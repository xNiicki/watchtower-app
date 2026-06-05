<?php

declare(strict_types=1);

use App\Contracts\TokenStore;
use App\Data\Alert;
use App\Data\AlertTier;
use App\Data\AppEvent;
use App\Data\AppHealth;
use App\Data\DashboardSummary;
use App\Data\Target;
use App\Data\TargetStatus;
use App\Exceptions\HubUnreachableException;
use App\Services\EndpointResolver;
use App\Services\HttpHubClient;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Tests for HttpHubClient — always instantiate directly so the test's HubClient
 * binding (FakeHubClient, per phpunit.xml) does not interfere.
 */
class HttpHubClientTest extends TestCase
{
    use RefreshDatabase;

    private const BASE = 'http://hub.test';

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();

        // Seed the token store so EndpointResolver resolves to BASE without probing.
        // No hub.local_url is set, so EndpointResolver skips the /up probe entirely.
        app(TokenStore::class)->set('hub.remote_url', self::BASE);
        app(TokenStore::class)->set('hub.token', 'test-token-abc');
    }

    private function client(): HttpHubClient
    {
        // Always instantiate a fresh EndpointResolver (not the scoped instance)
        // so probe caching does not bleed across tests.
        $resolver = new EndpointResolver(app(TokenStore::class));

        return new HttpHubClient($resolver, app(TokenStore::class));
    }

    // -------------------------------------------------------------------------
    // summary()
    // -------------------------------------------------------------------------

    public function test_summary_hydrates_dashboard_summary_from_flat_json(): void
    {
        Http::fake([
            self::BASE.'/api/v1/summary' => Http::response([
                'targetsUp' => 14,
                'targetsTotal' => 18,
                'targetsPaused' => 2,
                'openAlerts' => [
                    [
                        'id' => 'al-1',
                        'tier' => 'critical',
                        'title' => 'Disk 91%',
                        'message' => 'Root filesystem at 91%.',
                        'targetId' => 'mariadb-prod',
                        'firedAt' => '2026-06-02T10:00:00Z',
                        'acknowledged' => false,
                        'resolvedAt' => null,
                    ],
                    [
                        'id' => 'al-2',
                        'tier' => 'warning',
                        'title' => 'jelly down',
                        'message' => 'HTTP check failing.',
                        'targetId' => 'jelly',
                        'firedAt' => '2026-06-02T11:00:00Z',
                        'acknowledged' => true,
                        'resolvedAt' => null,
                    ],
                ],
                'nodes' => [
                    ['id' => 'pve', 'name' => 'pve', 'type' => 'node', 'status' => 'up', 'node' => null, 'cpuPercent' => 23.0, 'memPercent' => 61.0, 'diskPercent' => 48.0, 'latencyMs' => null],
                ],
                'apps' => [
                    ['name' => 'booking', 'slug' => 'booking', 'healthy' => true, 'errorsLastHour' => 0, 'queueDepth' => 3, 'failedJobs24h' => 1, 'mailSent24h' => 14, 'lastDeployAt' => '2026-05-31T08:00:00Z'],
                ],
                'lastBackupAt' => '2026-06-02T03:12:00Z',
                'lastBackupOk' => true,
                'tankUsagePercent' => 71.4,
            ]),
        ]);

        $summary = $this->client()->summary();

        $this->assertInstanceOf(DashboardSummary::class, $summary);
        $this->assertSame(14, $summary->targetsUp);
        $this->assertSame(18, $summary->targetsTotal);
        $this->assertSame(2, $summary->targetsPaused);
        $this->assertCount(2, $summary->openAlerts);
        $this->assertCount(1, $summary->nodes);
        $this->assertCount(1, $summary->apps);
        $this->assertTrue($summary->lastBackupOk);
        $this->assertEqualsWithDelta(71.4, $summary->tankUsagePercent, 0.01);

        // CarbonImmutable parse check
        $this->assertInstanceOf(CarbonImmutable::class, $summary->lastBackupAt);
        $this->assertSame('03:12', $summary->lastBackupAt->format('H:i'));

        // Alert hydration — enums + acknowledged flag
        $critical = $summary->openAlerts->firstWhere('id', 'al-1');
        $this->assertInstanceOf(Alert::class, $critical);
        $this->assertSame(AlertTier::Critical, $critical->tier);
        $this->assertFalse($critical->acknowledged);
        $this->assertInstanceOf(CarbonImmutable::class, $critical->firedAt);

        $warning = $summary->openAlerts->firstWhere('id', 'al-2');
        $this->assertTrue($warning->acknowledged);
        $this->assertSame(AlertTier::Warning, $warning->tier);

        // Node hydration
        $node = $summary->nodes->first();
        $this->assertInstanceOf(Target::class, $node);
        $this->assertSame(TargetStatus::Up, $node->status);
        $this->assertSame('pve', $node->name);

        // AppHealth hydration
        $app = $summary->apps->first();
        $this->assertInstanceOf(AppHealth::class, $app);
        $this->assertSame('booking', $app->name);
        $this->assertTrue($app->healthy);
        $this->assertInstanceOf(CarbonImmutable::class, $app->lastDeployAt);
    }

    // -------------------------------------------------------------------------
    // targets() / target()
    // -------------------------------------------------------------------------

    public function test_targets_unwraps_data_envelope(): void
    {
        Http::fake([
            self::BASE.'/api/v1/targets' => Http::response([
                'data' => [
                    ['id' => 'pve', 'name' => 'pve', 'type' => 'node', 'status' => 'up', 'node' => null, 'cpuPercent' => 23.0, 'memPercent' => 61.0, 'diskPercent' => 48.0, 'latencyMs' => null],
                    ['id' => 'jelly', 'name' => 'jelly', 'type' => 'lxc', 'status' => 'down', 'node' => 'pve', 'cpuPercent' => null, 'memPercent' => null, 'diskPercent' => null, 'latencyMs' => null],
                ],
            ]),
        ]);

        $targets = $this->client()->targets();

        $this->assertCount(2, $targets);
        $this->assertInstanceOf(Target::class, $targets->first());
        $this->assertSame(TargetStatus::Up, $targets->firstWhere('id', 'pve')->status);
        $this->assertSame(TargetStatus::Down, $targets->firstWhere('id', 'jelly')->status);
    }

    public function test_target_404_throws_invalid_argument_exception(): void
    {
        Http::fake([
            self::BASE.'/api/v1/targets/nope' => Http::response(['message' => 'Not Found'], 404),
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->client()->target('nope');
    }

    // -------------------------------------------------------------------------
    // alerts()
    // -------------------------------------------------------------------------

    public function test_alerts_unwraps_data_envelope_and_parses_fired_at(): void
    {
        Http::fake([
            self::BASE.'/api/v1/alerts' => Http::response([
                'data' => [
                    [
                        'id' => 'al-1',
                        'tier' => 'critical',
                        'title' => 'Disk 91%',
                        'message' => 'Root at 91%.',
                        'targetId' => 'mariadb-prod',
                        'firedAt' => '2026-06-02T09:00:00Z',
                        'acknowledged' => false,
                        'resolvedAt' => null,
                    ],
                ],
            ]),
        ]);

        $alerts = $this->client()->alerts();

        $this->assertCount(1, $alerts);
        $alert = $alerts->first();
        $this->assertInstanceOf(Alert::class, $alert);
        $this->assertInstanceOf(CarbonImmutable::class, $alert->firedAt);
        $this->assertSame('09:00', $alert->firedAt->format('H:i'));
        $this->assertSame(AlertTier::Critical, $alert->tier);
    }

    // -------------------------------------------------------------------------
    // acknowledgeAlert()
    // -------------------------------------------------------------------------

    public function test_acknowledge_posts_with_bearer_and_returns_true(): void
    {
        Http::fake([
            self::BASE.'/api/v1/alerts/al-1/ack' => Http::response(['data' => ['id' => 'al-1', 'acknowledged' => true]], 200),
        ]);

        $result = $this->client()->acknowledgeAlert('al-1');

        $this->assertTrue($result);

        Http::assertSent(function (Request $request) {
            return str_contains($request->url(), '/api/v1/alerts/al-1/ack')
                && $request->method() === 'POST'
                && $request->header('Authorization') === ['Bearer test-token-abc'];
        });
    }

    public function test_acknowledge_403_throws_hub_unreachable_with_auth_message(): void
    {
        Http::fake([
            self::BASE.'/api/v1/alerts/al-1/ack' => Http::response(['message' => 'Forbidden'], 403),
        ]);

        $this->expectException(HubUnreachableException::class);
        $this->expectExceptionMessageMatches('/403|Forbidden|token|auth/i');

        $this->client()->acknowledgeAlert('al-1');
    }

    // -------------------------------------------------------------------------
    // Unconfigured / transport errors
    // -------------------------------------------------------------------------

    public function test_unconfigured_resolver_throws_before_any_http(): void
    {
        app(TokenStore::class)->forget('hub.local_url');
        app(TokenStore::class)->forget('hub.remote_url');

        Http::fake(); // should not be called

        $this->expectException(HubUnreachableException::class);
        $this->expectExceptionMessageMatches('/not configured|Settings/i');

        // Bind a fresh resolver that sees the now-cleared store.
        $resolver = new EndpointResolver(app(TokenStore::class));
        $client = new HttpHubClient($resolver, app(TokenStore::class));
        $client->targets();

        Http::assertNothingSent();
    }

    public function test_connection_exception_becomes_transport_exception(): void
    {
        Http::fake([
            self::BASE.'/api/v1/targets' => fn () => throw new ConnectionException('Connection refused'),
        ]);

        $this->expectException(HubUnreachableException::class);
        $this->expectExceptionMessageMatches('/Connection refused|unreachable/i');

        $this->client()->targets();
    }

    // -------------------------------------------------------------------------
    // Request headers
    // -------------------------------------------------------------------------

    public function test_all_requests_carry_bearer_token_and_accept_json(): void
    {
        Http::fake([
            self::BASE.'/api/v1/targets' => Http::response(['data' => []], 200),
        ]);

        $this->client()->targets();

        Http::assertSent(function (Request $request) {
            return str_contains($request->url(), '/api/v1/targets')
                && $request->header('Authorization') === ['Bearer test-token-abc']
                && $request->header('Accept') === ['application/json'];
        });
    }

    // -------------------------------------------------------------------------
    // logs() query params
    // -------------------------------------------------------------------------

    public function test_logs_passes_host_severity_search_as_query_params(): void
    {
        Http::fake([
            self::BASE.'/api/v1/logs*' => Http::response([], 200),
        ]);

        $this->client()->logs(['host' => 'pve', 'severity' => 'err', 'search' => 'backup']);

        Http::assertSent(function (Request $request) {
            $url = $request->url();

            return str_contains($url, 'host=pve')
                && str_contains($url, 'severity=err')
                && str_contains($url, 'search=backup');
        });
    }

    // -------------------------------------------------------------------------
    // apps() — lastSeenAt + stale hydration
    // -------------------------------------------------------------------------

    public function test_apps_hydrates_last_seen_at_and_stale(): void
    {
        Http::fake([
            self::BASE.'/api/v1/summary' => Http::response([
                'targetsUp' => 1,
                'targetsTotal' => 1,
                'targetsPaused' => 0,
                'openAlerts' => [],
                'nodes' => [],
                'apps' => [[
                    'name' => 'booking', 'slug' => 'booking', 'healthy' => false,
                    'errorsLastHour' => 0, 'queueDepth' => 0,
                    'failedJobs24h' => 0, 'mailSent24h' => 0,
                    'lastDeployAt' => null,
                    'lastSeenAt' => '2026-06-04T17:40:00+00:00',
                    'stale' => true,
                ]],
                'lastBackupAt' => null,
                'lastBackupOk' => false,
                'tankUsagePercent' => 0.0,
            ]),
        ]);

        $apps = $this->client()->apps();

        $this->assertCount(1, $apps);

        /** @var AppHealth $app */
        $app = $apps->first();
        $this->assertInstanceOf(AppHealth::class, $app);
        $this->assertTrue($app->stale);
        $this->assertInstanceOf(CarbonImmutable::class, $app->lastSeenAt);
        $this->assertSame('2026-06-04T17:40:00+00:00', $app->lastSeenAt->toIso8601String());
    }

    // -------------------------------------------------------------------------
    // appEvents()
    // -------------------------------------------------------------------------

    public function test_fetches_app_events(): void
    {
        Http::fake([self::BASE.'/api/v1/apps/booking/events*' => Http::response([[
            'id' => '7', 'type' => 'exception', 'severity' => 'critical',
            'title' => 'TypeError', 'message' => 'boom', 'occurrences' => 12,
            'firstSeenAt' => '2026-06-04T17:00:00+00:00',
            'lastSeenAt' => '2026-06-04T17:30:00+00:00',
        ]], 200)]);

        $events = $this->client()->appEvents('booking');

        $this->assertCount(1, $events);
        $this->assertInstanceOf(AppEvent::class, $events->first());
        $this->assertSame('TypeError', $events->first()->title);
        $this->assertSame(12, $events->first()->occurrences);
        $this->assertSame('critical', $events->first()->severity);
    }

    public function test_app_events_404_returns_an_empty_collection_not_an_error(): void
    {
        // Unknown app slug is a graceful empty list, mirroring FakeHubClient.
        Http::fake([self::BASE.'/api/v1/apps/ghost/events*' => Http::response(['message' => 'Not Found'], 404)]);

        $events = $this->client()->appEvents('ghost');

        $this->assertTrue($events->isEmpty());
    }

    public function test_app_events_surfaces_non_404_errors(): void
    {
        // 5xx is a real hub failure and must not be swallowed as an empty list.
        Http::fake([self::BASE.'/api/v1/apps/booking/events*' => Http::response(['message' => 'boom'], 500)]);

        $this->expectException(HubUnreachableException::class);

        $this->client()->appEvents('booking');
    }

    // -------------------------------------------------------------------------
    // appMetrics()
    // -------------------------------------------------------------------------

    public function test_fetches_app_metrics_latest_summary(): void
    {
        Http::fake([self::BASE.'/api/v1/apps/booking/metrics*' => Http::response([
            'range' => '1h',
            'series' => (object) [],
            'latest' => [
                'requestsPerMin' => 120, 'latencyAvgMs' => 45, 'latencyMaxMs' => 320,
                'slowRequests' => 2, 'slowQueries' => 1,
            ],
        ], 200)]);

        $m = $this->client()->appMetrics('booking');

        $this->assertNotNull($m);
        $this->assertSame(120, $m->requestsPerMin);
        $this->assertSame(45, $m->latencyAvgMs);
        $this->assertSame(2, $m->slowRequests);
    }

    public function test_app_metrics_404_returns_null_not_an_exception(): void
    {
        // Unknown app slug is a graceful miss, mirroring FakeHubClient.
        Http::fake([self::BASE.'/api/v1/apps/ghost/metrics*' => Http::response(['message' => 'Not Found'], 404)]);

        $this->assertNull($this->client()->appMetrics('ghost'));
    }

    public function test_app_metrics_surfaces_non_404_errors(): void
    {
        // 5xx is a real hub failure and must not be swallowed as a null miss.
        Http::fake([self::BASE.'/api/v1/apps/booking/metrics*' => Http::response(['message' => 'boom'], 500)]);

        $this->expectException(HubUnreachableException::class);

        $this->client()->appMetrics('booking');
    }

    public function test_app_metrics_series_hydrates_points_per_key(): void
    {
        Http::fake(['*/api/v1/apps/booking/metrics*' => Http::response([
            'range' => '6h',
            'series' => [
                'requests' => [
                    ['at' => '2026-06-05T05:00:00+00:00', 'value' => 120],
                    ['at' => '2026-06-05T05:01:00+00:00', 'value' => 150],
                ],
                'request_latency_avg_ms' => [['at' => '2026-06-05T05:00:00+00:00', 'value' => 45.5]],
                'request_latency_max_ms' => [['at' => '2026-06-05T05:00:00+00:00', 'value' => 320]],
            ],
            'latest' => [],
        ])]);

        $series = $this->client()->appMetricsSeries('booking', '6h');

        $this->assertNotNull($series);
        $this->assertSame('6h', $series->range);
        $this->assertCount(2, $series->requests);
        $this->assertSame(150.0, $series->requests[1]['value']);
        $this->assertSame(45.5, $series->latencyAvgMs[0]['value']);
    }

    public function test_app_metrics_series_404_returns_null(): void
    {
        Http::fake(['*/api/v1/apps/booking/metrics*' => Http::response(null, 404)]);
        $this->assertNull($this->client()->appMetricsSeries('booking', '1h'));
    }

    public function test_app_metrics_series_surfaces_non_404_errors(): void
    {
        Http::fake(['*/api/v1/apps/booking/metrics*' => Http::response(null, 500)]);
        $this->expectException(HubUnreachableException::class);
        $this->client()->appMetricsSeries('booking', '1h');
    }

    // -------------------------------------------------------------------------
    // appEvent()
    // -------------------------------------------------------------------------

    public function test_app_event_hydrates_detail_with_trace_and_context(): void
    {
        Http::fake(['*/api/v1/apps/booking/events/1' => Http::response([
            'id' => '1', 'type' => 'exception', 'severity' => 'critical',
            'title' => 'TypeError', 'message' => 'boom', 'occurrences' => 5,
            'firstSeenAt' => '2026-06-05T05:00:00+00:00', 'lastSeenAt' => '2026-06-05T05:04:00+00:00',
            'exceptionClass' => 'TypeError', 'file' => 'app/Foo.php', 'line' => 42,
            'trace' => '#0 app/Foo.php(42)', 'context' => ['queue' => 'default'],
        ])]);

        $detail = $this->client()->appEvent('booking', '1');

        $this->assertNotNull($detail);
        $this->assertSame('app/Foo.php', $detail->file);
        $this->assertSame(42, $detail->line);
        $this->assertSame('#0 app/Foo.php(42)', $detail->trace);
        $this->assertSame(['queue' => 'default'], $detail->context);
    }

    public function test_app_event_404_returns_null(): void
    {
        Http::fake(['*/api/v1/apps/booking/events/9' => Http::response(null, 404)]);
        $this->assertNull($this->client()->appEvent('booking', '9'));
    }

    public function test_app_event_surfaces_non_404_errors(): void
    {
        Http::fake(['*/api/v1/apps/booking/events/1' => Http::response(null, 500)]);
        $this->expectException(HubUnreachableException::class);
        $this->client()->appEvent('booking', '1');
    }

    // -------------------------------------------------------------------------
    // Transport failure clears endpoint cache (Change 2)
    // -------------------------------------------------------------------------

    public function test_transport_failure_clears_the_endpoint_cache(): void
    {
        // Prime the cache with a "reachable" remote endpoint.
        Cache::put('hub.resolved_base_url', self::BASE, 60);

        // The data call itself throws a ConnectionException (transport failure).
        Http::fake([
            self::BASE.'/api/v1/targets' => fn () => throw new ConnectionException('Connection refused'),
        ]);

        try {
            $this->client()->targets();
        } catch (HubUnreachableException) {
            // Expected — we only care about the side-effect.
        }

        // After the failure the cache entry must be gone so the next request re-probes.
        $this->assertNull(Cache::get('hub.resolved_base_url'));
    }
}

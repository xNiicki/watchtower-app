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
                    ['name' => 'booking', 'healthy' => true, 'errorsLastHour' => 0, 'queueDepth' => 3, 'failedJobs24h' => 1, 'mailSent24h' => 14, 'lastDeployAt' => '2026-05-31T08:00:00Z'],
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
                    'name' => 'booking', 'healthy' => false,
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

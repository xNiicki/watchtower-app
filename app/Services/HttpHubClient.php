<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\HubClient;
use App\Contracts\TokenStore;
use App\Data\Alert;
use App\Data\AlertTier;
use App\Data\AppEvent;
use App\Data\AppEventDetail;
use App\Data\AppHealth;
use App\Data\AppMetrics;
use App\Data\AppMetricsSeries;
use App\Data\DashboardSummary;
use App\Data\LogEntry;
use App\Data\Target;
use App\Data\TargetStatus;
use App\Exceptions\HubUnreachableException;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\HttpClientException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;

class HttpHubClient implements HubClient
{
    public function __construct(
        private readonly EndpointResolver $resolver,
        private readonly TokenStore $tokens,
    ) {}

    public function summary(): DashboardSummary
    {
        $data = $this->send('get', '/api/v1/summary')->json();

        return new DashboardSummary(
            targetsUp: (int) $data['targetsUp'],
            targetsTotal: (int) $data['targetsTotal'],
            targetsPaused: (int) $data['targetsPaused'],
            openAlerts: collect($data['openAlerts'])->map(fn (array $a) => $this->hydrateAlert($a)),
            nodes: collect($data['nodes'])->map(fn (array $t) => $this->hydrateTarget($t)),
            apps: collect($data['apps'])->map(fn (array $a) => $this->hydrateAppHealth($a)),
            lastBackupAt: isset($data['lastBackupAt']) ? CarbonImmutable::parse($data['lastBackupAt']) : null,
            lastBackupOk: (bool) $data['lastBackupOk'],
            tankUsagePercent: (float) $data['tankUsagePercent'],
        );
    }

    public function targets(): Collection
    {
        return collect($this->send('get', '/api/v1/targets')->json('data'))
            ->map(fn (array $t) => $this->hydrateTarget($t));
    }

    public function target(string $id): Target
    {
        $response = $this->send('get', "/api/v1/targets/{$id}", throwOnError: false);

        if ($response->status() === 404) {
            throw new InvalidArgumentException("Unknown target [{$id}]");
        }

        $this->guardResponse($response);

        return $this->hydrateTarget($response->json('data'));
    }

    public function alerts(): Collection
    {
        return collect($this->send('get', '/api/v1/alerts')->json('data'))
            ->map(fn (array $a) => $this->hydrateAlert($a));
    }

    public function acknowledgeAlert(string $id): bool
    {
        $response = $this->send('post', "/api/v1/alerts/{$id}/ack", throwOnError: false);

        if ($response->status() === 403) {
            throw HubUnreachableException::transport(
                new \RuntimeException('Forbidden — token lacks the alerts:ack ability (403).')
            );
        }

        $this->guardResponse($response);

        return $response->successful();
    }

    public function logs(array $filters = []): Collection
    {
        $query = array_filter([
            'host' => $filters['host'] ?? null,
            'severity' => $filters['severity'] ?? null,
            'search' => $filters['search'] ?? null,
        ]);

        return collect($this->send('get', '/api/v1/logs', query: $query)->json())
            ->map(fn (array $e) => $this->hydrateLogEntry($e));
    }

    /**
     * There is no dedicated /apps endpoint — AppHealth comes only inside summary
     * (the hub folds monitored-app health into /api/v1/summary).
     */
    public function apps(): Collection
    {
        return $this->summary()->apps;
    }

    public function appEvents(string $slug, array $filters = []): Collection
    {
        // Explicit callback instead of bare array_filter: preserves a numeric 0
        // limit (falsy) that bare array_filter would silently strip.
        $query = array_filter([
            'search' => $filters['search'] ?? null,
            'limit' => $filters['limit'] ?? null,
        ], fn ($v) => $v !== null && $v !== '');

        $response = $this->send('get', "/api/v1/apps/{$slug}/events", query: $query, throwOnError: false);

        // Unknown app slug is a graceful empty list, mirroring FakeHubClient.
        // Other non-2xx (401/403/5xx) still surface via guardResponse().
        if ($response->status() === 404) {
            return collect();
        }

        $this->guardResponse($response);

        return collect($response->json())
            ->map(fn (array $e) => $this->hydrateAppEvent($e));
    }

    public function appEvent(string $slug, string $id): ?AppEventDetail
    {
        $response = $this->send('get', "/api/v1/apps/{$slug}/events/{$id}", throwOnError: false);

        // Unknown app/event is a graceful miss (null); other non-2xx surface.
        if ($response->status() === 404) {
            return null;
        }

        $this->guardResponse($response);

        $d = $response->json();

        return new AppEventDetail(
            id: (string) $d['id'],
            type: (string) $d['type'],
            severity: (string) $d['severity'],
            title: (string) $d['title'],
            message: (string) $d['message'],
            occurrences: (int) $d['occurrences'],
            firstSeenAt: CarbonImmutable::parse($d['firstSeenAt']),
            lastSeenAt: CarbonImmutable::parse($d['lastSeenAt']),
            exceptionClass: $d['exceptionClass'] ?? null,
            file: $d['file'] ?? null,
            line: isset($d['line']) ? (int) $d['line'] : null,
            trace: $d['trace'] ?? null,
            context: is_array($d['context'] ?? null) ? $d['context'] : [],
        );
    }

    public function appMetricsSeries(string $slug, string $range = '1h'): ?AppMetricsSeries
    {
        $response = $this->send('get', "/api/v1/apps/{$slug}/metrics", query: ['range' => $range], throwOnError: false);

        if ($response->status() === 404) {
            return null;
        }

        $this->guardResponse($response);

        $series = $response->json('series');
        if (! is_array($series)) {
            return null;
        }

        $points = fn (string $key): array => collect($series[$key] ?? [])
            ->map(fn (array $p) => ['at' => CarbonImmutable::parse($p['at']), 'value' => (float) $p['value']])
            ->all();

        return new AppMetricsSeries(
            range: (string) ($response->json('range') ?? $range),
            requests: $points('requests'),
            latencyAvgMs: $points('request_latency_avg_ms'),
            latencyMaxMs: $points('request_latency_max_ms'),
        );
    }

    public function appMetrics(string $slug): ?AppMetrics
    {
        $response = $this->send('get', "/api/v1/apps/{$slug}/metrics", throwOnError: false);

        // Unknown app slug is a graceful miss (null), mirroring FakeHubClient.
        // Other non-2xx (401/403/5xx) still surface via guardResponse().
        if ($response->status() === 404) {
            return null;
        }

        $this->guardResponse($response);

        $latest = $response->json('latest');

        if (! is_array($latest)) {
            return null;
        }

        return new AppMetrics(
            requestsPerMin: (int) ($latest['requestsPerMin'] ?? 0),
            latencyAvgMs: (int) ($latest['latencyAvgMs'] ?? 0),
            latencyMaxMs: (int) ($latest['latencyMaxMs'] ?? 0),
            slowRequests: (int) ($latest['slowRequests'] ?? 0),
            slowQueries: (int) ($latest['slowQueries'] ?? 0),
        );
    }

    // -------------------------------------------------------------------------
    // Internals
    // -------------------------------------------------------------------------

    /** @param array<string, mixed> $query */
    private function send(string $method, string $path, array $query = [], bool $throwOnError = true): Response
    {
        $base = $this->resolver->baseUrl();

        if ($base === null) {
            throw HubUnreachableException::unconfigured();
        }

        try {
            $request = $this->buildRequest();

            /** @var Response $response */
            $response = $query
                ? $request->{$method}(rtrim($base, '/').$path, $query)
                : $request->{$method}(rtrim($base, '/').$path);
        } catch (ConnectionException $e) {
            // Forget the cached endpoint so the next request re-probes; handles
            // LAN→VPS transitions without waiting for the 60s TTL to expire.
            $this->resolver->forget();

            throw HubUnreachableException::transport($e);
        } catch (HttpClientException $e) {
            $this->resolver->forget();

            throw HubUnreachableException::transport($e);
        }

        if ($throwOnError) {
            $this->guardResponse($response);
        }

        return $response;
    }

    private function buildRequest(): PendingRequest
    {
        $token = $this->tokens->get('hub.token');

        return Http::acceptJson()
            ->timeout(8)
            ->when($token !== null, fn (PendingRequest $r) => $r->withToken($token));
    }

    private function guardResponse(Response $response): void
    {
        if ($response->successful()) {
            return;
        }

        if ($response->status() === 401 || $response->status() === 403) {
            throw HubUnreachableException::transport(
                new \RuntimeException("Authentication failed — check your API token ({$response->status()}).")
            );
        }

        throw HubUnreachableException::transport(
            new \RuntimeException("Unexpected hub response {$response->status()}.")
        );
    }

    /** @param array<string, mixed> $data */
    private function hydrateTarget(array $data): Target
    {
        return new Target(
            id: (string) $data['id'],
            name: (string) $data['name'],
            type: (string) $data['type'],
            status: TargetStatus::from($data['status']),
            node: isset($data['node']) ? (string) $data['node'] : null,
            cpuPercent: isset($data['cpuPercent']) ? (float) $data['cpuPercent'] : null,
            memPercent: isset($data['memPercent']) ? (float) $data['memPercent'] : null,
            diskPercent: isset($data['diskPercent']) ? (float) $data['diskPercent'] : null,
            latencyMs: isset($data['latencyMs']) ? (int) $data['latencyMs'] : null,
        );
    }

    /** @param array<string, mixed> $data */
    private function hydrateAlert(array $data): Alert
    {
        return new Alert(
            id: (string) $data['id'],
            tier: AlertTier::from($data['tier']),
            title: (string) $data['title'],
            message: (string) $data['message'],
            targetId: isset($data['targetId']) ? (string) $data['targetId'] : null,
            // Contract: never null for active alerts (hub falls back to pending_since);
            // defensive now() guards against out-of-band rows rather than parsing null as "now" silently.
            firedAt: isset($data['firedAt']) ? CarbonImmutable::parse($data['firedAt']) : CarbonImmutable::now(),
            acknowledged: (bool) ($data['acknowledged'] ?? false),
            resolvedAt: isset($data['resolvedAt']) ? CarbonImmutable::parse($data['resolvedAt']) : null,
        );
    }

    /** @param array<string, mixed> $data */
    private function hydrateAppHealth(array $data): AppHealth
    {
        return new AppHealth(
            name: (string) $data['name'],
            slug: (string) ($data['slug'] ?? ''),
            healthy: (bool) $data['healthy'],
            errorsLastHour: (int) $data['errorsLastHour'],
            queueDepth: (int) $data['queueDepth'],
            failedJobs24h: (int) $data['failedJobs24h'],
            mailSent24h: (int) $data['mailSent24h'],
            lastDeployAt: isset($data['lastDeployAt']) ? CarbonImmutable::parse($data['lastDeployAt']) : null,
            lastSeenAt: isset($data['lastSeenAt']) ? CarbonImmutable::parse($data['lastSeenAt']) : null,
            stale: (bool) ($data['stale'] ?? false),
        );
    }

    /** @param array<string, mixed> $data */
    private function hydrateLogEntry(array $data): LogEntry
    {
        return new LogEntry(
            id: (string) $data['id'],
            host: (string) $data['host'],
            severity: (string) $data['severity'],
            message: (string) $data['message'],
            loggedAt: CarbonImmutable::parse($data['loggedAt']),
        );
    }

    /** @param array<string, mixed> $data */
    private function hydrateAppEvent(array $data): AppEvent
    {
        return new AppEvent(
            id: (string) $data['id'],
            type: (string) $data['type'],
            severity: (string) $data['severity'],
            title: (string) $data['title'],
            message: (string) $data['message'],
            occurrences: (int) $data['occurrences'],
            firstSeenAt: CarbonImmutable::parse($data['firstSeenAt']),
            lastSeenAt: CarbonImmutable::parse($data['lastSeenAt']),
        );
    }
}

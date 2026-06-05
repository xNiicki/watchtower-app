<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\HubClient;
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
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use InvalidArgumentException;

/**
 * Fixture-backed HubClient mirroring the real homelab so screens are
 * developed against realistic data. Replaced by HttpHubClient in Plan E.
 * Acks persist in the session so on-device navigation feels real; the hub
 * owns this state in Plan E.
 */
class FakeHubClient implements HubClient
{
    /** @var array<string, bool> */
    private array $acknowledged = [];

    public function summary(): DashboardSummary
    {
        $targets = $this->targets();

        return new DashboardSummary(
            targetsUp: $targets->where('status', TargetStatus::Up)->count(),
            targetsTotal: $targets->count(),
            targetsPaused: $targets->where('status', TargetStatus::Paused)->count(),
            openAlerts: $this->alerts(),
            nodes: $targets->where('type', 'node')->values(),
            apps: $this->apps(),
            lastBackupAt: CarbonImmutable::parse('yesterday 03:12'),
            lastBackupOk: true,
            tankUsagePercent: 71.4,
        );
    }

    public function targets(): Collection
    {
        return collect([
            new Target('pve', 'pve', 'node', TargetStatus::Up, null, 23.0, 61.0, 48.0),
            new Target('backup', 'backup', 'node', TargetStatus::Up, null, 4.0, 32.0, 55.0),
            new Target('tank', 'tank', 'storage', TargetStatus::Up, 'backup', null, null, 71.4),
            new Target('immich', 'immich', 'lxc', TargetStatus::Up, 'pve', 12.0, 54.0, 40.0, 23),
            new Target('alpine-ntfy', 'alpine-ntfy', 'lxc', TargetStatus::Up, 'pve', 1.0, 18.0, 12.0, 8),
            new Target('dockge-2', 'dockge-2', 'lxc', TargetStatus::Up, 'pve', 8.0, 44.0, 35.0, 15),
            new Target('mariadb-prod', 'mariadb-prod', 'lxc', TargetStatus::Up, 'pve', 31.0, 72.0, 91.0, 4),
            new Target('redis-prod', 'redis-prod', 'lxc', TargetStatus::Up, 'pve', 5.0, 38.0, 22.0, 2),
            new Target('adguard', 'adguard', 'lxc', TargetStatus::Up, 'pve', 2.0, 21.0, 18.0, 6),
            new Target('mongodb', 'mongodb', 'lxc', TargetStatus::Up, 'pve', 9.0, 47.0, 39.0, 5),
            new Target('paperless-ngx', 'paperless-ngx', 'lxc', TargetStatus::Up, 'pve', 6.0, 41.0, 52.0, 31),
            new Target('booking', 'booking', 'lxc', TargetStatus::Up, 'pve', 14.0, 58.0, 44.0, 41),
            new Target('jelly', 'jelly', 'lxc', TargetStatus::Down, 'pve', null, null, null),
            new Target('arr', 'arr', 'lxc', TargetStatus::Up, 'pve', 7.0, 49.0, 63.0, 19),
            new Target('wg', 'wg', 'lxc', TargetStatus::Up, 'pve', 1.0, 9.0, 8.0, 3),
            new Target('haos', 'haos', 'vm', TargetStatus::Up, 'pve', 11.0, 52.0, 37.0, 27),
            new Target('paperless-gpt', 'paperless-gpt', 'lxc', TargetStatus::Paused, 'pve'),
            new Target('o3-test', 'o3-test', 'lxc', TargetStatus::Paused, 'pve'),
        ]);
    }

    public function target(string $id): Target
    {
        return $this->targets()->firstWhere('id', $id)
            ?? throw new InvalidArgumentException("Unknown target [{$id}]");
    }

    public function alerts(): Collection
    {
        return collect([
            new Alert(
                id: 'al-001',
                tier: AlertTier::Critical,
                title: 'Disk usage 91% on mariadb-prod',
                message: 'Root filesystem at 91% (threshold 90%) for 12 minutes.',
                targetId: 'mariadb-prod',
                firedAt: CarbonImmutable::now()->subMinutes(12),
                acknowledged: $this->acknowledged['al-001'] ?? session()->get('fake-hub.ack.al-001', false),
            ),
            new Alert(
                id: 'al-002',
                tier: AlertTier::Warning,
                title: 'jelly unreachable',
                message: 'HTTP check failing for 4 minutes. Media stack never pushes.',
                targetId: 'jelly',
                firedAt: CarbonImmutable::now()->subMinutes(4),
                acknowledged: $this->acknowledged['al-002'] ?? session()->get('fake-hub.ack.al-002', false),
            ),
        ]);
    }

    public function acknowledgeAlert(string $id): bool
    {
        $this->acknowledged[$id] = true;
        session()->put('fake-hub.ack.'.$id, true);

        return true;
    }

    public function logs(array $filters = []): Collection
    {
        $entries = collect([
            new LogEntry('lg-1', 'pve', 'info', 'pvedaemon: worker started', CarbonImmutable::now()->subMinutes(2)),
            new LogEntry('lg-2', 'mariadb-prod', 'warning', 'Aborted connection 4123 (Got timeout reading communication packets)', CarbonImmutable::now()->subMinutes(7)),
            new LogEntry('lg-3', 'backup', 'info', 'proxmox-backup-proxy: backup job booking-daily finished successfully', CarbonImmutable::now()->subHours(3)),
            new LogEntry('lg-4', 'mariadb-prod', 'err', 'InnoDB: page cleaner: 1000ms intended loop took 4310ms', CarbonImmutable::now()->subMinutes(31)),
            new LogEntry('lg-5', 'booking', 'info', 'horizon: queue worker restarted', CarbonImmutable::now()->subMinutes(18)),
            new LogEntry('lg-6', 'redis-prod', 'notice', 'Background saving terminated with success', CarbonImmutable::now()->subMinutes(44)),
        ]);

        return $entries
            ->when(isset($filters['host']), fn ($c) => $c->where('host', $filters['host']))
            ->when(isset($filters['severity']), fn ($c) => $c->where('severity', $filters['severity']))
            ->when(isset($filters['search']), fn ($c) => $c->filter(
                fn (LogEntry $e) => str_contains(strtolower($e->message), strtolower($filters['search']))
            ))
            ->values();
    }

    public function apps(): Collection
    {
        return collect([
            new AppHealth(
                name: 'booking',
                slug: 'booking',
                healthy: true,
                errorsLastHour: 0,
                queueDepth: 3,
                failedJobs24h: 1,
                mailSent24h: 14,
                lastDeployAt: CarbonImmutable::now()->subDays(2),
                lastSeenAt: CarbonImmutable::now()->subMinute(),
                stale: false,
                bufferDepth: 3,
                lastShipError: 'POST /api/ingest/event → 404',
                deliveryDegraded: true,
            ),
            new AppHealth(
                name: 'newsletter',
                slug: 'newsletter',
                healthy: false,
                errorsLastHour: 0,
                queueDepth: 0,
                failedJobs24h: 0,
                mailSent24h: 0,
                lastDeployAt: null,
                lastSeenAt: CarbonImmutable::now()->subMinutes(42),
                stale: true,
                bufferDepth: 0,
                lastShipError: null,
                deliveryDegraded: false,
            ),
        ]);
    }

    public function appEvents(string $slug, array $filters = []): Collection
    {
        if ($slug !== 'booking') {
            return collect();
        }

        return collect([
            new AppEvent('1', 'exception', 'critical', 'TypeError', 'Cannot read property foo', 412,
                CarbonImmutable::now()->subHours(2), CarbonImmutable::now()->subMinutes(3)),
            new AppEvent('2', 'failed_job', 'critical', 'App\\Jobs\\SendInvoice', 'SMTP connect failed', 7,
                CarbonImmutable::now()->subDay(), CarbonImmutable::now()->subMinutes(30)),
        ])->when(isset($filters['search']), fn ($c) => $c->filter(
            fn (AppEvent $e) => str_contains(strtolower($e->message), strtolower((string) $filters['search']))
        ))->values();
    }

    public function appEvent(string $slug, string $id): ?AppEventDetail
    {
        if ($slug !== 'booking' || $id !== '1') {
            return null;
        }

        return new AppEventDetail(
            id: '1', type: 'exception', severity: 'critical', title: 'TypeError',
            message: 'Cannot read property foo', occurrences: 412,
            firstSeenAt: CarbonImmutable::now()->subHours(2),
            lastSeenAt: CarbonImmutable::now()->subMinutes(3),
            exceptionClass: 'TypeError', file: 'app/Services/Booking.php', line: 42,
            trace: "#0 app/Services/Booking.php(42): Booking->load()\n#1 app/Http/BookingController.php(88)",
            context: ['queue' => 'default', 'connection' => 'redis', 'user_id' => 1042],
        );
    }

    public function appMetrics(string $slug): ?AppMetrics
    {
        if ($slug !== 'booking') {
            return null;
        }

        return new AppMetrics(requestsPerMin: 120, latencyAvgMs: 45, latencyMaxMs: 320, slowRequests: 2, slowQueries: 1);
    }

    public function appMetricsSeries(string $slug, string $range = '1h'): ?AppMetricsSeries
    {
        if ($slug !== 'booking') {
            return null;
        }

        $now = CarbonImmutable::now()->startOfMinute();
        $mk = fn (array $values) => collect($values)
            ->map(fn ($v, $i) => ['at' => $now->subMinutes(count($values) - 1 - $i), 'value' => (float) $v])
            ->all();

        return new AppMetricsSeries(
            range: in_array($range, ['1h', '6h', '24h'], true) ? $range : '1h',
            requests: $mk([90, 110, 130, 105, 150, 140, 162, 120]),
            latencyAvgMs: $mk([40, 44, 38, 47, 42, 45, 41, 46]),
            latencyMaxMs: $mk([210, 260, 300, 280, 320, 290, 340, 320]),
        );
    }
}

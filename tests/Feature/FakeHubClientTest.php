<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Contracts\HubClient;
use App\Data\Alert;
use App\Data\AppEvent;
use App\Data\AppHealth;
use App\Data\AppMetrics;
use App\Data\AppMetricsSeries;
use App\Data\DashboardSummary;
use App\Data\Target;
use App\Data\TargetStatus;
use App\Services\FakeHubClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class FakeHubClientTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function summary_reflects_fixture_fleet(): void
    {
        $summary = app(HubClient::class)->summary();

        $this->assertInstanceOf(DashboardSummary::class, $summary);
        $this->assertGreaterThanOrEqual(15, $summary->targetsTotal);
        $this->assertLessThanOrEqual($summary->targetsTotal, $summary->targetsUp);
        $this->assertNotEmpty($summary->openAlerts);
    }

    #[Test]
    public function targets_returns_the_fleet_with_valid_statuses(): void
    {
        $targets = app(HubClient::class)->targets();

        $this->assertNotEmpty($targets);

        foreach ($targets as $target) {
            $this->assertInstanceOf(Target::class, $target);
        }

        foreach ($targets->pluck('status')->unique() as $status) {
            $this->assertInstanceOf(TargetStatus::class, $status);
        }
    }

    #[Test]
    public function target_finds_one_by_id_and_throws_for_unknown(): void
    {
        $client = app(HubClient::class);

        $this->assertSame('booking', $client->target('booking')->name);

        $this->expectException(\InvalidArgumentException::class);
        $client->target('does-not-exist');
    }

    #[Test]
    public function alerts_returns_open_alerts_acknowledge_flips_the_flag(): void
    {
        // Relies on the scoped container binding: same FakeHubClient instance across calls in one request.
        $client = app(HubClient::class);
        $first = $client->alerts()->first();

        $this->assertInstanceOf(Alert::class, $first);
        $this->assertFalse($first->acknowledged);

        $client->acknowledgeAlert($first->id);

        $this->assertTrue($client->alerts()->firstWhere('id', $first->id)->acknowledged);
    }

    #[Test]
    public function logs_filter_by_host_and_search_term(): void
    {
        $client = app(HubClient::class);

        $hostLogs = $client->logs(['host' => 'mariadb-prod']);
        $this->assertNotEmpty($hostLogs);

        foreach ($hostLogs as $entry) {
            $this->assertSame('mariadb-prod', $entry->host);
        }

        $this->assertLessThan($client->logs()->count(), $client->logs(['search' => 'backup'])->count());
    }

    #[Test]
    public function apps_returns_booking_health(): void
    {
        $apps = app(HubClient::class)->apps();

        $this->assertNotEmpty($apps);
        $this->assertInstanceOf(AppHealth::class, $apps->first());
        $this->assertSame('booking', $apps->first()->name);
    }

    #[Test]
    public function test_app_events_returns_fixtures_and_filters_on_search(): void
    {
        $client = app(HubClient::class);

        $events = $client->appEvents('booking');

        $this->assertCount(2, $events);
        foreach ($events as $event) {
            $this->assertInstanceOf(AppEvent::class, $event);
        }

        $filtered = $client->appEvents('booking', ['search' => 'smtp']);
        $this->assertCount(1, $filtered);
        $this->assertSame('App\\Jobs\\SendInvoice', $filtered->first()->title);
    }

    #[Test]
    public function app_event_returns_detail_for_booking_and_null_otherwise(): void
    {
        $fake = new FakeHubClient;
        $this->assertNotNull($fake->appEvent('booking', '1'));
        $this->assertSame('TypeError', $fake->appEvent('booking', '1')->exceptionClass);
        $this->assertNull($fake->appEvent('booking', '999'));
        $this->assertNull($fake->appEvent('unknown', '1'));
    }

    #[Test]
    public function app_metrics_returns_summary_for_known_slug_and_null_for_unknown(): void
    {
        $client = app(HubClient::class);

        $metrics = $client->appMetrics('booking');

        $this->assertInstanceOf(AppMetrics::class, $metrics);
        $this->assertSame(120, $metrics->requestsPerMin);
        $this->assertSame(45, $metrics->latencyAvgMs);
        $this->assertSame(320, $metrics->latencyMaxMs);
        $this->assertSame(2, $metrics->slowRequests);
        $this->assertSame(1, $metrics->slowQueries);

        $this->assertNull($client->appMetrics('unknown'));
    }

    #[Test]
    public function app_metrics_series_returns_points_for_booking_and_null_otherwise(): void
    {
        $fake = new FakeHubClient;
        $series = $fake->appMetricsSeries('booking', '6h');
        $this->assertNotNull($series);
        $this->assertInstanceOf(AppMetricsSeries::class, $series);
        $this->assertSame('6h', $series->range);
        $this->assertNotEmpty($series->requests);
        $this->assertNull($fake->appMetricsSeries('unknown'));
    }
}

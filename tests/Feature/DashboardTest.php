<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Contracts\HubClient;
use App\Livewire\Dashboard;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function shows_fleet_summary_counts(): void
    {
        Livewire::test(Dashboard::class)
            ->assertSee('15/18 up')   // FakeHubClient: 15 Up, 1 Down (jelly), 2 Paused = 18 targets
            ->assertSee('2 paused');
    }

    #[Test]
    public function shows_open_alerts_with_tier_styling(): void
    {
        Livewire::test(Dashboard::class)
            ->assertSee('Disk usage 91% on mariadb-prod')
            ->assertSee('jelly unreachable')
            ->assertSeeHtml('border-red-500')
            ->assertSeeHtml('border-amber-500');
    }

    #[Test]
    public function shows_node_app_and_backup_cards(): void
    {
        Livewire::test(Dashboard::class)
            ->assertSee('pve')
            ->assertSee('booking')
            ->assertSee('03:12')
            ->assertSee('tank 71%');
    }

    #[Test]
    public function acknowledged_alerts_drop_off_the_dashboard(): void
    {
        // Both alerts visible, critical present → "Attention needed".
        Livewire::test(Dashboard::class)
            ->assertSee('Disk usage 91% on mariadb-prod')
            ->assertSee('Attention needed');

        // Acknowledge both (al-001 critical, al-002 warning).
        app(HubClient::class)->acknowledgeAlert('al-001');
        app(HubClient::class)->acknowledgeAlert('al-002');

        // Gone from the dashboard; header back to green.
        Livewire::test(Dashboard::class)
            ->assertDontSee('Disk usage 91% on mariadb-prod')
            ->assertDontSee('jelly unreachable')
            ->assertSee('All systems go');
    }

    #[Test]
    public function refresh_action_exists_and_re_renders(): void
    {
        Livewire::test(Dashboard::class)
            ->call('refresh')
            ->assertSuccessful();
    }
}

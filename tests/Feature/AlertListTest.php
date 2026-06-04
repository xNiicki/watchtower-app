<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Contracts\HubClient;
use App\Livewire\Alerts\AlertList;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AlertListTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function lists_open_alerts_ordered_critical_first(): void
    {
        Livewire::test(AlertList::class)
            ->assertSeeInOrder(['Disk usage 91% on mariadb-prod', 'jelly unreachable']);
    }

    #[Test]
    public function acknowledging_an_alert_marks_it_acked_in_the_ui(): void
    {
        Livewire::test(AlertList::class)
            ->call('acknowledge', 'al-001')
            ->assertSeeInOrder(['acknowledged', 'Acknowledge']);
    }

    #[Test]
    public function shows_the_empty_state_when_there_are_no_alerts(): void
    {
        $mock = $this->mock(HubClient::class);
        $mock->shouldReceive('alerts')->andReturn(collect());

        Livewire::test(AlertList::class)
            ->assertSee('No open alerts. Quiet is good.');
    }

    #[Test]
    public function acknowledging_an_unknown_id_does_not_crash(): void
    {
        Livewire::test(AlertList::class)
            ->call('acknowledge', 'does-not-exist')
            ->assertSuccessful();
    }

    #[Test]
    public function acknowledgement_survives_a_fresh_request(): void
    {
        Livewire::test(AlertList::class)->call('acknowledge', 'al-001');

        Livewire::test(AlertList::class)
            ->assertSeeInOrder(['acknowledged', 'Acknowledge']);
    }
}

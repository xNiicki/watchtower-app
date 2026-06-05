<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Livewire\Apps\AppEvents;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AppEventsScreenTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function lists_events_for_an_app(): void
    {
        Livewire::test(AppEvents::class, ['slug' => 'booking'])
            ->assertOk()
            ->assertSee('TypeError')
            ->assertSee('412×')
            // Severity and type labels are shown so users can read why an item is red/amber.
            ->assertSee('Critical')
            ->assertSee('exception');
    }

    #[Test]
    public function search_filters_events(): void
    {
        Livewire::test(AppEvents::class, ['slug' => 'booking'])
            ->set('search', 'smtp')
            ->assertSee('SendInvoice')
            ->assertDontSee('TypeError');
    }

    #[Test]
    public function shows_no_events_recorded_for_unknown_slug(): void
    {
        Livewire::test(AppEvents::class, ['slug' => 'unknown'])
            ->assertOk()
            ->assertSee('No events recorded.')
            ->assertDontSee('TypeError');
    }

    #[Test]
    public function shows_slow_count_chips(): void
    {
        Livewire::test(AppEvents::class, ['slug' => 'booking'])
            ->assertSee('slow req')
            ->assertSee('slow query');
    }

    #[Test]
    public function shows_range_selector_and_links_events_to_detail(): void
    {
        Livewire::test(AppEvents::class, ['slug' => 'booking'])
            ->assertOk()
            ->assertSee('1h')->assertSee('6h')->assertSee('24h')
            ->assertSeeHtml('/apps/booking/events/1');
    }

    #[Test]
    public function set_range_updates_property_and_builds_chart_data(): void
    {
        Livewire::test(AppEvents::class, ['slug' => 'booking'])
            ->call('setRange', '6h')
            ->assertSet('range', '6h')
            ->assertDispatched('metrics-updated');
    }

    #[Test]
    public function set_range_ignores_invalid_values(): void
    {
        Livewire::test(AppEvents::class, ['slug' => 'booking'])
            ->call('setRange', 'evil')
            ->assertSet('range', '1h');
    }
}

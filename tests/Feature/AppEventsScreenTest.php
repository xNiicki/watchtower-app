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
            ->assertSee('412×');
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
    public function shows_metrics_summary(): void
    {
        Livewire::test(AppEvents::class, ['slug' => 'booking'])
            ->assertSee('req/min')
            ->assertSee('120');
    }
}

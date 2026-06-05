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
            ->assertSee('412');
    }

    #[Test]
    public function search_filters_events(): void
    {
        Livewire::test(AppEvents::class, ['slug' => 'booking'])
            ->set('search', 'smtp')
            ->assertSee('SendInvoice')
            ->assertDontSee('TypeError');
    }
}

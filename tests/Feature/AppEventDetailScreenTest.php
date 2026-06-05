<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Livewire\Apps\AppEventDetail;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AppEventDetailScreenTest extends TestCase
{
    #[Test]
    public function renders_trace_and_context_for_a_known_event(): void
    {
        Livewire::test(AppEventDetail::class, ['slug' => 'booking', 'id' => '1'])
            ->assertOk()
            ->assertSee('TypeError')
            ->assertSee('app/Services/Booking.php')
            ->assertSee('Booking->load()')
            ->assertSee('queue')
            ->assertSee('redis');
    }

    #[Test]
    public function shows_not_found_for_unknown_event(): void
    {
        Livewire::test(AppEventDetail::class, ['slug' => 'booking', 'id' => '999'])
            ->assertOk()
            ->assertSee('Event not found')
            ->assertDontSee('Booking->load()');
    }
}

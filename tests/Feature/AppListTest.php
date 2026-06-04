<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Livewire\Apps\AppList;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AppListTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function shows_booking_with_nightwatch_style_stats(): void
    {
        Livewire::test(AppList::class)
            ->assertSee('booking')
            ->assertSee('queue 3')
            ->assertSee('14 mails');
    }

    #[Test]
    public function shows_stale_state_and_last_seen(): void
    {
        Livewire::test(AppList::class)
            ->assertSee('stale')
            ->assertSee('last seen');
    }
}

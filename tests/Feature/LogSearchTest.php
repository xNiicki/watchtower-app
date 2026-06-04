<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Livewire\Logs\LogSearch;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class LogSearchTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function shows_recent_log_entries(): void
    {
        Livewire::test(LogSearch::class)
            ->assertSee('Aborted connection');
    }

    #[Test]
    public function filters_by_search_term(): void
    {
        Livewire::test(LogSearch::class)
            ->set('search', 'backup job')
            ->assertSee('booking-daily')
            ->assertDontSee('Aborted connection');
    }

    #[Test]
    public function filters_by_host(): void
    {
        Livewire::test(LogSearch::class)
            ->set('host', 'redis-prod')
            ->assertSee('Background saving')
            ->assertDontSee('Aborted connection');
    }

    #[Test]
    public function resetting_the_host_chip_to_all_shows_every_entry(): void
    {
        Livewire::test(LogSearch::class)
            ->set('host', 'redis-prod')
            ->assertDontSee('Aborted connection')
            ->set('host', '')
            ->assertSee('Aborted connection')
            ->assertSee('Background saving');
    }

    #[Test]
    public function search_and_host_filters_combine(): void
    {
        Livewire::test(LogSearch::class)
            ->set('host', 'mariadb-prod')
            ->set('search', 'InnoDB')
            ->assertSee('page cleaner')
            ->assertDontSee('Aborted connection');
    }

    #[Test]
    public function searching_for_the_string_zero_does_not_reset_the_filter(): void
    {
        Livewire::test(LogSearch::class)
            ->set('search', '0')
            ->assertSee('page cleaner')
            ->assertDontSee('Background saving');
    }
}

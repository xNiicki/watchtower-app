<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Livewire\Apps\AppList;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AppListScreenTest extends TestCase
{
    #[Test]
    public function shows_telemetry_degraded_badge_with_reason(): void
    {
        Livewire::test(AppList::class)
            ->assertOk()
            ->assertSee('telemetry degraded')
            ->assertSee('POST /api/ingest/event → 404');
    }
}

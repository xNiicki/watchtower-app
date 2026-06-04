<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class NavigationSmokeTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    #[DataProvider('routes')]
    public function every_app_route_renders_successfully(string $route): void
    {
        $this->get(route($route))->assertSuccessful();
    }

    public static function routes(): array
    {
        return [
            ['dashboard'],
            ['infra.index'],
            ['apps.index'],
            ['logs.index'],
            ['alerts.index'],
            ['settings'],
        ];
    }
}

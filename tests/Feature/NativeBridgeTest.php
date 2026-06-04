<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Support\NativeBridge;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class NativeBridgeTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function bridge_is_unavailable_in_the_test_environment(): void
    {
        // The Jump userland fallback defines nativephp_call(), but it is not the
        // C extension and no Jump bridge env vars are set in tests.
        $this->assertFalse(NativeBridge::available());
    }
}

<?php

declare(strict_types=1);

namespace App\Support;

use ReflectionFunction;

/**
 * Detects whether native bridge calls can actually reach a device.
 *
 * On-device, nativephp_call() is a C extension function. On dev machines the
 * nativephp/mobile package registers a userland TCP fallback (Jump hybrid mode)
 * that hangs when no device is connected — so function_exists() alone is not
 * a usable signal. We treat the bridge as available when the function comes
 * from the C extension, or when a Jump bridge is explicitly configured.
 */
class NativeBridge
{
    private static ?bool $available = null;

    public static function available(): bool
    {
        return self::$available ??= self::detect();
    }

    private static function detect(): bool
    {
        if (! function_exists('nativephp_call')) {
            return false;
        }

        if ((new ReflectionFunction('nativephp_call'))->getExtensionName() !== false) {
            return true; // C extension — running on a device
        }

        // Userland Jump fallback: only meaningful when native:jump configured a bridge.
        return env('JUMP_BRIDGE_PORT') !== null || env('JUMP_WS_PORT') !== null;
    }
}

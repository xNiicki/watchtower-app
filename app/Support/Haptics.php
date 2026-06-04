<?php

declare(strict_types=1);

namespace App\Support;

use Native\Mobile\Facades\Device;

/**
 * Bridge-guarded haptic feedback: vibrates on device, silently
 * no-ops when no native bridge is available.
 */
class Haptics
{
    public static function tap(): void
    {
        if (NativeBridge::available()) {
            Device::vibrate();
        }
    }
}

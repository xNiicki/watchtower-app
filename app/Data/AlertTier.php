<?php

declare(strict_types=1);

namespace App\Data;

enum AlertTier: string
{
    case Critical = 'critical';
    case Warning = 'warning';

    /** Left-border accent classes for alert cards. */
    public function borderClass(): string
    {
        return match ($this) {
            self::Critical => 'border-l-4 border-red-500',
            self::Warning => 'border-l-4 border-amber-500',
        };
    }
}

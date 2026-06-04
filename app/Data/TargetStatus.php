<?php

declare(strict_types=1);

namespace App\Data;

enum TargetStatus: string
{
    case Up = 'up';
    case Down = 'down';
    case Unknown = 'unknown';
    case Paused = 'paused';

    /** Status dot color class for target list items (color only; shape classes belong in the view). */
    public function dotColorClass(): string
    {
        return match ($this) {
            self::Up => 'bg-green-500',
            self::Down => 'bg-red-500',
            self::Paused => 'bg-zinc-600',
            self::Unknown => 'bg-amber-500',
        };
    }
}

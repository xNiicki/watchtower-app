<?php

declare(strict_types=1);

namespace App\Data;

use Carbon\CarbonImmutable;

final readonly class LogEntry
{
    public function __construct(
        public string $id,
        public string $host,
        public string $severity, // emerg|alert|crit|err|warning|notice|info|debug
        public string $message,
        public CarbonImmutable $loggedAt,
    ) {}

    /** Text color class for this entry's severity. */
    public function severityClass(): string
    {
        return match (true) {
            in_array($this->severity, ['emerg', 'alert', 'crit', 'err'], true) => 'text-red-400',
            $this->severity === 'warning' => 'text-amber-400',
            default => 'text-zinc-300',
        };
    }
}

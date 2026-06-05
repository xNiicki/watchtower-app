<?php

declare(strict_types=1);

namespace App\Data;

use Carbon\CarbonImmutable;

final readonly class AppEvent
{
    public function __construct(
        public string $id,
        public string $type,
        public string $severity,
        public string $title,
        public string $message,
        public int $occurrences,
        public CarbonImmutable $firstSeenAt,
        public CarbonImmutable $lastSeenAt,
    ) {}
}

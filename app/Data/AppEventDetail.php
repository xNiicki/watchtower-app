<?php

declare(strict_types=1);

namespace App\Data;

use Carbon\CarbonImmutable;

final readonly class AppEventDetail
{
    /** @param array<string, mixed> $context */
    public function __construct(
        public string $id,
        public string $type,
        public string $severity,
        public string $title,
        public string $message,
        public int $occurrences,
        public CarbonImmutable $firstSeenAt,
        public CarbonImmutable $lastSeenAt,
        public ?string $exceptionClass,
        public ?string $file,
        public ?int $line,
        public ?string $trace,
        public array $context,
    ) {}
}

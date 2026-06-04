<?php

declare(strict_types=1);

namespace App\Data;

use Carbon\CarbonImmutable;

final readonly class AppHealth
{
    public function __construct(
        public string $name,
        public bool $healthy,
        public int $errorsLastHour,
        public int $queueDepth,
        public int $failedJobs24h,
        public int $mailSent24h,
        public ?CarbonImmutable $lastDeployAt = null,
        public ?CarbonImmutable $lastSeenAt = null,
        public bool $stale = false,
    ) {}
}

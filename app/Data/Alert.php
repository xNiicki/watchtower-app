<?php

declare(strict_types=1);

namespace App\Data;

use Carbon\CarbonImmutable;

final readonly class Alert
{
    public function __construct(
        public string $id,
        public AlertTier $tier,
        public string $title,
        public string $message,
        public ?string $targetId,
        public CarbonImmutable $firedAt,
        public bool $acknowledged = false,
        public ?CarbonImmutable $resolvedAt = null,
    ) {}
}

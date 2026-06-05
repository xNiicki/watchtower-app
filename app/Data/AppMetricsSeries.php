<?php

declare(strict_types=1);

namespace App\Data;

use Carbon\CarbonImmutable;

final readonly class AppMetricsSeries
{
    /**
     * Each series is a time-ordered list of points.
     *
     * @param  array<int, array{at: CarbonImmutable, value: float}>  $requests
     * @param  array<int, array{at: CarbonImmutable, value: float}>  $latencyAvgMs
     * @param  array<int, array{at: CarbonImmutable, value: float}>  $latencyMaxMs
     */
    public function __construct(
        public string $range,
        public array $requests,
        public array $latencyAvgMs,
        public array $latencyMaxMs,
    ) {}
}

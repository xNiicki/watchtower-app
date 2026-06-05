<?php

declare(strict_types=1);

namespace App\Data;

final readonly class AppMetrics
{
    public function __construct(
        public int $requestsPerMin,
        public int $latencyAvgMs,
        public int $latencyMaxMs,
        public int $slowRequests,
        public int $slowQueries,
    ) {}
}

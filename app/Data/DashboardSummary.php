<?php

declare(strict_types=1);

namespace App\Data;

use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

final readonly class DashboardSummary
{
    /**
     * @param  Collection<int, Alert>  $openAlerts
     * @param  Collection<int, Target>  $nodes
     * @param  Collection<int, AppHealth>  $apps
     */
    public function __construct(
        public int $targetsUp,
        public int $targetsTotal,
        public int $targetsPaused,
        public Collection $openAlerts,
        public Collection $nodes,
        public Collection $apps,
        public ?CarbonImmutable $lastBackupAt,
        public bool $lastBackupOk,
        public float $tankUsagePercent,
    ) {}
}

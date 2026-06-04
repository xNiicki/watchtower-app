<?php

declare(strict_types=1);

namespace App\Data;

final readonly class Target
{
    public function __construct(
        public string $id,
        public string $name,
        public string $type, // lxc|vm|node|storage|service|app
        public TargetStatus $status,
        public ?string $node = null,
        public ?float $cpuPercent = null,
        public ?float $memPercent = null,
        public ?float $diskPercent = null,
        public ?int $latencyMs = null,
    ) {}
}

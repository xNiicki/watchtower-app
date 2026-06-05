<?php

declare(strict_types=1);

namespace App\Contracts;

use App\Data\Alert;
use App\Data\AppEvent;
use App\Data\AppHealth;
use App\Data\DashboardSummary;
use App\Data\LogEntry;
use App\Data\Target;
use Illuminate\Support\Collection;

interface HubClient
{
    public function summary(): DashboardSummary;

    /** @return Collection<int, Target> */
    public function targets(): Collection;

    /** @throws \InvalidArgumentException when the id is unknown */
    public function target(string $id): Target;

    /** @return Collection<int, Alert> active (non-resolved) alerts; acknowledged ones included with acknowledged=true */
    public function alerts(): Collection;

    /**
     * @return bool true when the hub accepted the acknowledgement
     *
     * @throws \RuntimeException on transport failure (implementations must not silently swallow network errors)
     */
    public function acknowledgeAlert(string $id): bool;

    /**
     * @param  array{host?: string, severity?: string, search?: string}  $filters
     * @return Collection<int, LogEntry>
     *
     * Filter shape will gain limit/start pagination keys in Plan E (backward-compatible).
     */
    public function logs(array $filters = []): Collection;

    /** @return Collection<int, AppHealth> */
    public function apps(): Collection;

    /**
     * @param  array{search?: string, limit?: int}  $filters
     * @return Collection<int, AppEvent>
     */
    public function appEvents(string $slug, array $filters = []): Collection;
}

<?php

declare(strict_types=1);

namespace App\Livewire\Apps;

use App\Contracts\HubClient;
use App\Data\AppMetricsSeries;
use App\Livewire\Concerns\InteractsWithHub;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.app')]
#[Title('App Events')]
class AppEvents extends Component
{
    use InteractsWithHub;

    #[Locked]
    public string $slug = '';

    public string $search = '';

    public string $range = '1h';

    /**
     * @var array{requestsPerMin: int, latencyAvgMs: int, latencyMaxMs: int, slowRequests: int, slowQueries: int}|null
     */
    #[Locked]
    public ?array $metrics = null;

    /**
     * Chart series shaped for Chart.js. Fetched in mount()/setRange() (not in
     * render()) so debounced search keystrokes don't re-hit the metrics endpoint;
     * the chart is independent of the search filter.
     *
     * @var array{labels: array<int, string>, requests: array<int, float>, latencyAvg: array<int, float>, latencyMax: array<int, float>}|null
     */
    #[Locked]
    public ?array $chart = null;

    public function mount(string $slug, HubClient $hub): void
    {
        $this->slug = $slug;

        $metrics = $this->hubData(fn () => $hub->appMetrics($this->slug));

        $this->metrics = $metrics === null ? null : [
            'requestsPerMin' => $metrics->requestsPerMin,
            'latencyAvgMs' => $metrics->latencyAvgMs,
            'latencyMaxMs' => $metrics->latencyMaxMs,
            'slowRequests' => $metrics->slowRequests,
            'slowQueries' => $metrics->slowQueries,
        ];

        $this->chart = $this->chartData($hub);
    }

    public function setRange(string $range, HubClient $hub): void
    {
        if (! in_array($range, ['1h', '6h', '24h'], true)) {
            return;
        }

        $this->range = $range;
        $this->chart = $this->chartData($hub);

        // The chart canvas is inside wire:ignore, so Livewire won't re-init the
        // Alpine component on re-render — push fresh data via a browser event instead.
        $this->dispatch('metrics-updated', chart: $this->chart);
    }

    public function render(HubClient $hub): View
    {
        $filters = $this->search !== '' ? ['search' => $this->search] : [];

        return view('livewire.apps.app-events', [
            'events' => $this->hubData(fn () => $hub->appEvents($this->slug, $filters)) ?? collect(),
            'chart' => $this->chart,
        ]);
    }

    /**
     * Shape the metric series into the plain arrays Chart.js consumes.
     *
     * @return array{labels: array<int, string>, requests: array<int, float>, latencyAvg: array<int, float>, latencyMax: array<int, float>}|null
     */
    private function chartData(HubClient $hub): ?array
    {
        /** @var AppMetricsSeries|null $series */
        $series = $this->hubData(fn () => $hub->appMetricsSeries($this->slug, $this->range));

        if ($series === null) {
            return null;
        }

        return [
            'labels' => array_map(fn (array $p) => $p['at']->format('H:i'), $series->requests),
            'requests' => array_map(fn (array $p) => $p['value'], $series->requests),
            'latencyAvg' => array_map(fn (array $p) => $p['value'], $series->latencyAvgMs),
            'latencyMax' => array_map(fn (array $p) => $p['value'], $series->latencyMaxMs),
        ];
    }
}

<?php

declare(strict_types=1);

namespace App\Livewire\Apps;

use App\Contracts\HubClient;
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

    /**
     * Metrics don't depend on the search filter, so they're fetched once in
     * mount() and carried across re-renders rather than re-hitting /metrics on
     * every debounced keystroke. Stored as a Livewire-serializable array shape
     * (custom value objects can't be dehydrated as public properties).
     *
     * @var array{requestsPerMin: int, latencyAvgMs: int, latencyMaxMs: int, slowRequests: int, slowQueries: int}|null
     */
    #[Locked]
    public ?array $metrics = null;

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
    }

    public function render(HubClient $hub): View
    {
        $filters = $this->search !== '' ? ['search' => $this->search] : [];

        return view('livewire.apps.app-events', [
            'events' => $this->hubData(fn () => $hub->appEvents($this->slug, $filters)) ?? collect(),
        ]);
    }
}

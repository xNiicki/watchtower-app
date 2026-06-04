<?php

declare(strict_types=1);

namespace App\Livewire\Logs;

use App\Contracts\HubClient;
use App\Livewire\Concerns\InteractsWithHub;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.app')]
#[Title('Logs')]
class LogSearch extends Component
{
    use InteractsWithHub;

    public string $search = '';

    public string $host = '';

    /** Distinct hosts for the filter chips; computed once per request. */
    #[Computed]
    public function hosts(): Collection
    {
        return ($this->hubData(fn () => app(HubClient::class)->logs()) ?? collect())
            ->pluck('host')->unique()->sort()->values();
    }

    public function render(HubClient $hub): View
    {
        $filters = array_filter(
            ['search' => $this->search, 'host' => $this->host],
            fn (string $v): bool => $v !== '',
        );

        return view('livewire.logs.log-search', [
            'entries' => $this->hubData(fn () => $hub->logs($filters)) ?? collect(),
        ]);
    }
}

<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Contracts\HubClient;
use App\Livewire\Concerns\InteractsWithHub;
use App\Support\Haptics;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.app')]
#[Title('Watchtower')]
class Dashboard extends Component
{
    use InteractsWithHub;

    public function refresh(): void
    {
        Haptics::tap();
        // Re-render pulls fresh data from HubClient in render().
    }

    public function render(HubClient $hub): View
    {
        $summary = $this->hubData(fn () => $hub->summary());

        return view('livewire.dashboard', [
            'summary' => $summary,
            // Acknowledged alerts drop off the dashboard ("what needs me now");
            // they remain visible on the Alerts tab, labelled acknowledged.
            'openAlerts' => ($summary?->openAlerts ?? collect())
                ->reject(fn ($alert) => $alert->acknowledged)
                ->values(),
        ]);
    }
}

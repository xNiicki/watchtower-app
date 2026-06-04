<?php

declare(strict_types=1);

namespace App\Livewire\Alerts;

use App\Contracts\HubClient;
use App\Data\AlertTier;
use App\Livewire\Concerns\InteractsWithHub;
use App\Support\Haptics;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use RuntimeException;

#[Layout('components.layouts.app')]
#[Title('Alerts')]
class AlertList extends Component
{
    use InteractsWithHub;

    public function acknowledge(HubClient $hub, string $alertId): void
    {
        try {
            $hub->acknowledgeAlert($alertId);
            Haptics::tap();
        } catch (RuntimeException) {
            // Hub unreachable: the alert stays un-acked on re-render.
            // HubUnreachableException extends RuntimeException, so it is caught here too.
        }
    }

    public function render(HubClient $hub): View
    {
        $alerts = $this->hubData(fn () => $hub->alerts()) ?? collect();

        return view('livewire.alerts.alert-list', [
            'alerts' => $alerts->sortBy(fn ($a) => $a->tier === AlertTier::Critical ? 0 : 1)->values(),
        ]);
    }
}

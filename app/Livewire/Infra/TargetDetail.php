<?php

declare(strict_types=1);

namespace App\Livewire\Infra;

use App\Contracts\HubClient;
use App\Exceptions\HubUnreachableException;
use App\Livewire\Concerns\InteractsWithHub;
use Illuminate\Contracts\View\View;
use InvalidArgumentException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.app')]
#[Title('Target')]
class TargetDetail extends Component
{
    use InteractsWithHub;

    #[Locked]
    public string $targetId;

    public function mount(string $targetId, HubClient $hub): void
    {
        $this->targetId = $targetId;

        try {
            $hub->target($targetId);
        } catch (HubUnreachableException $e) {
            // Hub down during mount: don't 404, render with hubError set.
            $this->hubError = $e->getMessage();
        } catch (InvalidArgumentException) {
            abort(404);
        }
    }

    public function render(HubClient $hub): View
    {
        $target = $this->hubData(fn () => $hub->target($this->targetId));
        $logs = $target !== null ? ($this->hubData(fn () => $hub->logs(['host' => $this->targetId])) ?? collect()) : collect();

        return view('livewire.infra.target-detail', [
            'target' => $target,
            'logs' => $logs,
        ]);
    }
}

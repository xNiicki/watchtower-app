<?php

declare(strict_types=1);

namespace App\Livewire\Infra;

use App\Contracts\HubClient;
use App\Livewire\Concerns\InteractsWithHub;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.app')]
#[Title('Infrastructure')]
class TargetList extends Component
{
    use InteractsWithHub;

    public function render(HubClient $hub): View
    {
        $targets = $this->hubData(fn () => $hub->targets()) ?? collect();

        $storageTargets = $targets->filter(fn ($t) => $t->type === 'storage')->values();
        $nonStorage = $targets->reject(fn ($t) => $t->type === 'storage');

        $groups = $nonStorage
            ->groupBy(fn ($t) => $t->node ?? $t->name)
            ->map(fn ($members, $key) => [
                'node' => $members->firstWhere(fn ($t) => $t->type === 'node' && $t->name === $key),
                'children' => $members->reject(fn ($t) => $t->type === 'node' && $t->name === $key)->values(),
            ]);

        return view('livewire.infra.target-list', [
            'groups' => $groups,
            'storageTargets' => $storageTargets,
        ]);
    }
}

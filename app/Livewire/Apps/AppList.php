<?php

declare(strict_types=1);

namespace App\Livewire\Apps;

use App\Contracts\HubClient;
use App\Livewire\Concerns\InteractsWithHub;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.app')]
#[Title('Applications')]
class AppList extends Component
{
    use InteractsWithHub;

    public function render(HubClient $hub): View
    {
        return view('livewire.apps.app-list', [
            'apps' => $this->hubData(fn () => $hub->apps()) ?? collect(),
        ]);
    }
}

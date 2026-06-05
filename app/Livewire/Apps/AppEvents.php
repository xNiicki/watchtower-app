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

    public function mount(string $slug): void
    {
        $this->slug = $slug;
    }

    public function render(HubClient $hub): View
    {
        $filters = $this->search !== '' ? ['search' => $this->search] : [];

        return view('livewire.apps.app-events', [
            'events' => $this->hubData(fn () => $hub->appEvents($this->slug, $filters)) ?? collect(),
        ]);
    }
}

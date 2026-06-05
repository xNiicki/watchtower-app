<?php

declare(strict_types=1);

namespace App\Livewire\Apps;

use App\Contracts\HubClient;
use App\Data\AppEventDetail as AppEventDetailData;
use App\Livewire\Concerns\InteractsWithHub;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.app')]
#[Title('Event')]
class AppEventDetail extends Component
{
    use InteractsWithHub;

    #[Locked]
    public string $slug = '';

    #[Locked]
    public string $id = '';

    public function mount(string $slug, string $id): void
    {
        $this->slug = $slug;
        $this->id = $id;
    }

    public function render(HubClient $hub): View
    {
        /** @var AppEventDetailData|null $event */
        $event = $this->hubData(fn () => $hub->appEvent($this->slug, $this->id));

        return view('livewire.apps.app-event-detail', ['event' => $event]);
    }
}

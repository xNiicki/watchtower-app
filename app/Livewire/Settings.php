<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Contracts\TokenStore;
use App\Support\Haptics;
use App\Support\NativeBridge;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Native\Mobile\Facades\Network;

#[Layout('components.layouts.app')]
#[Title('Settings')]
class Settings extends Component
{
    public string $localUrl = '';

    public string $remoteUrl = '';

    public string $token = '';

    public function mount(TokenStore $store): void
    {
        $this->localUrl = $store->get('hub.local_url') ?? '';
        $this->remoteUrl = $store->get('hub.remote_url') ?? '';
        $this->token = $store->get('hub.token') ?? '';
    }

    public function save(TokenStore $store): void
    {
        $this->validate([
            'localUrl' => ['nullable', 'url'],
            'remoteUrl' => ['nullable', 'url'],
            'token' => ['nullable', 'string', 'max:255'],
        ]);

        foreach (['hub.local_url' => $this->localUrl, 'hub.remote_url' => $this->remoteUrl, 'hub.token' => $this->token] as $key => $value) {
            $value === '' ? $store->forget($key) : $store->set($key, $value);
        }

        Haptics::tap();
        $this->dispatch('settings-saved');
    }

    /** @return array<string, string> */
    protected function validationAttributes(): array
    {
        return [
            'localUrl' => 'hub URL (home)',
            'remoteUrl' => 'hub URL (remote)',
        ];
    }

    public function render(): View
    {
        return view('livewire.settings', [
            'network' => $this->networkStatus(),
        ]);
    }

    /** @return object|null status object with connected/type, or null when the bridge is unavailable */
    private function networkStatus(): ?object
    {
        if (! NativeBridge::available()) {
            return null;
        }

        $status = Network::status();

        // The Jump bridge can return an error-shaped object when the device
        // disconnects mid-session. Treat anything missing the expected shape as unavailable.
        if ($status === null || ! property_exists($status, 'connected') || ! property_exists($status, 'type')) {
            return null;
        }

        return $status;
    }
}

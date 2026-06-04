<div class="space-y-6">
    <form wire:submit="save" class="space-y-4">
        <div>
            <label for="localUrl" class="text-sm text-zinc-400">Hub URL (home network)</label>
            <input id="localUrl" wire:model="localUrl" type="url" placeholder="http://192.168.1.50"
                class="mt-1 w-full rounded-lg border border-zinc-700 bg-zinc-900 px-3 py-2">
            @error('localUrl') <p class="mt-1 text-sm text-red-400">{{ $message }}</p> @enderror
        </div>
        <div>
            <label for="remoteUrl" class="text-sm text-zinc-400">Hub URL (remote / VPS)</label>
            <input id="remoteUrl" wire:model="remoteUrl" type="url" placeholder="https://watchtower.example.net"
                class="mt-1 w-full rounded-lg border border-zinc-700 bg-zinc-900 px-3 py-2">
            @error('remoteUrl') <p class="mt-1 text-sm text-red-400">{{ $message }}</p> @enderror
        </div>
        <div>
            <label for="token" class="text-sm text-zinc-400">API token</label>
            <input id="token" wire:model="token" type="password" autocomplete="off"
                class="mt-1 w-full rounded-lg border border-zinc-700 bg-zinc-900 px-3 py-2">
            @error('token') <p class="mt-1 text-sm text-red-400">{{ $message }}</p> @enderror
        </div>
        <button type="submit" class="w-full rounded-lg bg-blue-600 py-2.5 font-medium">Save</button>
        <p x-data="{ shown: false }" x-on:settings-saved.window="shown = true; setTimeout(() => shown = false, 2000)"
           x-show="shown" x-cloak class="text-center text-sm text-green-400">Saved.</p>
    </form>

    <div class="rounded-lg border border-zinc-800 p-3 text-sm">
        <p class="text-zinc-400">Network</p>
        @if ($network === null)
            <p>Bridge unavailable (browser/test run)</p>
        @else
            <p>{{ $network->connected ? 'Connected' : 'Offline' }} — {{ $network->type }}</p>
        @endif
    </div>
</div>

<div class="space-y-3">
    @include('partials.hub-error')

    @forelse ($alerts as $alert)
        <div class="rounded-xl bg-zinc-900 p-4 {{ $alert->tier->borderClass() }}">
            <p class="font-medium">{{ $alert->title }}</p>
            <p class="mt-1 text-sm text-zinc-400">{{ $alert->message }}</p>
            <div class="mt-2 flex items-center justify-between text-sm">
                <span class="text-zinc-500">{{ $alert->firedAt->diffForHumans(short: true) }}</span>
                @if ($alert->acknowledged)
                    <span class="text-zinc-500">acknowledged</span>
                @else
                    <button wire:click="acknowledge({{ Js::from($alert->id) }})" class="text-blue-400">Acknowledge</button>
                @endif
            </div>
        </div>
    @empty
        <p class="py-12 text-center text-zinc-500">No open alerts. Quiet is good.</p>
    @endforelse
</div>

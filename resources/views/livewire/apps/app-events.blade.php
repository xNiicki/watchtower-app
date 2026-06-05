<div class="space-y-3">
    @include('partials.hub-error')

    <input type="search" wire:model.live.debounce.300ms="search" placeholder="Search messages…"
           class="w-full rounded-lg border border-zinc-700 bg-zinc-900 px-3 py-2 text-sm text-zinc-100 placeholder-zinc-500" />

    @forelse ($events as $event)
        <div class="rounded-xl bg-zinc-900 p-4">
            <div class="flex items-baseline justify-between">
                <p class="font-medium">{{ $event->title }}</p>
                <p class="text-sm {{ $event->severity === 'critical' ? 'text-red-400' : 'text-amber-400' }}">
                    {{ $event->type }} · {{ $event->occurrences }}×
                </p>
            </div>
            <p class="mt-1 text-sm text-zinc-400">{{ $event->message }}</p>
            <p class="mt-2 text-xs text-zinc-500">last seen {{ $event->lastSeenAt->diffForHumans() }}</p>
        </div>
    @empty
        @if ($search !== '')
            <p class="py-12 text-center text-zinc-500">No matching events.</p>
        @else
            <p class="py-12 text-center text-zinc-500">No events recorded.</p>
        @endif
    @endforelse
</div>

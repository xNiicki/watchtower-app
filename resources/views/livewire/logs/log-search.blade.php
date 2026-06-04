<div class="space-y-3">
    @include('partials.hub-error')

    <input wire:model.live.debounce.300ms="search" type="search" placeholder="Search logs…"
        class="w-full rounded-lg border border-zinc-700 bg-zinc-900 px-3 py-2">

    <div class="flex gap-2 overflow-x-auto pb-1">
        <button wire:click="$set('host', '')"
            class="shrink-0 rounded-full px-3 py-1 text-sm {{ $host === '' ? 'bg-blue-600' : 'bg-zinc-800 text-zinc-400' }}">all</button>
        @foreach ($this->hosts as $h)
            <button wire:click="$set('host', {{ Js::from($h) }})"
                class="shrink-0 rounded-full px-3 py-1 text-sm {{ $host === $h ? 'bg-blue-600' : 'bg-zinc-800 text-zinc-400' }}">{{ $h }}</button>
        @endforeach
    </div>

    <div class="space-y-1.5">
        @forelse ($entries as $entry)
            <div class="rounded-lg bg-zinc-900 px-3 py-2 text-sm">
                <div class="flex items-baseline justify-between text-xs text-zinc-500">
                    <span>{{ $entry->host }}</span>
                    <span>{{ $entry->loggedAt->format('H:i') }}</span>
                </div>
                <p class="mt-0.5 {{ $entry->severityClass() }}">
                    {{ $entry->message }}
                </p>
            </div>
        @empty
            @if ($this->hosts->isEmpty())
                <p class="py-12 text-center text-zinc-500">No logs received yet.</p>
            @else
                <p class="py-12 text-center text-zinc-500">No matching entries.</p>
            @endif
        @endforelse
    </div>
</div>

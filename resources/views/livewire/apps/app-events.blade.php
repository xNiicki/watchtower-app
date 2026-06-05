<div class="space-y-3">
    @include('partials.hub-error')

    {{-- Range selector --}}
    <div class="flex gap-1">
        @foreach (['1h', '6h', '24h'] as $r)
            <button wire:click="setRange('{{ $r }}')"
                    class="flex-1 rounded-lg px-3 py-1.5 text-sm {{ $range === $r ? 'bg-zinc-700 text-zinc-100' : 'bg-zinc-900 text-zinc-400' }}">
                {{ $r }}
            </button>
        @endforeach
    </div>

    {{-- Chart island: wire:ignore so Livewire never destroys the Chart.js canvas --}}
    @if ($chart)
        <div wire:ignore x-data="metricsChart(@js($chart))" class="space-y-3">
            <div class="rounded-xl bg-zinc-900 p-3">
                <p class="text-xs text-zinc-400">Requests / min</p>
                <canvas x-ref="requests" class="mt-2" height="120"></canvas>
            </div>
            <div class="rounded-xl bg-zinc-900 p-3">
                <p class="text-xs text-zinc-400">Latency (avg / max ms)</p>
                <canvas x-ref="latency" class="mt-2" height="120"></canvas>
            </div>
        </div>
    @endif

    @if ($metrics)
        <div class="flex gap-2">
            <div class="flex-1 rounded-lg bg-zinc-900 p-2 text-sm text-zinc-300">{{ $metrics['slowRequests'] }} slow req</div>
            <div class="flex-1 rounded-lg bg-zinc-900 p-2 text-sm text-zinc-300">{{ $metrics['slowQueries'] }} slow query</div>
        </div>
    @endif

    <input type="search" wire:model.live.debounce.300ms="search" placeholder="Search messages…"
           class="w-full rounded-lg border border-zinc-700 bg-zinc-900 px-3 py-2 text-sm text-zinc-100 placeholder-zinc-500" />

    <p class="text-xs uppercase tracking-wide text-zinc-500">Recent events</p>

    @forelse ($events as $event)
        <a href="{{ route('apps.event', ['slug' => $slug, 'id' => $event->id]) }}" wire:navigate
           class="block rounded-xl bg-zinc-900 p-4">
            <div class="flex items-baseline justify-between">
                <p class="font-medium">{{ $event->title }}</p>
                <p class="text-sm {{ $event->severity === 'critical' ? 'text-red-400' : 'text-amber-400' }}">
                    {{ ucfirst($event->severity) }} · {{ $event->type }} · {{ $event->occurrences }}×
                </p>
            </div>
            <p class="mt-1 text-sm text-zinc-400">{{ $event->message }}</p>
            <p class="mt-2 text-xs text-zinc-500">last seen {{ $event->lastSeenAt->diffForHumans() }}</p>
        </a>
    @empty
        @if ($search !== '')
            <p class="py-12 text-center text-zinc-500">No matching events.</p>
        @else
            <p class="py-12 text-center text-zinc-500">No events recorded.</p>
        @endif
    @endforelse
</div>

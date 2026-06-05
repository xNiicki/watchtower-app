<div class="space-y-4">
    @include('partials.hub-error')

    @if ($event)
        <div>
            <div class="flex items-baseline justify-between">
                <h1 class="text-lg font-semibold">{{ $event->title }}</h1>
                <span class="text-xs {{ $event->severity === 'critical' ? 'text-red-400' : 'text-amber-400' }}">
                    {{ strtoupper($event->severity) }}
                </span>
            </div>
            <p class="mt-1 text-xs text-zinc-400">
                {{ $event->type }} · {{ $event->occurrences }}× · first seen {{ $event->firstSeenAt->diffForHumans() }} · last seen {{ $event->lastSeenAt->diffForHumans() }}
            </p>
        </div>

        <div class="rounded-xl bg-zinc-900 p-3">
            <p class="text-sm text-zinc-200">{{ $event->message }}</p>
            @if ($event->file)
                <p class="mt-1 font-mono text-xs text-zinc-400">{{ $event->file }}{{ $event->line ? ':'.$event->line : '' }}</p>
            @endif
        </div>

        @if ($event->trace)
            <div x-data="{ open: true }">
                <button @click="open = !open" class="flex w-full items-center justify-between text-xs uppercase tracking-wide text-zinc-400">
                    <span>Stack trace</span>
                    <span x-text="open ? 'Hide ▾' : 'Show ▸'" class="text-blue-400"></span>
                </button>
                <pre x-show="open" class="mt-2 overflow-x-auto rounded-xl bg-zinc-900 p-3 font-mono text-xs leading-relaxed text-zinc-300">{{ $event->trace }}</pre>
            </div>
        @endif

        @if (! empty($event->context))
            <div>
                <p class="text-xs uppercase tracking-wide text-zinc-400">Context</p>
                <div class="mt-2 rounded-xl bg-zinc-900 p-3 font-mono text-xs text-zinc-300">
                    @foreach ($event->context as $key => $value)
                        <div class="flex justify-between gap-4">
                            <span class="text-zinc-500">{{ $key }}</span>
                            <span class="text-right">{{ is_scalar($value) ? $value : json_encode($value) }}</span>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif
    @else
        <p class="py-12 text-center text-zinc-500">Event not found.</p>
    @endif
</div>

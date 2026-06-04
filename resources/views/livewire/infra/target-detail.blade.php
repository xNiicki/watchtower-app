<div class="space-y-4">
    @include('partials.hub-error')

    @if ($target !== null)
        <div class="rounded-xl bg-zinc-900 p-4">
            <p class="text-lg font-semibold">{{ $target->name }}</p>
            <p class="text-sm text-zinc-500">{{ $target->type }}@if($target->node) on {{ $target->node }}@endif · {{ $target->status->value }}</p>

            @if ($target->type === 'storage')
                @php $diskPercent = $target->diskPercent ?? 0; @endphp
                <div class="mt-3">
                    <div class="mb-1 flex items-baseline justify-between text-sm">
                        <span class="text-zinc-400">Disk usage</span>
                        <span class="font-semibold">{{ number_format($diskPercent) }}%</span>
                    </div>
                    <div class="h-2 w-full overflow-hidden rounded-full bg-zinc-700">
                        <div class="h-2 rounded-full bg-zinc-400" style="width: {{ $diskPercent }}%"></div>
                    </div>
                </div>
            @else
                <dl class="mt-3 grid grid-cols-3 gap-2 text-center text-sm">
                    @foreach (['cpu' => $target->cpuPercent, 'mem' => $target->memPercent, 'disk' => $target->diskPercent] as $label => $value)
                        <div class="rounded-lg bg-zinc-800 py-2">
                            <dt class="text-zinc-500">{{ $label }}</dt>
                            <dd class="font-medium">{{ $value !== null ? number_format($value).'%' : '—' }}</dd>
                        </div>
                    @endforeach
                </dl>
            @endif
        </div>

        <div>
            <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-zinc-500">Recent logs</p>
            <div class="space-y-1.5">
                @forelse ($logs as $entry)
                    <div class="rounded-lg bg-zinc-900 px-3 py-2 text-sm">
                        <span class="text-zinc-500">{{ $entry->loggedAt->format('H:i') }}</span>
                        <span class="ml-1 {{ $entry->severityClass() }}">{{ $entry->severity }}</span>
                        <span class="ml-1 text-zinc-300">{{ $entry->message }}</span>
                    </div>
                @empty
                    <p class="text-sm text-zinc-500">No log entries for this host.</p>
                @endforelse
            </div>
        </div>
    @endif
</div>

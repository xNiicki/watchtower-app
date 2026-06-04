<div class="space-y-5">
    @include('partials.hub-error')

    @forelse ($groups as $groupName => $group)
        <div>
            @if ($group['node'])
                <a href="{{ route('infra.show', $group['node']->id) }}" wire:navigate
                   class="mb-2 flex items-baseline justify-between">
                    <span class="text-xs font-semibold uppercase tracking-wide text-zinc-500">{{ $groupName }}</span>
                    <span class="text-xs text-zinc-500">mem {{ number_format($group['node']->memPercent ?? 0) }}%</span>
                </a>
            @else
                <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-zinc-500">{{ $groupName }}</p>
            @endif
            <div class="space-y-2">
                @foreach ($group['children'] as $target)
                    <a href="{{ route('infra.show', $target->id) }}" wire:navigate
                       class="flex items-center justify-between rounded-xl bg-zinc-900 px-4 py-3">
                        <span class="flex items-center gap-2">
                            <span class="inline-block h-2.5 w-2.5 rounded-full {{ $target->status->dotColorClass() }}"></span>
                            <span class="font-medium">{{ $target->name }}</span>
                            <span class="text-xs text-zinc-500">{{ $target->type }}</span>
                        </span>
                        @if ($target->memPercent !== null)
                            <span class="text-sm text-zinc-400">{{ number_format($target->memPercent) }}%</span>
                        @endif
                    </a>
                @endforeach
            </div>
        </div>
    @empty
        <p class="py-12 text-center text-zinc-500">No targets reported yet.</p>
    @endforelse

    @if ($storageTargets->isNotEmpty())
        <div>
            <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-zinc-500">Storage</p>
            <div class="space-y-2">
                @foreach ($storageTargets as $target)
                    <a href="{{ route('infra.show', $target->id) }}" wire:navigate
                       class="flex items-center justify-between rounded-xl bg-zinc-900 px-4 py-3">
                        <span class="flex items-center gap-2">
                            <span class="inline-block h-2.5 w-2.5 rounded-full {{ $target->status->dotColorClass() }}"></span>
                            <span class="font-medium">{{ $target->name }}</span>
                        </span>
                        @if ($target->diskPercent !== null)
                            <span class="text-sm text-zinc-400">{{ number_format($target->diskPercent) }}%</span>
                        @endif
                    </a>
                @endforeach
            </div>
        </div>
    @endif
</div>

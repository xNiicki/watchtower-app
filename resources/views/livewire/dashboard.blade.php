{{-- wire:poll re-renders without calling refresh(); haptics are intentionally button-only --}}
<div class="space-y-3" wire:poll.30s>
    @include('partials.hub-error')

    @if ($hubError ?? false)
        <p class="text-sm text-zinc-400">Configure your hub endpoints in
            <a href="{{ route('settings') }}" class="text-blue-400">Settings</a> to get started.
        </p>
    @else
        {{-- Fleet state --}}
        <div class="rounded-xl bg-zinc-900 p-4">
            @if ($openAlerts->where('tier', \App\Data\AlertTier::Critical)->isEmpty())
                <p class="font-semibold text-green-400">● All systems go</p>
            @else
                <p class="font-semibold text-red-400">● Attention needed</p>
            @endif
            <p class="mt-1 text-sm text-zinc-400">
                {{ $summary->targetsUp }}/{{ $summary->targetsTotal }} up · {{ $summary->targetsPaused }} paused
            </p>
        </div>

        {{-- Open alerts --}}
        @foreach ($openAlerts as $alert)
            <a href="{{ route('alerts.index') }}" wire:navigate
               class="block rounded-xl bg-zinc-900 p-4 {{ $alert->tier->borderClass() }}">
                <p class="font-medium">{{ $alert->title }}</p>
                <p class="mt-0.5 text-sm text-zinc-400">{{ $alert->firedAt->diffForHumans(short: true) }}</p>
            </a>
        @endforeach

        {{-- Nodes --}}
        @foreach ($summary->nodes as $node)
            <div class="rounded-xl bg-zinc-900 p-4">
                <div class="flex items-baseline justify-between">
                    <p class="font-medium">{{ $node->name }}</p>
                    <p class="text-sm text-zinc-400">cpu {{ number_format($node->cpuPercent ?? 0) }}% · mem {{ number_format($node->memPercent ?? 0) }}%</p>
                </div>
                <div class="mt-2 h-1.5 overflow-hidden rounded bg-zinc-800">
                    <div class="h-full bg-blue-500" style="width: {{ min(100, max(0, $node->memPercent ?? 0)) }}%"></div>
                </div>
            </div>
        @endforeach

        {{-- Apps --}}
        @foreach ($summary->apps as $app)
            <a href="{{ route('apps.index') }}" wire:navigate class="block rounded-xl bg-zinc-900 p-4">
                <div class="flex items-baseline justify-between">
                    <p class="font-medium">{{ $app->name }}</p>
                    <p class="text-sm {{ $app->healthy ? 'text-green-400' : 'text-red-400' }}">
                        {{ $app->healthy ? 'healthy' : 'unhealthy' }}
                    </p>
                </div>
                <p class="mt-1 text-sm text-zinc-400">{{ $app->errorsLastHour }} err/h · queue {{ $app->queueDepth }}</p>
            </a>
        @endforeach

        {{-- Backups --}}
        <div class="rounded-xl bg-zinc-900 p-4">
            <div class="flex items-baseline justify-between">
                <p class="font-medium">backups</p>
                <p class="text-sm {{ $summary->lastBackupOk ? 'text-green-400' : 'text-red-400' }}">
                    {{ $summary->lastBackupOk ? '✓' : '✗' }} {{ $summary->lastBackupAt?->format('H:i') ?? 'never' }}
                </p>
            </div>
            <p class="mt-1 text-sm text-zinc-400">tank {{ number_format($summary->tankUsagePercent) }}%</p>
        </div>

        <button wire:click="refresh" class="w-full rounded-lg border border-zinc-800 py-2 text-sm text-zinc-400">
            Refresh
        </button>
    @endif
</div>

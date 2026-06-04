<div class="space-y-3">
    @include('partials.hub-error')

    @forelse ($apps as $app)
        <div class="rounded-xl bg-zinc-900 p-4 {{ $app->stale ? 'opacity-60' : '' }}">
            <div class="flex items-baseline justify-between">
                <p class="font-medium">{{ $app->name }}</p>
                @if ($app->stale)
                    <p class="text-sm text-amber-400">stale</p>
                @else
                    <p class="text-sm {{ $app->healthy ? 'text-green-400' : 'text-red-400' }}">{{ $app->healthy ? 'healthy' : 'unhealthy' }}</p>
                @endif
            </div>
            <div class="mt-3 grid grid-cols-2 gap-2 text-sm text-zinc-400">
                <div>{{ $app->errorsLastHour }} errors last hour</div>
                <div>queue {{ $app->queueDepth }}</div>
                <div>{{ $app->failedJobs24h }} failed jobs 24h</div>
                <div>{{ $app->mailSent24h }} mails 24h</div>
            </div>
            <div class="mt-2 flex items-center justify-between text-xs text-zinc-500">
                @if ($app->lastDeployAt)
                    <span>deployed {{ $app->lastDeployAt->diffForHumans() }}</span>
                @else
                    <span></span>
                @endif
                @if ($app->lastSeenAt)
                    <span>last seen {{ $app->lastSeenAt->diffForHumans() }}</span>
                @endif
            </div>
        </div>
    @empty
        <p class="py-12 text-center text-zinc-500">No applications configured.</p>
    @endforelse
</div>

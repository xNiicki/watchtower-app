<div class="space-y-3">
    @include('partials.hub-error')

    @forelse ($apps as $app)
        <div class="rounded-xl bg-zinc-900 p-4">
            <div class="flex items-baseline justify-between">
                <p class="font-medium">{{ $app->name }}</p>
                <p class="text-sm {{ $app->healthy ? 'text-green-400' : 'text-red-400' }}">{{ $app->healthy ? 'healthy' : 'unhealthy' }}</p>
            </div>
            <div class="mt-3 grid grid-cols-2 gap-2 text-sm text-zinc-400">
                <div>{{ $app->errorsLastHour }} errors last hour</div>
                <div>queue {{ $app->queueDepth }}</div>
                <div>{{ $app->failedJobs24h }} failed jobs 24h</div>
                <div>{{ $app->mailSent24h }} mails 24h</div>
            </div>
            @if ($app->lastDeployAt)
                <p class="mt-2 text-xs text-zinc-500">deployed {{ $app->lastDeployAt->diffForHumans() }}</p>
            @endif
        </div>
    @empty
        <p class="py-12 text-center text-zinc-500">No applications configured.</p>
    @endforelse
</div>

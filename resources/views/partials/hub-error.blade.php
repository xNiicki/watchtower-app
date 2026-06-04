@if ($hubError ?? false)
    <div class="mb-4 rounded-xl border border-zinc-700 bg-zinc-900 px-4 py-3 text-sm">
        <p class="font-medium text-zinc-200">Hub unreachable &mdash; {{ $hubError }}</p>
        <a href="{{ route('settings') }}" class="mt-1 inline-block text-blue-400">Open Settings</a>
    </div>
@endif

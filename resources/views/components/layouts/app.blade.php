<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <title>{{ $title ?? 'Watchtower' }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="nativephp-safe-area bg-zinc-950 text-zinc-100 antialiased">
    {{-- Sub-screens get a back chevron (EDGE's leading nav icon opens a sidebar, which
         we don't use, so "back" is a trailing action that navigates to the parent). --}}
    @php($backUrl = match (true) {
        request()->routeIs('infra.show') => route('infra.index'),
        request()->routeIs('apps.event') => route('apps.events', ['slug' => request()->route('slug')]),
        default => null,
    })
    <native:top-bar title="{{ $title ?? 'Watchtower' }}" :show-navigation-icon="false">
        @isset($backUrl)
            <native:top-bar-action id="back" label="Back"
                icon="{{ \Native\Mobile\Facades\System::isIos() ? 'chevron.backward' : 'arrow_back' }}"
                :url="$backUrl" />
        @endisset
        @unless(request()->routeIs('settings'))
            <native:top-bar-action id="settings" icon="settings" label="Settings" :url="route('settings')" />
        @endunless
    </native:top-bar>

    {{-- The webview is full-screen UNDER the native bars (NativePHP EDGE renders them
         natively on top). The `nativephp-safe-area` body class already clears the notch
         via --inset-top, so main only needs to clear the top-bar's own height. --}}
    <main class="px-4 pt-16 pb-24">
        {{ $slot }}
    </main>

    <native:bottom-nav label-visibility="labeled">
        <native:bottom-nav-item id="tab-dash" icon="home" label="Dash"
            :url="route('dashboard')" :active="request()->routeIs('dashboard*')" />
        <native:bottom-nav-item id="tab-infra" icon="dashboard" label="Infra"
            :url="route('infra.index')" :active="request()->routeIs('infra.*')" />
        <native:bottom-nav-item id="tab-apps" icon="folder" label="Apps"
            :url="route('apps.index')" :active="request()->routeIs('apps.*')" />
        <native:bottom-nav-item id="tab-logs" icon="file" label="Logs"
            :url="route('logs.index')" :active="request()->routeIs('logs.*')" />
        <native:bottom-nav-item id="tab-alerts" icon="notifications" label="Alerts"
            :url="route('alerts.index')" :active="request()->routeIs('alerts.*')" />
    </native:bottom-nav>
</body>
</html>

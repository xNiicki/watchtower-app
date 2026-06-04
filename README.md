# Watchtower (iOS app)

A native iOS app — built with [NativePHP for Mobile](https://nativephp.com/docs/mobile/3) v3 (PHP + Livewire) — to monitor your homelab / dev infrastructure from your phone. It pairs with the self-hosted **Watchtower Hub** (separate repo) which collects and exposes the data over a read-only API.

NativePHP runs a full PHP runtime on the device with on-device SQLite — there is no web server. The whole UI is Livewire 4 full-page components rendered inside EDGE native navigation chrome.

## Features

- **Dashboard** — fleet health at a glance: targets up/total/paused, open alerts, nodes, last backup status, and storage usage.
- **Infrastructure** — list of all monitored targets (nodes, VMs, LXC containers, storage) with a detail screen per target.
- **Apps** — per-app health (queue depth, failed jobs, errors, mail sent, last deploy).
- **Alerts** — open alerts by tier, with acknowledge.
- **Logs** — searchable log view, filterable by host and severity.
- **Settings** — store the hub URL and API token. Supports a home-network (LAN) URL with a remote / VPS fallback; the app probes the LAN endpoint first and falls back automatically when off-network.
- **Native navigation** — EDGE `<native:top-bar>` + `<native:bottom-nav>` for a true native feel.

### Roadmap

- **Native push** via APNs (requires a paid Apple Developer account).
- **Face-ID-gated write actions** for any future mutating operations.

The app communicates with the hub over a **read-only API**. The only secret it holds is a revocable API token, kept in device-local storage.

## Architecture

All hub data flows through a single `App\Contracts\HubClient` interface, with two implementations:

- `FakeHubClient` — fixture-backed (realistic homelab-style demo data). Used for local development and the test suite, so the app runs and renders out of the box without a hub.
- `HttpHubClient` — talks to the real Watchtower Hub over its read-only API.

The active implementation is chosen by the `WATCHTOWER_HUB_DRIVER` env var (`http` or `fake`). Endpoints and the API token live behind `App\Contracts\TokenStore` (SQLite-backed on device); a Keychain-backed implementation can drop in later behind the same contract. `EndpointResolver` handles LAN-vs-remote selection by probing the configured hub.

Because screens only depend on the `HubClient` contract, you can develop and run the entire UI against fixtures (`WATCHTOWER_HUB_DRIVER=fake`) with no hub running.

## Requirements

- macOS with Xcode (for building / running on a simulator or device)
- PHP 8.3+
- A running, reachable **Watchtower Hub** (only needed when using the `http` driver against real data)

The free NativePHP plugins (`nativephp/mobile`, `nativephp/mobile-device`, `nativephp/mobile-network`) are already declared in `composer.json` and installed via `composer install`.

## Build & run

Install dependencies:

```bash
composer install
npm install
```

Then build the frontend assets and run on iOS (run these in your terminal):

```bash
npm run build -- --mode=ios
php artisan native:run ios
```

A **free** Apple account is enough for on-device development (apps re-sign / expire every 7 days). A **paid** Apple Developer account is required for App Store distribution and for APNs push notifications.

To explore the UI without a hub, set `WATCHTOWER_HUB_DRIVER=fake` and the app will render the bundled fixture data.

## Testing

```bash
php artisan test --compact
```

## License

Licensed under the **AGPL-3.0**. See [LICENSE](LICENSE).

<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\TokenStore;
use Illuminate\Http\Client\HttpClientException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * Picks the hub base URL: probe the LAN address's /api/v1/ping for the
 * watchtower-hub fingerprint with a short timeout, fall back to the VPS
 * route. Result memoized per instance (one probe per request) AND cached
 * cross-request so repeated page loads skip the probe round-trip entirely.
 */
class EndpointResolver
{
    // 1s, not 300ms: the first LAN round-trip (ARP+DNS+TCP, possibly TLS) routinely
    // exceeds 300ms, which would silently route everything via the VPS fallback.
    private const PROBE_TIMEOUT_SECONDS = 1.0;

    /** Shared cache key for the resolved base URL. */
    private const CACHE_KEY = 'hub.resolved_base_url';

    /** How long (seconds) the resolved URL is trusted without re-probing. */
    private const RESOLVE_CACHE_SECONDS = 60;

    private ?string $resolved = null;

    private bool $hasResolved = false;

    public function __construct(private readonly TokenStore $tokens) {}

    public function baseUrl(): ?string
    {
        // Per-instance memo: free on a cache hit within the same request.
        if ($this->hasResolved) {
            return $this->resolved;
        }

        // Cross-request cache: skip the probe when a recent result is stored.
        $cached = Cache::get(self::CACHE_KEY);

        if ($cached !== null) {
            $this->resolved = $cached;
            $this->hasResolved = true;

            return $this->resolved;
        }

        // Probe required — resolve and store if non-null.
        $local = $this->tokens->get('hub.local_url');
        $remote = $this->tokens->get('hub.remote_url');

        $this->resolved = match (true) {
            $local !== null && $this->isReachable($local) => $local,
            $remote !== null => $remote,
            default => null, // unconfigured, or local-only and unreachable
        };

        $this->hasResolved = true;

        // Never cache null: an unconfigured/unreachable state should keep retrying.
        if ($this->resolved !== null) {
            Cache::put(self::CACHE_KEY, $this->resolved, self::RESOLVE_CACHE_SECONDS);
        }

        return $this->resolved;
    }

    /**
     * Clears both the cross-request cache entry and the per-instance memo so
     * the next baseUrl() call performs a fresh probe. Call this after a transport
     * failure to handle LAN→VPS transitions without waiting for the TTL.
     */
    public function forget(): void
    {
        Cache::forget(self::CACHE_KEY);

        $this->resolved = null;
        $this->hasResolved = false;
    }

    private function isReachable(string $baseUrl): bool
    {
        try {
            $response = Http::timeout(self::PROBE_TIMEOUT_SECONDS)
                ->connectTimeout(self::PROBE_TIMEOUT_SECONDS)
                ->get(rtrim($baseUrl, '/').'/api/v1/ping');

            return $response->successful()
                && $response->json('service') === 'watchtower-hub';
        } catch (HttpClientException) {
            return false;
        }
    }
}

<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Contracts\TokenStore;
use App\Services\EndpointResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class EndpointResolverTest extends TestCase
{
    use RefreshDatabase;

    private TokenStore $store;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();

        $this->store = app(TokenStore::class);
        $this->store->set('hub.local_url', 'http://192.168.1.50');
        $this->store->set('hub.remote_url', 'https://watchtower.example.net');
    }

    #[Test]
    public function prefers_the_local_endpoint_when_it_responds(): void
    {
        Http::fake(['http://192.168.1.50/api/v1/ping' => Http::response(['service' => 'watchtower-hub'], 200)]);

        $this->assertSame('http://192.168.1.50', app(EndpointResolver::class)->baseUrl());
    }

    #[Test]
    public function falls_back_to_remote_when_local_probe_fails(): void
    {
        Http::fake(['http://192.168.1.50/api/v1/ping' => fn () => throw new ConnectionException('timeout')]);

        $this->assertSame('https://watchtower.example.net', app(EndpointResolver::class)->baseUrl());
    }

    #[Test]
    public function falls_back_to_remote_when_local_probe_returns_a_server_error(): void
    {
        Http::fake(['http://192.168.1.50/api/v1/ping' => Http::response('', 502)]);

        $this->assertSame('https://watchtower.example.net', app(EndpointResolver::class)->baseUrl());
    }

    #[Test]
    public function uses_remote_directly_when_no_local_url_is_configured(): void
    {
        $this->store->forget('hub.local_url');
        Http::fake();

        $this->assertSame('https://watchtower.example.net', app(EndpointResolver::class)->baseUrl());
        Http::assertNothingSent();
    }

    #[Test]
    public function returns_null_when_nothing_is_configured(): void
    {
        $this->store->forget('hub.local_url');
        $this->store->forget('hub.remote_url');

        $this->assertNull(app(EndpointResolver::class)->baseUrl());
    }

    #[Test]
    public function caches_the_probe_result_within_one_instance(): void
    {
        Http::fake(['http://192.168.1.50/api/v1/ping' => Http::response(['service' => 'watchtower-hub'], 200)]);

        $resolver = app(EndpointResolver::class);
        $resolver->baseUrl();
        $resolver->baseUrl();

        Http::assertSentCount(1);
    }

    #[Test]
    public function falls_back_to_remote_when_local_probe_returns_a_captive_portal_200(): void
    {
        Http::fake(['http://192.168.1.50/api/v1/ping' => Http::response('<html>Hotel WiFi Login</html>', 200)]);

        $this->assertSame('https://watchtower.example.net', app(EndpointResolver::class)->baseUrl());
    }

    #[Test]
    public function falls_back_to_remote_when_local_probe_throws_a_request_exception(): void
    {
        Http::fake(['http://192.168.1.50/api/v1/ping' => fn () => throw new RequestException(
            new Response(new \GuzzleHttp\Psr7\Response(508))
        )]);

        $this->assertSame('https://watchtower.example.net', app(EndpointResolver::class)->baseUrl());
    }

    #[Test]
    public function container_returns_the_same_scoped_instance_so_the_probe_is_shared(): void
    {
        Http::fake(['http://192.168.1.50/api/v1/ping' => Http::response(['service' => 'watchtower-hub'], 200)]);

        app(EndpointResolver::class)->baseUrl();
        app(EndpointResolver::class)->baseUrl();

        Http::assertSentCount(1);
    }

    // -------------------------------------------------------------------------
    // Cross-request cache (Change 1)
    // -------------------------------------------------------------------------

    #[Test]
    public function second_resolver_instance_within_ttl_skips_the_probe(): void
    {
        // Both instances use fresh objects (simulating separate requests),
        // but they share the same Laravel Cache store. The probe should fire
        // exactly once across both calls, proving cross-request caching.
        Http::fake(['http://192.168.1.50/api/v1/ping' => Http::response(['service' => 'watchtower-hub'], 200)]);

        $first = new EndpointResolver(app(TokenStore::class));
        $first->baseUrl();

        $second = new EndpointResolver(app(TokenStore::class));
        $second->baseUrl();

        Http::assertSentCount(1);
    }

    #[Test]
    public function forget_clears_the_cache_so_next_call_probes_again(): void
    {
        Http::fake(['http://192.168.1.50/api/v1/ping' => Http::response(['service' => 'watchtower-hub'], 200)]);

        $resolver = new EndpointResolver(app(TokenStore::class));
        $resolver->baseUrl();  // fills cache, sends probe #1

        $resolver->forget();   // wipes both per-instance memo and cache entry

        $resolver->baseUrl();  // must probe again

        Http::assertSentCount(2);
    }

    #[Test]
    public function null_result_is_not_cached_so_each_call_retries(): void
    {
        // No local_url, no remote_url → resolves to null every time.
        $this->store->forget('hub.local_url');
        $this->store->forget('hub.remote_url');

        Http::fake();

        $first = new EndpointResolver(app(TokenStore::class));
        $this->assertNull($first->baseUrl());

        // A second fresh instance must NOT find a cached value.
        $second = new EndpointResolver(app(TokenStore::class));
        $this->assertNull($second->baseUrl());

        $this->assertNull(Cache::get('hub.resolved_base_url'));
    }
}

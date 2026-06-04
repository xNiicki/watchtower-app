<?php

declare(strict_types=1);

namespace App\Providers;

use App\Contracts\HubClient;
use App\Contracts\TokenStore;
use App\Services\DatabaseTokenStore;
use App\Services\EndpointResolver;
use App\Services\FakeHubClient;
use App\Services\HttpHubClient;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(TokenStore::class, DatabaseTokenStore::class);
        $this->app->scoped(EndpointResolver::class);

        $driver = config('services.hub.driver', 'http');
        $this->app->scoped(
            HubClient::class,
            $driver === 'http' ? HttpHubClient::class : FakeHubClient::class,
        );
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Starter default for the on-device runtime. Outbound Http:: calls
        // (EndpointResolver probe, future HttpHubClient) use explicit URLs and
        // are unaffected; only generated route/asset URLs are forced. Skipped
        // in local/testing so plain-http browser dev keeps working.
        if (! app()->environment('local', 'testing')) {
            URL::forceHttps();
        }
    }
}

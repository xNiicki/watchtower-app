<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Contracts\TokenStore;
use App\Livewire\Settings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SettingsTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function renders_with_empty_fields_when_nothing_stored(): void
    {
        Livewire::test(Settings::class)
            ->assertSet('localUrl', '')
            ->assertSet('remoteUrl', '')
            ->assertSet('token', '');
    }

    #[Test]
    public function loads_existing_values_from_the_token_store(): void
    {
        $store = app(TokenStore::class);
        $store->set('hub.local_url', 'http://192.168.1.50');
        $store->set('hub.token', 'tok-123');

        Livewire::test(Settings::class)
            ->assertSet('localUrl', 'http://192.168.1.50')
            ->assertSet('token', 'tok-123');
    }

    #[Test]
    public function saves_values_to_the_token_store(): void
    {
        Livewire::test(Settings::class)
            ->set('localUrl', 'http://192.168.1.50')
            ->set('remoteUrl', 'https://watchtower.example.net')
            ->set('token', 'tok-456')
            ->call('save')
            ->assertDispatched('settings-saved');

        $store = app(TokenStore::class);
        $this->assertSame('http://192.168.1.50', $store->get('hub.local_url'));
        $this->assertSame('https://watchtower.example.net', $store->get('hub.remote_url'));
        $this->assertSame('tok-456', $store->get('hub.token'));
    }

    #[Test]
    public function rejects_invalid_urls(): void
    {
        Livewire::test(Settings::class)
            ->set('localUrl', 'not-a-url')
            ->call('save')
            ->assertHasErrors(['localUrl' => 'url']);
    }

    #[Test]
    public function blank_values_remove_stored_keys(): void
    {
        app(TokenStore::class)->set('hub.local_url', 'http://old');

        Livewire::test(Settings::class)
            ->set('localUrl', '')
            ->call('save');

        $this->assertNull(app(TokenStore::class)->get('hub.local_url'));
    }
}

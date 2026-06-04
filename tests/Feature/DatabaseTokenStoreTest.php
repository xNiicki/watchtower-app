<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Contracts\TokenStore;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class DatabaseTokenStoreTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function stores_and_retrieves_a_value(): void
    {
        $store = app(TokenStore::class);

        $store->set('hub.token', 'secret-123');

        $this->assertSame('secret-123', $store->get('hub.token'));
    }

    #[Test]
    public function returns_null_for_missing_keys(): void
    {
        $this->assertNull(app(TokenStore::class)->get('nope'));
    }

    #[Test]
    public function overwrites_an_existing_key(): void
    {
        $store = app(TokenStore::class);

        $store->set('hub.token', 'first');
        $store->set('hub.token', 'second');

        $this->assertSame('second', $store->get('hub.token'));
    }

    #[Test]
    public function forgets_a_key(): void
    {
        $store = app(TokenStore::class);

        $store->set('hub.token', 'bye');
        $store->forget('hub.token');

        $this->assertNull($store->get('hub.token'));
    }

    #[Test]
    public function values_are_encrypted_at_rest(): void
    {
        app(TokenStore::class)->set('hub.token', 'plaintext-secret');

        $raw = DB::table('secrets')->value('value');

        $this->assertStringNotContainsString('plaintext-secret', $raw);
    }

    #[Test]
    public function forgetting_a_nonexistent_key_is_a_no_op(): void
    {
        $this->expectNotToPerformAssertions();

        app(TokenStore::class)->forget('never-existed');
    }

    #[Test]
    public function raw_stored_value_looks_like_an_encrypted_payload(): void
    {
        app(TokenStore::class)->set('hub.token', 'plain');

        $raw = DB::table('secrets')->value('value');

        $this->assertStringStartsWith('eyJ', $raw);
    }
}

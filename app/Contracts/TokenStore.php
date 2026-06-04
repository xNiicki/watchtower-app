<?php

declare(strict_types=1);

namespace App\Contracts;

/**
 * Device-local secret storage. SQLite-backed in v1; swapped for the
 * NativePHP SecureStorage (iOS Keychain) plugin later without touching consumers.
 */
interface TokenStore
{
    /**
     * Returns null when the key does not exist OR when the stored value
     * cannot be decrypted (e.g. after an APP_KEY rotation). Callers
     * cannot distinguish the two cases.
     */
    public function get(string $key): ?string;

    public function set(string $key, string $value): void;

    public function forget(string $key): void;
}

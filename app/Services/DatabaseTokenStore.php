<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\TokenStore;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class DatabaseTokenStore implements TokenStore
{
    public function get(string $key): ?string
    {
        $encrypted = DB::table('secrets')->where('key', $key)->value('value');

        if ($encrypted === null) {
            return null;
        }

        try {
            return Crypt::decryptString($encrypted);
        } catch (DecryptException) {
            Log::warning('TokenStore: failed to decrypt stored value', ['key' => $key]);

            return null;
        }
    }

    public function set(string $key, string $value): void
    {
        DB::table('secrets')->upsert(
            [['key' => $key, 'value' => Crypt::encryptString($value), 'created_at' => now(), 'updated_at' => now()]],
            ['key'],
            ['value', 'updated_at'],
        );
    }

    public function forget(string $key): void
    {
        DB::table('secrets')->where('key', $key)->delete();
    }
}

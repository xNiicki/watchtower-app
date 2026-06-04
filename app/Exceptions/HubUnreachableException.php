<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

class HubUnreachableException extends RuntimeException
{
    public static function unconfigured(): self
    {
        return new self('Hub not configured — add endpoint URLs in Settings.');
    }

    public static function transport(\Throwable $e): self
    {
        return new self('Hub unreachable — '.$e->getMessage(), 0, $e);
    }
}

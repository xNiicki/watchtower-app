<?php

declare(strict_types=1);

namespace App\Livewire\Concerns;

use App\Exceptions\HubUnreachableException;

trait InteractsWithHub
{
    public ?string $hubError = null;

    /**
     * Wraps a HubClient call and catches HubUnreachableException.
     * Returns null on failure and sets $hubError for the view.
     */
    protected function hubData(callable $fetch): mixed
    {
        try {
            return $fetch();
        } catch (HubUnreachableException $e) {
            $this->hubError = $e->getMessage();

            return null;
        }
    }
}

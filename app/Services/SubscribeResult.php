<?php

namespace App\Services;

/**
 * Result envelope returned by FestivalSubscriptionService::subscribe().
 * Keeps the service non-throwing for expected failures (festival not in
 * FestivalAPI, etc.) so the Livewire caller doesn't need a try/catch
 * just to surface an inline error message.
 */
class SubscribeResult
{
    private function __construct(
        public readonly bool $ok,
        public readonly ?string $error,
    ) {}

    public static function success(): self
    {
        return new self(true, null);
    }

    public static function error(string $message): self
    {
        return new self(false, $message);
    }
}

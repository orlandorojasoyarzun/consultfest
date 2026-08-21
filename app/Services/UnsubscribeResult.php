<?php

namespace App\Services;

/**
 * Result envelope returned by FestivalSubscriptionService::unsubscribe().
 *
 * Mirrors SubscribeResult but carries an extra signal — whether a row
 * was actually deleted (`deleted`) or the call was a no-op (`noop`) —
 * because the HTTP and Livewire callers want different flash messages
 * for those two outcomes ("Te desuscribiste de X" vs. "No estabas
 * suscrito a X"). Booleans alone would force the caller to re-query
 * the DB to figure out which message to show.
 */
class UnsubscribeResult
{
    private function __construct(
        public readonly bool $deleted,
        public readonly ?string $message,
    ) {}

    public static function deleted(string $festivalName): self
    {
        return new self(true, "Te desuscribiste de {$festivalName}.");
    }

    public static function noop(string $message): self
    {
        return new self(false, $message);
    }
}

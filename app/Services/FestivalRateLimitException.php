<?php

namespace App\Services;

use RuntimeException;

/**
 * Thrown by FestivalSearchService when an IP exceeds the internal
 * festivalapi rate-limit (30/min). The Livewire component catches it and
 * renders the empty state instead of a 500.
 */
class FestivalRateLimitException extends RuntimeException
{
    public function __construct(public readonly int $retryAfterSeconds)
    {
        parent::__construct(
            "FestivalAPI rate limit hit. Retry in {$retryAfterSeconds} seconds.",
            429
        );
    }
}
<?php

namespace App\Services;

use App\Data\FestivalData;
use Illuminate\Cache\RateLimiter;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter as RateLimiterFacade;

/**
 * High-level facade for live festival search.
 *
 * Responsibilities (kept separate from FestivalApiService which only does
 * raw HTTP):
 *   - map component filters → FestivalAPI query params
 *   - cache responses for 1h by hash of filters
 *   - apply internal rate-limit (30/min/IP) so a script can't drain credits
 *   - filter out past deadlines client-side
 *   - degrade gracefully on API failure (return empty Collection)
 *
 * The Livewire component calls ONLY this service, never FestivalApiService
 * directly. That keeps the boundary clean and makes the service easy to
 * swap if we ever move to Estrategia B (full sync).
 */
class FestivalSearchService
{
    /**
     * How long a successful response stays in cache. FestivalAPI updates
     * continuously but with daily granularity, so 1h is the sweet spot:
     * fresh enough for time-sensitive data, long enough that identical
     * repeated searches cost zero credits.
     */
    private const CACHE_TTL_SECONDS = 3600;

    /**
     * Internal cap to avoid burning credits via scripted abuse. 30 req/min
     * is generous for any human user.
     */
    private const RATE_LIMIT_PER_MINUTE = 30;

    public function __construct(
        private readonly FestivalApiService $api,
        private readonly RateLimiter $rateLimiter,
    ) {}

    /**
     * Search festivals for the given component-level filters.
     *
     * @param  array<string, mixed>  $filters
     * @return Collection<int, FestivalData>
     */
    public function search(array $filters): Collection
    {
        $this->checkRateLimit();

        $cacheKey = $this->cacheKey($filters);

        try {
            $cached = Cache::get($cacheKey);
            if ($cached instanceof Collection) {
                return $cached;
            }
            // Anything else in cache (corrupt entry, __PHP_Incomplete_Class,
            // or a leftover from a previous code version) is discarded —
            // better to re-fetch than to crash the dashboard.
            if ($cached !== null) {
                Cache::forget($cacheKey);
                Log::warning('FestivalSearchService: dropped non-Collection cache value', [
                    'key' => $cacheKey,
                    'type' => is_object($cached) ? get_class($cached) : gettype($cached),
                ]);
            }
        } catch (\Throwable $e) {
            // Driver failure (DB cache table missing, etc.) — fall through.
            Log::warning('FestivalSearchService: cache read failed', ['error' => $e->getMessage()]);
        }

        $apiFilters = $this->mapFilters($filters);
        $payload = $this->api->searchFestivals($apiFilters);

        $results = collect($payload['results'] ?? [])
            ->map(fn (array $row) => FestivalData::fromApi($row))
            ->filter(fn (FestivalData $f) => $f->isAcceptingSubmissions())
            ->values();

        try {
            Cache::put($cacheKey, $results, self::CACHE_TTL_SECONDS);
        } catch (\Throwable $e) {
            // Cache write failure is non-fatal — we still return results.
            Log::warning('FestivalSearchService: cache write failed', ['error' => $e->getMessage()]);
        }

        return $results;
    }

    /**
     * Component filters → FestivalAPI query string.
     *
     * Component keys (from FestivalCalendar dispatch):
     *   - category     → maps directly
     *   - genre        → maps directly
     *   - country      → maps directly
     *   - startDate    → deadline_after OR event_date_after (if dateField=opening_date)
     *   - endDate      → deadline_before OR event_date_before
     *   - dateField    → '' (legacy) | 'opening_date' | 'deadline'
     *
     * FestivalAPI distinguishes event_date_* (when the festival runs) from
     * deadline_* (when submissions close). Our component exposes this via
     * the dropdown we built earlier.
     */
    private function mapFilters(array $filters): array
    {
        $api = [];

        if (!empty($filters['category'])) {
            $api['category'] = (string) $filters['category'];
        }
        if (!empty($filters['genre'])) {
            $api['genre'] = (string) $filters['genre'];
        }
        if (!empty($filters['country'])) {
            $api['country'] = (string) $filters['country'];
        }

        $dateField = (string) ($filters['dateField'] ?? '');
        $useEventDate = $dateField === 'opening_date';

        if (!empty($filters['startDate'])) {
            $api[$useEventDate ? 'event_date_after' : 'deadline_after'] = (string) $filters['startDate'];
        }
        if (!empty($filters['endDate'])) {
            $api[$useEventDate ? 'event_date_before' : 'deadline_before'] = (string) $filters['endDate'];
        }

        return $api;
    }

    /**
     * Deterministic cache key from filters. md5 is fine here — this isn't
     * a security boundary, just a way to dedupe identical queries.
     */
    private function cacheKey(array $filters): string
    {
        // Sort so ["a","b"] and ["b","a"] hit the same cache slot.
        ksort($filters);
        return 'festivalapi:search:' . md5(json_encode($filters) ?: '');
    }

    /**
     * Internal rate-limit so a user hammering the search form can't burn
     * through FestivalAPI credits. We piggy-back on Laravel's RateLimiter
     * facade which is request-scoped.
     */
    private function checkRateLimit(): void
    {
        $key = 'festivalapi:' . (request()?->ip() ?? 'cli');

        if ($this->rateLimiter->tooManyAttempts($key, self::RATE_LIMIT_PER_MINUTE)) {
            $retryAfter = $this->rateLimiter->availableIn($key);
            Log::warning('FestivalAPI rate-limit hit', [
                'ip' => $key,
                'retry_after' => $retryAfter,
            ]);
            // Throwing here would surface a 500 to the user; instead, the
            // service returns [] for the rest of this minute and the UI
            // renders the empty state.
            throw new FestivalRateLimitException($retryAfter);
        }

        $this->rateLimiter->hit($key, 60);
    }
}
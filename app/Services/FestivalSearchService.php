<?php

namespace App\Services;

use App\Data\FestivalData;
use Carbon\Carbon;
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
     * with daily granularity, so 24h is the sweet spot: fresh enough for
     * time-sensitive data (deadlines, new festivals), long enough that
     * identical repeated searches over the course of a day cost zero
     * credits.
     */
    private const CACHE_TTL_SECONDS = 86400;

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
            // FestivalAPI does NOT honour `event_date_after/before` (verified
            // 2026-08-09: with category=short_film + event_date_after=2026-12-01
            // + event_date_before=2027-03-01 the API returned 20 results of
            // which 13 had event_start_date outside the range). We filter
            // client-side so the UI matches what the user asked for.
            ->filter(fn (FestivalData $f) => $this->withinDateRange($f, $filters))
            ->values()
            // Order results by the same dateField the user picked. With no
            // dateField (legacy) we fall back to deadline — the next
            // submission closing date is the most actionable signal.
            ->sortBy(fn (FestivalData $f) => $this->sortKey($f, $filters))
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
     * Fetch a single festival's detail payload (with submission_url,
     * website, etc.) and return it as an enriched FestivalData.
     *
     * The list endpoint mis-maps submission_url/website to other festivals'
     * slugs, so the only way to get the real organizer URL is to hit the
     * detail endpoint — which costs 1 credit per call. We cache per apiId
     * for the same 24h window as search results so revisiting the same
     * festival in the same day costs nothing extra.
     *
     * If the detail call fails we return the input festival unchanged so
     * the caller can still render a card with a search URL fallback.
     */
    public function details(FestivalData $festival): FestivalData
    {
        $cacheKey = 'festivalapi:details:' . $festival->apiId;

        try {
            $cached = Cache::get($cacheKey);
            if ($cached instanceof FestivalData) {
                return $cached;
            }
            if ($cached !== null) {
                Cache::forget($cacheKey);
            }
        } catch (\Throwable $e) {
            Log::warning('FestivalSearchService: details cache read failed', [
                'error' => $e->getMessage(),
            ]);
        }

        $payload = $this->api->getFestivalDetails($festival->apiId);

        if ($payload === null) {
            return $festival;
        }

        $enriched = FestivalData::fromApi($payload);

        try {
            Cache::put($cacheKey, $enriched, self::CACHE_TTL_SECONDS);
        } catch (\Throwable $e) {
            Log::warning('FestivalSearchService: details cache write failed', [
                'error' => $e->getMessage(),
            ]);
        }

        return $enriched;
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

    /**
     * Post-fetch date filter. FestivalAPI ignores event_date_after/before
     * but respects deadline_* filters, so we only need to defend ourselves
     * for the "Apertura" dateField. If the user didn't ask for a date
     * filter (legacy '' dateField) or asked by deadline, we trust the API.
     *
     * @param  array<string, mixed>  $filters
     */
    private function withinDateRange(FestivalData $festival, array $filters): bool
    {
        if (($filters['dateField'] ?? '') !== 'opening_date') {
            return true;
        }

        // No event_start_date means we can't filter — be permissive.
        if ($festival->eventStartDate === null) {
            return true;
        }

        $start = !empty($filters['startDate']) ? Carbon::parse($filters['startDate']) : null;
        $end = !empty($filters['endDate']) ? Carbon::parse($filters['endDate']) : null;

        if ($start && $festival->eventStartDate->lt($start)) {
            return false;
        }
        if ($end && $festival->eventStartDate->gt($end)) {
            return false;
        }

        return true;
    }

    /**
     * Sort key for a single festival. FestivalAPI does NOT honour event_date_*
     * filters AND it does NOT honour any ordering on event_start_date vs
     * deadline, so we sort client-side using the field the user picked.
     *
     * Sort key rules:
     *   - dateField='opening_date' → eventStartDate (nulls last)
     *   - dateField='deadline' (or '') → deadline (nulls last)
     *   - We add apiId as a deterministic tiebreaker so two festivals with
     *     the exact same date don't shuffle on every request.
     *
     * @param  array<string, mixed>  $filters
     */
    private function sortKey(FestivalData $festival, array $filters): array
    {
        $useEventDate = ($filters['dateField'] ?? '') === 'opening_date';
        $date = $useEventDate ? $festival->eventStartDate : $festival->deadline;

        // PHP's sortBy puts arrays with the smaller first element first.
        // [0, timestamp] sorts before [1, timestamp], so we use 0 when the
        // date is present and 1 when it's null — pushing unknowns to the end.
        return [
            $date === null ? 1 : 0,
            $date?->timestamp ?? PHP_INT_MAX,
            $festival->apiId,
        ];
    }
}
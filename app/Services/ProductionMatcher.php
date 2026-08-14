<?php

namespace App\Services;

use App\Data\FestivalData;
use App\Models\Production;
use Illuminate\Support\Collection;

/**
 * Matches a Production (user-registered short film) against Festivals using
 * the live FestivalAPI catalogue — Estrategia A.
 *
 * Why this lives next to FestivalSearchService and NOT against the local
 * `Festival` table: under Estrategia A the local table is dev-only seed data
 * (~28 rows). Real matching needs the live ~12k-festival catalogue, which
 * FestivalSearchService already caches and exposes via FestivalData DTOs.
 *
 * Matching semantics (preserved from the previous local-DB implementation):
 *   - Category is the strongest signal: when present, we accept festivals of
 *     that category regardless of the production's genres.
 *   - Country narrows further (LIKE on country name).
 *   - Genres are passed to the API but the API returns any festival that
 *     matches the category+country, ignoring genre. That's intentional —
 *     festivals accept any sub-genre within their category in practice.
 *   - Guard: a Production with no matchable attributes must NOT dump the
 *     entire open catalogue.
 */
class ProductionMatcher
{
    /**
     * Hard cap on the pool of matches the matcher returns. FestivalAPI
     * returns up to 100 results per list call; we slice to keep the
     * in-memory DTO list bounded (and so the page can paginate a known
     * ceiling). The controller slices this into pages of PER_PAGE.
     */
    private const MAX_MATCHES = 50;

    /**
     * Number of top matches that get detail-enriched automatically. Detail
     * enrichment is what gives us real deadlines, real fees, and the real
     * organizer URL (the list endpoint returns null/mis-mapped for many of
     * those), but it costs 1 FestivalAPI credit per festival.
     *
     * We only enrich the top matches — those are the ones the user is most
     * likely to consider subscribing to and where dates matter most. The
     * tail of the list renders with list-endpoint data only (dates often
     * blank, URL via FilmFreeway-search fallback if the list doesn't have
     * one). Tail matches can be enriched on click through the redirect
     * endpoint, just like the dashboard listing.
     *
     * Cost math (first visit, cold cache):
     *   - 1 list + ENRICH_TOP_N details.
     *   - ENRICH_TOP_N = 5 → 6 credits.
     *   - ENRICH_TOP_N = 15 → 16 credits (old behaviour).
     *
     * Revisits within the 24h detail cache stay at 1 credit (list only).
     */
    private const ENRICH_TOP_N = 5;

    public function __construct(
        private readonly FestivalSearchService $search,
    ) {}

    /**
     * @return Collection<int, FestivalData>
     */
    public function matchFor(Production $production): Collection
    {
        $hasCategory = !empty($production->category);
        $hasCountry = !empty($production->country);
        $hasGenres = !empty($production->genres) && is_array($production->genres);

        if (!$hasCategory && !$hasCountry && !$hasGenres) {
            return collect();
        }

        $filters = [];

        if ($hasCategory) {
            $filters['category'] = (string) $production->category;
        }
        if ($hasCountry) {
            $filters['country'] = (string) $production->country;
        }
        // FestivalAPI's `genre` param is singular and accepts a single value,
        // so we pass the first genre as a soft signal — the API will surface
        // festivals that broadly match. We don't iterate all genres because
        // each would be a separate request.
        if ($hasGenres) {
            $filters['genre'] = (string) $production->genres[0];
        }

        $results = $this->search->search($filters);

        // Trim to MAX_MATCHES. FestivalAPI returns up to 100 anyway, but the
        // old contract returned 50 — keeping the same UI assumption.
        $matches = $results->take(self::MAX_MATCHES);

        // Enrich only the top matches — those are the ones most likely to
        // be acted on. The tail keeps list-endpoint data and gets enriched
        // lazily on click through the redirect endpoint. See ENRICH_TOP_N
        // for the cost math.
        $enriched = $matches->take(self::ENRICH_TOP_N)
            ->map(fn (FestivalData $f) => $this->search->details($f));

        $tail = $matches->slice(self::ENRICH_TOP_N)->values();

        return $enriched->merge($tail)->values();
    }
}

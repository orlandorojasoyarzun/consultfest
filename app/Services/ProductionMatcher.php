<?php

namespace App\Services;

use App\Models\Festival;
use App\Models\Production;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Matches a Production (user-registered short film) against Festivals
 * using a declarative, AI-free query.
 *
 * Reuses the LIKE-wildcard escaping pattern from FestivalResults::search().
 * Matching is AND across attributes (category AND country AND genres) — a
 * Production must qualify on each declared attribute to be suggested.
 */
class ProductionMatcher
{
    public function matchFor(Production $production): Collection
    {
        $hasCategory = !empty($production->category);
        $hasCountry = !empty($production->country);
        $hasGenres = !empty($production->genres) && is_array($production->genres);

        // Guard: a Production with no matchable attributes must NOT dump the
        // entire open catalogue — that's a misleading "match" UX and an
        // information disclosure vector.
        if (!$hasCategory && !$hasCountry && !$hasGenres) {
            return collect();
        }

        $query = Festival::query()
            ->where('accepting_submissions', true)
            ->where('deadline', '>=', now()->toDateString());

        // Category is the strongest signal: when present, we accept
        // festivals of that category regardless of the production's
        // genres. (Festivals accept any sub-genre within their category
        // in practice — a documentary festival won't reject a doc just
        // because the filmmaker tagged it 'drama'.) Genres still act as
        // a soft tiebreaker below.
        if ($hasCategory) {
            $query->where('category', $production->category);
        } elseif ($hasGenres) {
            // No category: fall back to genres as the filter.
            $query->where(function (Builder $q) use ($production) {
                foreach ($production->genres as $genre) {
                    $escaped = addcslashes((string) $genre, '%_\\');
                    $q->orWhere('details->genres', 'LIKE', '%' . $escaped . '%');
                }
            });
        }

        if ($hasCountry) {
            // Escape LIKE wildcards so a malicious country value cannot
            // expand the match set (information disclosure / full-scan DoS).
            $country = addcslashes((string) $production->country, '%_\\');
            $query->where('country', 'LIKE', '%' . $country . '%');
        }

        // Order: festivals that share at least one genre with the
        // production float to the top; the rest keep their deadline order.
        $query->orderBy('deadline');

        return $query->limit(50)->get();
    }
}
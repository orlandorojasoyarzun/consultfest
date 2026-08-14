<?php

namespace Tests\Unit\Services;

use App\Data\FestivalData;
use App\Models\Production;
use App\Models\Subscriber;
use App\Services\FestivalSearchService;
use App\Services\ProductionMatcher;
use Illuminate\Cache\RateLimiter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ProductionMatcherTest extends TestCase
{
    use RefreshDatabase;

    private ProductionMatcher $matcher;

    protected function setUp(): void
    {
        parent::setUp();
        $this->matcher = app(ProductionMatcher::class);

        // Reset the per-apiId details cache and the rate limiter between
        // tests so the array driver doesn't leak state across runs.
        Cache::flush();
        $this->app->make(RateLimiter::class)->clear('festivalapi:127.0.0.1');
    }

    /**
     * Helper: build a fake FestivalAPI payload with realistic shape.
     */
    private function fakeFestival(array $overrides = []): array
    {
        return array_merge([
            'id' => 1,
            'name' => 'Fake Festival',
            'country' => 'Spain',
            'categories' => ['short_film'],
            'genres' => ['drama'],
            'deadline_regular' => now()->addDays(30)->toDateString(),
            'event_start_date' => now()->addMonths(2)->toDateString(),
            'regular_fee' => 25.0,
            'submission_url' => 'https://example.com/submit',
            'website' => 'https://example.com',
            'composite_score' => 70.0,
        ], $overrides);
    }

    /**
     * Fake BOTH endpoints so ProductionMatcher's detail enrichment works:
     * the list endpoint returns the given rows; the detail endpoint returns
     * each row's id+name with empty URLs (so the FilmFreeway search fallback
     * kicks in without crashing bestUrl()).
     */
    private function fakeListAndDetails(array $results): void
    {
        Http::fake([
            'https://festivalapi.com/v1/festivals/*/' => function ($request) use ($results) {
                preg_match('#/festivals/(\d+)/#', $request->url(), $m);
                $id = (int) ($m[1] ?? 0);
                $row = collect($results)->firstWhere('id', $id) ?? ($results[0] ?? []);
                return Http::response(array_merge($row, [
                    'submission_url' => '',
                    'website' => '',
                ]), 200);
            },
            'https://festivalapi.com/v1/festivals*' => Http::response([
                'count' => count($results),
                'results' => $results,
            ], 200),
        ]);
    }

    public function test_match_returns_festivals_with_same_category_and_country(): void
    {
        $this->fakeListAndDetails([
            // Match on both category+country
            $this->fakeFestival(['id' => 100, 'name' => 'Match']),
            // Match on category only — API doesn't filter by country,
            // so the matcher can't enforce it post-hoc either. This
            // documents the current behaviour: ProductionMatcher is
            // a thin wrapper over FestivalAPI and inherits its
            // loose matching semantics.
            $this->fakeFestival(['id' => 200, 'name' => 'Wrong Country', 'country' => 'France']),
        ]);

        $subscriber = Subscriber::factory()->create();
        $production = Production::factory()->create([
            'subscriber_id' => $subscriber->id,
            'category' => 'short_film',
            'country' => 'Spain',
            'genres' => [],
        ]);

        $matches = $this->matcher->matchFor($production);

        // Verify the API was called with category+country as filters.
        Http::assertSent(fn ($request) => str_contains($request->url(), 'category=short_film')
            && str_contains($request->url(), 'country=Spain')
            && ! str_contains($request->url(), '/festivals/'));

        // Both festivals come back — FestivalAPI doesn't do exact country
        // matching. The matcher is honest about that.
        $this->assertCount(2, $matches);
        $this->assertInstanceOf(FestivalData::class, $matches->first());
    }

    public function test_match_returns_empty_when_production_has_no_matchable_attrs(): void
    {
        $this->fakeListAndDetails([
            $this->fakeFestival(['id' => 1]),
            $this->fakeFestival(['id' => 2]),
            $this->fakeFestival(['id' => 3]),
        ]);

        $subscriber = Subscriber::factory()->create();
        $production = Production::factory()->create([
            'subscriber_id' => $subscriber->id,
            'category' => null,
            'country' => null,
            'genres' => [],
        ]);

        $matches = $this->matcher->matchFor($production);

        $this->assertCount(0, $matches);
        // Critical: we MUST NOT hit FestivalAPI at all when there are no
        // matchable attrs — that's how we avoid burning credits on an
        // empty "match everything" query.
        Http::assertNothingSent();
    }

    public function test_match_respects_max_matches_cap(): void
    {
        // The API returns up to 100; the matcher must cap to MAX_MATCHES (50).
        // MAX_MATCHES is the pool size available for pagination, not the page
        // size — the controller slices into pages of PER_PAGE on top. Keep
        // this > 60 in the future if you widen the pool.
        $payload = [];
        for ($i = 1; $i <= 80; $i++) {
            $payload[] = $this->fakeFestival(['id' => $i]);
        }
        $this->fakeListAndDetails($payload);

        $subscriber = Subscriber::factory()->create();
        $production = Production::factory()->create([
            'subscriber_id' => $subscriber->id,
            'category' => 'short_film',
            'country' => null,
            'genres' => [],
        ]);

        $matches = $this->matcher->matchFor($production);

        $this->assertCount(50, $matches);
    }

    public function test_match_passes_first_genre_as_filter(): void
    {
        $this->fakeListAndDetails([$this->fakeFestival()]);

        $subscriber = Subscriber::factory()->create();
        $production = Production::factory()->create([
            'subscriber_id' => $subscriber->id,
            'category' => null,
            'country' => null,
            'genres' => ['horror', 'thriller', 'mystery'],
        ]);

        $this->matcher->matchFor($production);

        Http::assertSent(function ($request) {
            $url = $request->url();
            // The first genre should be in the list-endpoint query string.
            return str_contains($url, 'genre=horror')
                && ! str_contains($url, '/festivals/');
        });
    }

    public function test_match_returns_empty_on_api_failure(): void
    {
        Http::fake([
            'https://festivalapi.com/v1/festivals*' => Http::response('', 500),
            'https://festivalapi.com/v1/festivals/*/' => Http::response('', 500),
        ]);

        $subscriber = Subscriber::factory()->create();
        $production = Production::factory()->create([
            'subscriber_id' => $subscriber->id,
            'category' => 'short_film',
            'country' => 'Spain',
            'genres' => [],
        ]);

        $matches = $this->matcher->matchFor($production);

        // FestivalSearchService degrades to [] on error; matcher returns [].
        $this->assertCount(0, $matches);
    }

    public function test_match_uses_category_filter_when_only_category_is_set(): void
    {
        $this->fakeListAndDetails([
            $this->fakeFestival(['id' => 10, 'categories' => ['documentary']]),
        ]);

        $subscriber = Subscriber::factory()->create();
        $production = Production::factory()->create([
            'subscriber_id' => $subscriber->id,
            'category' => 'documentary',
            'country' => null,
            'genres' => [],
        ]);

        $this->matcher->matchFor($production);

        Http::assertSent(fn ($request) => str_contains($request->url(), 'category=documentary')
            && ! str_contains($request->url(), '/festivals/'));
    }

    public function test_match_enriches_only_top_n_results_with_detail_endpoint(): void
    {
        // Cost guard: the matcher enriches only the first ENRICH_TOP_N (5)
        // matches via the detail endpoint so they show real dates. Tail
        // matches render with list-endpoint data only (dates blank for
        // many) and rely on the redirect endpoint for enrichment on click.
        // Bumping ENRICH_TOP_N in ProductionMatcher (to widen the enriched
        // set once the project has a FestivalAPI budget) requires updating
        // this test and its count assertion.
        $listResults = [];
        for ($i = 1; $i <= 8; $i++) {
            $listResults[] = $this->fakeFestival([
                'id' => $i,
                'name' => "Fest {$i}",
                'deadline_regular' => null,
                'submission_url' => 'wrong-slug',
            ]);
        }

        Http::fake([
            // Detail: first 5 ids (1..5) get enriched. 6..8 stay list-only.
            'https://festivalapi.com/v1/festivals/*/' => function ($request) use ($listResults) {
                preg_match('#/festivals/(\d+)/#', $request->url(), $m);
                $id = (int) ($m[1] ?? 0);
                $row = collect($listResults)->firstWhere('id', $id);
                if (!$row) {
                    return Http::response('', 404);
                }
                return Http::response(array_merge($row, [
                    'deadline_regular' => now()->addDays(30)->toDateString(),
                    'regular_fee' => 20.0,
                    'submission_url' => "https://filmfreeway.com/Fest-{$id}",
                ]), 200);
            },
            'https://festivalapi.com/v1/festivals*' => Http::response([
                'count' => count($listResults),
                'results' => $listResults,
            ], 200),
        ]);

        $subscriber = Subscriber::factory()->create();
        $production = Production::factory()->create([
            'subscriber_id' => $subscriber->id,
            'category' => 'short_film',
            'country' => 'Spain',
            'genres' => [],
        ]);

        $matches = $this->matcher->matchFor($production);

        $this->assertCount(8, $matches);

        // Top 5: enriched, real deadline + URL.
        $top = $matches->take(5);
        $this->assertNotNull($top->first()->deadline, 'Top match should be enriched');
        $this->assertSame('https://filmfreeway.com/Fest-1', $top->first()->submissionUrl);

        // Tail (6..8): list-endpoint values, deadline null, wrong-slug URL.
        $tail = $matches->slice(5)->values();
        $this->assertNull($tail->first()->deadline, 'Tail match should NOT be enriched');
        $this->assertSame('wrong-slug', $tail->first()->submissionUrl);

        // Detail endpoint hit exactly ENRICH_TOP_N times (5).
        $detailCalls = collect(Http::recorded())
            ->filter(fn ($pair) => str_contains($pair[0]->url(), '/festivals/') === true)
            ->count();
        $this->assertSame(5, $detailCalls);
    }

    public function test_match_enrichment_degrades_gracefully_on_detail_failure(): void
    {
        // If the detail endpoint fails for every match, the matcher must
        // still return the list-endpoint festivals so the page renders.
        Http::fake([
            // Detail pattern first (Http::fake first-match-wins).
            'https://festivalapi.com/v1/festivals/*/' => Http::response('', 500),
            'https://festivalapi.com/v1/festivals*' => Http::response([
                'count' => 1,
                'results' => [$this->fakeFestival(['id' => 7, 'name' => 'Solo List'])],
            ], 200),
        ]);

        $subscriber = Subscriber::factory()->create();
        $production = Production::factory()->create([
            'subscriber_id' => $subscriber->id,
            'category' => 'short_film',
            'country' => 'Spain',
            'genres' => [],
        ]);

        $matches = $this->matcher->matchFor($production);

        $this->assertCount(1, $matches);
        $this->assertSame('Solo List', $matches->first()->name);
    }

    public function test_match_enrichment_caches_detail_per_festival(): void
    {
        // Second matchFor() call for the same production must NOT hit the
        // detail endpoint again — the per-apiId cache (24h) handles that.
        // The first call enriches 1 festival (ENRICH_TOP_N covers it
        // since the list returns 1 result), the second serves from cache.
        $this->fakeListAndDetails([
            $this->fakeFestival(['id' => 99, 'name' => 'Cached']),
        ]);

        $subscriber = Subscriber::factory()->create();
        $production = Production::factory()->create([
            'subscriber_id' => $subscriber->id,
            'category' => 'short_film',
            'country' => 'Spain',
            'genres' => [],
        ]);

        $this->matcher->matchFor($production);
        $this->matcher->matchFor($production);

        // 2 list calls (one per matchFor invocation). Detail should fire
        // once and then be served from cache the second time.
        $detailCalls = collect(Http::recorded())
            ->filter(fn ($pair) => str_contains($pair[0]->url(), '/festivals/') === true)
            ->count();
        $this->assertSame(1, $detailCalls);
    }
}

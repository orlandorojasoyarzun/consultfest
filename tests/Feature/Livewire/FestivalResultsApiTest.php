<?php

namespace Tests\Feature\Livewire;

use App\Livewire\FestivalResults;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

class FestivalResultsApiTest extends TestCase
{
    use RefreshDatabase;

    private function fakeFestivals(int $count = 3): array
    {
        $results = [];
        for ($i = 1; $i <= $count; $i++) {
            $results[] = [
                'id' => $i,
                'name' => "Festival {$i}",
                'categories' => ['short_film'],
                'genres' => ['drama'],
                'country' => 'Spain',
                'deadline_regular' => Carbon::now()->addDays(15)->toDateString(),
                'regular_fee' => 25.0 + $i,
                'composite_score' => 80.0 + $i,
                'submission_url' => "https://filmfreeway.com/festival-{$i}",
            ];
        }
        return $results;
    }

    /**
     * Fake BOTH the list endpoint and the per-festival detail endpoint.
     * Most tests don't care about detail URLs — they just want the card
     * to render. So the detail endpoint returns the same row with empty
     * URLs, which makes bestUrl() fall back to the FilmFreeway search.
     *
     * Http::fake matches patterns with Str::is() under the hood, prepending
     * a star to each key. A bare `/festivals` (no star) only matches URLs
     * ending exactly there — meaning it WOULDN'T match `/festivals?...`.
     * Patterns must end with star to also catch the query string. The
     * detail pattern uses a slash so it only matches detail URLs.
     */
    private function fakeFestivalsWithDetails(array $results): void
    {
        Http::fake([
            // Detail endpoint: /festivals/{id}/ with the trailing slash.
            'https://festivalapi.com/v1/festivals/*/' => function ($request) use ($results) {
                preg_match('#/festivals/(\d+)/#', $request->url(), $m);
                $id = (int) ($m[1] ?? 0);
                $row = collect($results)->firstWhere('id', $id) ?? ($results[0] ?? []);
                return Http::response(array_merge($row, [
                    'submission_url' => '',
                    'website' => '',
                ]), 200);
            },
            // List endpoint: /festivals with optional query string.
            'https://festivalapi.com/v1/festivals*' => Http::response([
                'count' => count($results),
                'results' => $results,
            ], 200),
        ]);
    }

    public function test_results_renders_with_api_data(): void
    {
        $this->fakeFestivalsWithDetails($this->fakeFestivals(3));

        Livewire::test(FestivalResults::class)
            ->assertSee('Festival 1')
            ->assertSee('Festival 2')
            ->assertSee('Festival 3')
            ->assertSee('3 festivales');
    }

    public function test_results_shows_empty_state_on_api_failure(): void
    {
        Http::fake([
            'https://festivalapi.com/v1/festivals*' => Http::response(['detail' => 'Server error'], 500),
            'https://festivalapi.com/v1/festivals/*/' => Http::response(['detail' => 'Server error'], 500),
        ]);

        Livewire::test(FestivalResults::class)
            ->assertSee('No hay festivales');
    }

    public function test_results_shows_empty_state_on_401(): void
    {
        Http::fake([
            'https://festivalapi.com/v1/festivals*' => Http::response(['detail' => 'Invalid API key'], 401),
            'https://festivalapi.com/v1/festivals/*/' => Http::response(['detail' => 'Invalid API key'], 401),
        ]);

        Livewire::test(FestivalResults::class)
            ->assertSee('No hay festivales');
    }

    public function test_results_pagination_works_with_api_data(): void
    {
        // Detail endpoint needs single-festival responses — pad with empty
        // submission_url/website so the fallback kicks in without breaking
        // bestUrl().
        Http::fake([
            'https://festivalapi.com/v1/festivals/*/' => Http::response([
                'id' => 1,
                'name' => 'Festival 1',
                'categories' => ['short_film'],
                'submission_url' => '',
                'website' => '',
            ], 200),
            'https://festivalapi.com/v1/festivals*' => Http::response([
                'count' => 25,
                'results' => $this->fakeFestivals(25),
            ], 200),
        ]);

        $component = Livewire::test(FestivalResults::class);
        $component->assertSee('25 festivales', false);
        $component->assertSee('Página 1 de 3', false);
        $component->assertSet('page', 1);

        $component->call('gotoPage', 2);
        $component->assertSee('Página 2 de 3', false);
        $component->assertSet('page', 2);

        $component->call('nextPage');
        $component->assertSet('page', 3);

        $component->call('previousPage');
        $component->assertSet('page', 2);
    }

    public function test_results_shows_filmfreeway_search_link_when_detail_has_no_url(): void
    {
        // Cards on the listing page must point at the server-side redirect
        // route, NOT directly at the festival URL. The redirect endpoint is
        // the only place where the detail enrichment runs (1 credit, 24h
        // cache per apiId) — see FestivalController::redirectToFestival.
        Http::fake([
            'https://festivalapi.com/v1/festivals*' => Http::response([
                'count' => 1,
                'results' => [[
                    'id' => 99,
                    'name' => 'External Fest',
                    'categories' => ['feature'],
                ]],
            ], 200),
        ]);

        Livewire::test(FestivalResults::class)
            ->assertSee('External Fest')
            ->assertSee(route('festivals.redirect', ['apiId' => 99]), false);
    }

    public function test_results_prefers_real_submission_url_from_detail_endpoint(): void
    {
        // The component itself does NOT enrich — that happens on click
        // through /festivals/{id}/redirect. So the list page must render
        // the redirect route, not the (potentially wrong) list-endpoint
        // URL. This protects users from "Private Project" without burning
        // credits on cards they never click.
        Http::fake([
            'https://festivalapi.com/v1/festivals*' => Http::response([
                'count' => 1,
                'results' => [[
                    'id' => 99,
                    'name' => 'External Fest',
                    'categories' => ['feature'],
                    'submission_url' => 'https://filmfreeway.com/WrongSlug',
                ]],
            ], 200),
        ]);

        Livewire::test(FestivalResults::class)
            ->assertSee('External Fest')
            ->assertSee(route('festivals.redirect', ['apiId' => 99]), false)
            // The card must NOT embed the wrong-slug URL directly.
            ->assertDontSee('https://filmfreeway.com/WrongSlug', false);
    }

    public function test_results_falls_back_to_search_url_when_detail_endpoint_fails(): void
    {
        // The list page never calls the detail endpoint, so list rendering
        // must succeed regardless of whether the detail endpoint is up.
        Http::fake([
            'https://festivalapi.com/v1/festivals*' => Http::response([
                'count' => 1,
                'results' => [[
                    'id' => 99,
                    'name' => 'External Fest',
                    'categories' => ['feature'],
                ]],
            ], 200),
            // Even if the detail endpoint is on fire, the list must render.
            'https://festivalapi.com/v1/festivals/*/' => Http::response('', 500),
        ]);

        Livewire::test(FestivalResults::class)
            ->assertSee('External Fest')
            ->assertSee(route('festivals.redirect', ['apiId' => 99]), false);
    }

    public function test_results_only_calls_list_no_detail_on_render(): void
    {
        // Cost guard: the listing page must spend exactly 1 FestivalAPI
        // credit (the list call) regardless of how many results paginate.
        // Detail enrichment is now lazy — it only happens when the user
        // clicks a card (the redirect route triggers the detail call).
        Http::fake([
            'https://festivalapi.com/v1/festivals*' => Http::response([
                'count' => 25,
                'results' => $this->fakeFestivals(25),
            ], 200),
        ]);

        Livewire::test(FestivalResults::class);

        // Just the list call — no detail calls during render.
        Http::assertSentCount(1);
        Http::assertSent(fn ($request) => str_contains($request->url(), '/festivals?')
            || str_contains($request->url(), '/festivals&')
            || str_ends_with(parse_url($request->url(), PHP_URL_PATH) ?? '', '/festivals'));
    }

    public function test_results_filters_past_deadlines_out(): void
    {
        $this->fakeFestivalsWithDetails([
            ['id' => 1, 'name' => 'Closed', 'categories' => ['feature'], 'deadline_regular' => '2020-01-01'],
            ['id' => 2, 'name' => 'Open', 'categories' => ['feature'], 'deadline_regular' => Carbon::now()->addDays(30)->toDateString()],
        ]);

        Livewire::test(FestivalResults::class)
            ->assertSee('Open')
            ->assertDontSee('Closed');
    }

    public function test_results_shows_genre_chips(): void
    {
        $this->fakeFestivalsWithDetails([[
            'id' => 1,
            'name' => 'Genre Fest',
            'categories' => ['feature'],
            'genres' => ['drama', 'comedy', 'horror'],
        ]]);

        Livewire::test(FestivalResults::class)
            ->assertSee('drama')
            ->assertSee('comedy')
            ->assertSee('horror');
    }

    public function test_results_search_dispatches_through_filters(): void
    {
        Http::fake([
            'https://festivalapi.com/v1/festivals/*/' => Http::response(['id' => 0, 'name' => 'unused'], 200),
            'https://festivalapi.com/v1/festivals*' => Http::response(['count' => 0, 'results' => []], 200),
        ]);

        Livewire::test(FestivalResults::class)
            ->call('search', [
                'category' => 'feature',
                'genre' => 'drama',
                'country' => 'France',
                'startDate' => '2026-08-01',
                'endDate' => '2026-08-31',
            ]);

        Http::assertSent(function ($request) {
            $url = $request->url();
            return str_contains($url, 'category=feature')
                && str_contains($url, 'genre=drama')
                && str_contains($url, 'country=France')
                && str_contains($url, 'deadline_after=2026-08-01');
        });
    }

    public function test_results_handles_thousand_count_by_acknowledging_limit(): void
    {
        $this->fakeFestivalsWithDetails($this->fakeFestivals(100));

        Livewire::test(FestivalResults::class)
            ->assertSee('100 festivales', false)
            ->assertSee('primeros 100 resultados', false);
    }
}
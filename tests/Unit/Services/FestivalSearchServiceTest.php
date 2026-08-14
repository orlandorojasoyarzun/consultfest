<?php

namespace Tests\Unit\Services;

use App\Data\FestivalData;
use App\Services\FestivalApiService;
use App\Services\FestivalRateLimitException;
use App\Services\FestivalSearchService;
use Carbon\Carbon;
use Illuminate\Cache\RateLimiter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class FestivalSearchServiceTest extends TestCase
{
    use RefreshDatabase;

    private function makeService(?FestivalApiService $api = null): FestivalSearchService
    {
        return new FestivalSearchService(
            $api ?? new FestivalApiService(),
            $this->app->make(RateLimiter::class),
        );
    }

    private function fakeApi(array $results, int $status = 200): void
    {
        Http::fake([
            // End with star so the URL matches even when FestivalAPI
            // appends a query string (?deadline_after=...). Without the
            // trailing star the bare /festivals pattern only matches
            // the exact URL with no query string.
            'https://festivalapi.com/v1/festivals*' => Http::response([
                'count' => count($results),
                'results' => $results,
            ], $status),
        ]);
    }

    public function test_search_returns_collection_of_festival_data(): void
    {
        $this->fakeApi([[
            'id' => 1,
            'name' => 'Sundance Film Festival',
            'categories' => ['feature', 'short_film'],
            'genres' => ['drama', 'comedy'],
            'country' => 'United States',
            'deadline_regular' => '2026-09-15',
            'regular_fee' => 85.0,
            'composite_score' => 94.5,
        ]]);

        $results = $this->makeService()->search([]);

        $this->assertCount(1, $results);
        $f = $results->first();
        $this->assertInstanceOf(FestivalData::class, $f);
        $this->assertSame('Sundance Film Festival', $f->name);
        $this->assertSame('feature', $f->primaryCategory);
        $this->assertSame(['feature', 'short_film'], $f->categories);
        $this->assertSame(['drama', 'comedy'], $f->genres);
        $this->assertSame('United States', $f->country);
        $this->assertSame(85.0, $f->regularFee);
        $this->assertSame(94.5, $f->compositeScore);
        $this->assertTrue($f->deadline->isFuture());
    }

    public function test_search_caches_results_for_one_hour(): void
    {
        $this->fakeApi([['id' => 1, 'name' => 'Cached', 'categories' => ['feature']]]);

        $service = $this->makeService();
        $filters = ['category' => 'feature'];

        $service->search($filters); // 1st call: hits API
        $service->search($filters); // 2nd call: should hit cache

        Http::assertSentCount(1);
    }

    public function test_search_returns_empty_on_api_error(): void
    {
        $this->fakeApi([], 500);

        $results = $this->makeService()->search(['category' => 'short_film']);

        $this->assertCount(0, $results);
    }

    public function test_search_filters_out_past_deadlines(): void
    {
        $this->fakeApi([
            ['id' => 1, 'name' => 'Closed Already', 'categories' => ['feature'], 'deadline_regular' => '2020-01-01'],
            ['id' => 2, 'name' => 'Still Open', 'categories' => ['feature'], 'deadline_regular' => Carbon::now()->addDays(30)->toDateString()],
        ]);

        $results = $this->makeService()->search([]);

        $this->assertCount(1, $results);
        $this->assertSame('Still Open', $results->first()->name);
    }

    public function test_search_maps_deadline_filters_to_api(): void
    {
        Http::fake(['https://festivalapi.com/v1/festivals*' => Http::response(['count' => 0, 'results' => []], 200)]);

        $this->makeService()->search([
            'category' => 'short_film',
            'genre' => 'drama',
            'country' => 'Spain',
            'startDate' => '2026-08-01',
            'endDate' => '2026-08-31',
            'dateField' => 'deadline',
        ]);

        Http::assertSent(function ($request) {
            $url = $request->url();
            return str_contains($url, 'category=short_film')
                && str_contains($url, 'genre=drama')
                && str_contains($url, 'country=Spain')
                && str_contains($url, 'deadline_after=2026-08-01')
                && str_contains($url, 'deadline_before=2026-08-31');
        });
    }

    public function test_search_uses_event_date_when_dateField_is_opening_date(): void
    {
        Http::fake(['https://festivalapi.com/v1/festivals*' => Http::response(['count' => 0, 'results' => []], 200)]);

        $this->makeService()->search([
            'startDate' => '2026-08-01',
            'endDate' => '2026-08-31',
            'dateField' => 'opening_date',
        ]);

        Http::assertSent(function ($request) {
            $url = $request->url();
            return str_contains($url, 'event_date_after=2026-08-01')
                && str_contains($url, 'event_date_before=2026-08-31')
                && !str_contains($url, 'deadline_after');
        });
    }

    public function test_search_omits_empty_filters(): void
    {
        Http::fake(['https://festivalapi.com/v1/festivals*' => Http::response(['count' => 0, 'results' => []], 200)]);

        $this->makeService()->search([
            'startDate' => '2026-08-01',
            'endDate' => '2026-08-31',
            'category' => '',
            'genre' => null,
            'country' => null,
        ]);

        Http::assertSent(function ($request) {
            $url = $request->url();
            // No empty params leaked into the URL.
            return !str_contains($url, 'category=')
                && !str_contains($url, 'genre=')
                && !str_contains($url, 'country=');
        });
    }

    public function test_search_throws_rate_limit_exception_after_30_calls(): void
    {
        $this->fakeApi([['id' => 1, 'name' => 'X', 'categories' => ['feature']]]);

        // Bypass rate limit increment by clearing between calls.
        // We need 31 calls in the same minute to trigger the limit; rather
        // than hammering, we hit the limiter directly via reflection to
        // bump the counter past the threshold.
        $service = $this->makeService();
        $filters = ['category' => 'feature'];

        // Manually saturate the limiter for this IP.
        $key = 'festivalapi:127.0.0.1';
        for ($i = 0; $i < 30; $i++) {
            $this->app->make(RateLimiter::class)->hit($key, 60);
        }

        $this->expectException(FestivalRateLimitException::class);
        $service->search($filters);
    }

    public function test_search_uses_carbon_for_deadline(): void
    {
        $this->fakeApi([[
            'id' => 1,
            'name' => 'Test',
            'categories' => ['feature'],
            'deadline_regular' => '2026-12-01',
        ]]);

        $results = $this->makeService()->search([]);

        $this->assertInstanceOf(Carbon::class, $results->first()->deadline);
        $this->assertSame('2026-12-01', $results->first()->deadline->toDateString());
    }

    public function test_search_handles_missing_optional_fields(): void
    {
        $this->fakeApi([['id' => 7, 'name' => 'Minimal']]);

        $results = $this->makeService()->search([]);

        $f = $results->first();
        $this->assertNull($f->primaryCategory);
        $this->assertNull($f->country);
        $this->assertNull($f->deadline);
        $this->assertSame([], $f->genres);
        // Festival with no deadline is treated as open.
        $this->assertTrue($f->isAcceptingSubmissions());
    }

    public function test_search_throws_for_invalid_payload(): void
    {
        $this->fakeApi([['name' => 'No ID Here']]);

        $this->expectException(\InvalidArgumentException::class);
        $this->makeService()->search([]);
    }

    /**
     * Regression test for the "Apertura" bug (2026-08-09).
     *
     * FestivalAPI does NOT honour event_date_after/before. With category
     * short_film + event_date_after=2026-12-01 + event_date_before=2027-03-01
     * the API returned 20 festivals of which 13 had event_start_date outside
     * the requested range. We post-filter to defend the user.
     */
    public function test_search_filters_by_event_start_date_when_dateField_is_opening_date(): void
    {
        $this->fakeApi([
            [
                'id' => 1,
                'name' => 'In Range',
                'categories' => ['short_film'],
                'event_start_date' => '2027-01-15',
                'deadline_regular' => Carbon::now()->addDays(60)->toDateString(),
            ],
            [
                'id' => 2,
                'name' => 'Before Range',
                'categories' => ['short_film'],
                'event_start_date' => '2026-09-10',
                'deadline_regular' => Carbon::now()->addDays(60)->toDateString(),
            ],
            [
                'id' => 3,
                'name' => 'After Range',
                'categories' => ['short_film'],
                'event_start_date' => '2027-06-20',
                'deadline_regular' => Carbon::now()->addDays(60)->toDateString(),
            ],
        ]);

        $results = $this->makeService()->search([
            'startDate' => '2026-12-01',
            'endDate' => '2027-03-01',
            'dateField' => 'opening_date',
            'category' => 'short_film',
        ]);

        // Only "In Range" should survive the post-fetch filter.
        $this->assertCount(1, $results);
        $this->assertSame('In Range', $results->first()->name);
    }

    public function test_search_keeps_festival_without_event_start_date_when_dateField_is_opening_date(): void
    {
        // Defensive: if the API omits event_start_date we can't filter it
        // out, so we keep it (better to show possibly-out-of-range data
        // than to drop a real match silently).
        $this->fakeApi([
            [
                'id' => 1,
                'name' => 'No Apertura',
                'categories' => ['short_film'],
                'event_start_date' => null,
                'deadline_regular' => Carbon::now()->addDays(60)->toDateString(),
            ],
        ]);

        $results = $this->makeService()->search([
            'startDate' => '2026-12-01',
            'endDate' => '2027-03-01',
            'dateField' => 'opening_date',
        ]);

        $this->assertCount(1, $results);
        $this->assertSame('No Apertura', $results->first()->name);
    }

    public function test_search_does_not_filter_by_date_when_dateField_is_deadline(): void
    {
        // The deadline_* filter is honoured by the API, so we trust it
        // and do NOT post-filter on event_start_date. We do still keep
        // the past-deadline filter.
        $this->fakeApi([
            [
                'id' => 1,
                'name' => 'Before Apertura but valid',
                'categories' => ['short_film'],
                'event_start_date' => '2026-09-10',
                'deadline_regular' => Carbon::now()->addDays(60)->toDateString(),
            ],
        ]);

        $results = $this->makeService()->search([
            'startDate' => '2026-12-01',
            'endDate' => '2027-03-01',
            'dateField' => 'deadline',
        ]);

        // Should be kept — dateField=deadline doesn't trigger the
        // event_start_date post-filter.
        $this->assertCount(1, $results);
    }

    public function test_search_orders_results_by_event_start_date_when_dateField_is_opening_date(): void
    {
        $this->fakeApi([
            [
                'id' => 1,
                'name' => 'Apertura más lejana',
                'categories' => ['short_film'],
                'event_start_date' => '2027-02-15',
                'deadline_regular' => Carbon::now()->addDays(120)->toDateString(),
            ],
            [
                'id' => 2,
                'name' => 'Apertura más próxima',
                'categories' => ['short_film'],
                'event_start_date' => '2026-12-05',
                'deadline_regular' => Carbon::now()->addDays(120)->toDateString(),
            ],
            [
                'id' => 3,
                'name' => 'Apertura intermedia',
                'categories' => ['short_film'],
                'event_start_date' => '2027-01-20',
                'deadline_regular' => Carbon::now()->addDays(120)->toDateString(),
            ],
        ]);

        $results = $this->makeService()->search([
            'startDate' => '2026-12-01',
            'endDate' => '2027-03-01',
            'dateField' => 'opening_date',
        ]);

        // Nearest first.
        $this->assertSame('Apertura más próxima', $results[0]->name);
        $this->assertSame('Apertura intermedia', $results[1]->name);
        $this->assertSame('Apertura más lejana', $results[2]->name);
    }

    public function test_search_orders_results_by_deadline_when_dateField_is_deadline(): void
    {
        $this->fakeApi([
            [
                'id' => 1,
                'name' => 'Deadline lejano',
                'categories' => ['short_film'],
                'deadline_regular' => Carbon::now()->addDays(180)->toDateString(),
                'event_start_date' => null,
            ],
            [
                'id' => 2,
                'name' => 'Deadline cercano',
                'categories' => ['short_film'],
                'deadline_regular' => Carbon::now()->addDays(15)->toDateString(),
                'event_start_date' => null,
            ],
            [
                'id' => 3,
                'name' => 'Deadline medio',
                'categories' => ['short_film'],
                'deadline_regular' => Carbon::now()->addDays(60)->toDateString(),
                'event_start_date' => null,
            ],
        ]);

        $results = $this->makeService()->search([
            'dateField' => 'deadline',
        ]);

        $this->assertSame('Deadline cercano', $results[0]->name);
        $this->assertSame('Deadline medio', $results[1]->name);
        $this->assertSame('Deadline lejano', $results[2]->name);
    }

    public function test_search_pushes_festivals_without_date_to_the_end(): void
    {
        $this->fakeApi([
            [
                'id' => 1,
                'name' => 'Sin fecha',
                'categories' => ['short_film'],
                'event_start_date' => null,
                'deadline_regular' => Carbon::now()->addDays(60)->toDateString(),
            ],
            [
                'id' => 2,
                'name' => 'Con fecha',
                'categories' => ['short_film'],
                'event_start_date' => '2027-01-15',
                'deadline_regular' => Carbon::now()->addDays(60)->toDateString(),
            ],
        ]);

        $results = $this->makeService()->search([
            'dateField' => 'opening_date',
        ]);

        // The one with a real event_start_date comes first.
        $this->assertSame('Con fecha', $results[0]->name);
        $this->assertSame('Sin fecha', $results[1]->name);
    }

    public function test_details_returns_enriched_festival_with_real_submission_url(): void
    {
        // The list endpoint often returns null or a wrong submission_url.
        // details() must hit the detail endpoint and return a DTO whose
        // bestUrl() resolves to the real organizer URL — the bug fix that
        // stops users seeing "Private Project" on every card.
        Http::fake([
            'https://festivalapi.com/v1/festivals/42/' => Http::response([
                'id' => 42,
                'name' => 'Sitges Film Festival',
                'categories' => ['feature'],
                'submission_url' => 'https://sitgesfilmfestival.com/submit',
                'website' => 'https://sitgesfilmfestival.com',
            ], 200),
        ]);

        $input = new FestivalData(
            apiId: 42,
            name: 'Sitges Film Festival',
            categories: ['feature'],
            primaryCategory: 'feature',
            country: 'Spain',
            city: null,
            state: null,
            genres: [],
            deadline: null,
            eventStartDate: null,
            regularFee: null,
            submissionUrl: null,  // list endpoint didn't return one
            website: null,
            compositeScore: null,
        );

        $enriched = $this->makeService()->details($input);

        $this->assertSame('https://sitgesfilmfestival.com/submit', $enriched->submissionUrl);
        $this->assertSame('https://sitgesfilmfestival.com/submit', $enriched->bestUrl());
    }

    public function test_details_caches_response_for_twenty_four_hours(): void
    {
        // Second call must NOT hit the API — we cached on the first.
        Http::fake([
            'https://festivalapi.com/v1/festivals/42/' => Http::response([
                'id' => 42,
                'name' => 'Sitges',
                'categories' => ['feature'],
                'submission_url' => 'https://sitgesfilmfestival.com/submit',
                'website' => '',
            ], 200),
        ]);

        $input = new FestivalData(
            apiId: 42, name: 'Sitges', categories: [], primaryCategory: null,
            country: null, city: null, state: null, genres: [],
            deadline: null, eventStartDate: null, regularFee: null,
            submissionUrl: null, website: null, compositeScore: null,
        );

        $this->makeService()->details($input);
        $this->makeService()->details($input);
        $this->makeService()->details($input);

        Http::assertSentCount(1);
    }

    public function test_details_returns_input_unchanged_when_api_call_fails(): void
    {
        // If the detail endpoint is down, the card must still render with
        // the fallback URL (FilmFreeway search by name). We don't lose
        // the festival — we just lose the upgrade to the real URL.
        Http::fake([
            'https://festivalapi.com/v1/festivals/42/' => Http::response(
                ['detail' => 'Server error'], 500
            ),
        ]);

        $input = new FestivalData(
            apiId: 42, name: 'Sitges', categories: [], primaryCategory: null,
            country: null, city: null, state: null, genres: [],
            deadline: null, eventStartDate: null, regularFee: null,
            submissionUrl: null, website: null, compositeScore: null,
        );

        $result = $this->makeService()->details($input);

        // Returned the same DTO (same apiId, same name) — bestUrl()
        // falls back to the FilmFreeway search URL.
        $this->assertSame(42, $result->apiId);
        $this->assertSame(
            'https://filmfreeway.com/search?q=Sitges',
            $result->bestUrl()
        );
    }
}
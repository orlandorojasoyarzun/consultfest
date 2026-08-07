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
}
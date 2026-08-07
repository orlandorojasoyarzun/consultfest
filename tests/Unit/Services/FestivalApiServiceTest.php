<?php

namespace Tests\Unit\Services;

use App\Services\FestivalApiService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class FestivalApiServiceTest extends TestCase
{
    use RefreshDatabase;

    private function makeService(): FestivalApiService
    {
        // The constructor reads from config() — config defaults are fine
        // for tests since Http::fake() intercepts before any real call.
        return new FestivalApiService();
    }

    public function test_service_can_be_instantiated(): void
    {
        $this->assertInstanceOf(FestivalApiService::class, $this->makeService());
    }

    public function test_search_festivals_sends_bearer_token(): void
    {
        Http::fake([
            'https://festivalapi.com/v1/festivals*' => Http::response([
                'count' => 0,
                'results' => [],
            ], 200),
        ]);

        $this->makeService()->searchFestivals(['category' => 'short_film']);

        Http::assertSent(function ($request) {
            $auth = $request->header('Authorization');
            return str_starts_with($request->url(), 'https://festivalapi.com/v1/festivals')
                && is_array($auth)
                && str_starts_with($auth[0] ?? '', 'Bearer ');
        });
    }

    public function test_search_festivals_returns_results_array(): void
    {
        Http::fake([
            'https://festivalapi.com/v1/festivals*' => Http::response([
                'count' => 1,
                'results' => [[
                    'id' => 1,
                    'name' => 'Sundance',
                    'categories' => ['feature'],
                    'genres' => ['drama'],
                ]],
            ], 200),
        ]);

        $result = $this->makeService()->searchFestivals(['category' => 'feature']);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('results', $result);
        $this->assertCount(1, $result['results']);
        $this->assertSame('Sundance', $result['results'][0]['name']);
    }

    public function test_search_festivals_returns_empty_on_http_error(): void
    {
        Http::fake([
            'https://festivalapi.com/v1/festivals*' => Http::response(['detail' => 'Invalid API key'], 401),
        ]);

        $result = $this->makeService()->searchFestivals();

        $this->assertSame([], $result);
    }

    public function test_search_festivals_returns_empty_on_5xx(): void
    {
        Http::fake([
            'https://festivalapi.com/v1/festivals*' => Http::response(['detail' => 'Server error'], 500),
        ]);

        $result = $this->makeService()->searchFestivals();

        $this->assertSame([], $result);
    }

    public function test_search_festivals_returns_empty_on_timeout(): void
    {
        Http::fake([
            'https://festivalapi.com/v1/festivals*' => function () {
                throw new \Illuminate\Http\Client\ConnectionException('timeout');
            },
        ]);

        $result = $this->makeService()->searchFestivals();

        $this->assertSame([], $result);
    }

    public function test_search_festivals_passes_filters_as_query_string(): void
    {
        Http::fake([
            'https://festivalapi.com/v1/festivals*' => Http::response(['count' => 0, 'results' => []], 200),
        ]);

        $this->makeService()->searchFestivals([
            'category' => 'short_film',
            'country' => 'United States',
            'deadline_after' => '2026-08-01',
        ]);

        Http::assertSent(function ($request) {
            $url = $request->url();
            return str_contains($url, 'category=short_film')
                && (str_contains($url, 'country=United+States') || str_contains($url, 'country=United%20States'))
                && str_contains($url, 'deadline_after=2026-08-01');
        });
    }

    public function test_search_festivals_returns_empty_on_empty_response(): void
    {
        Http::fake([
            'https://festivalapi.com/v1/festivals*' => Http::response('', 200),
        ]);

        $result = $this->makeService()->searchFestivals();

        $this->assertSame([], $result);
    }
}
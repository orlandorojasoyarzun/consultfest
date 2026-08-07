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

    public function test_results_renders_with_api_data(): void
    {
        Http::fake([
            'https://festivalapi.com/v1/festivals*' => Http::response([
                'count' => 3,
                'results' => $this->fakeFestivals(3),
            ], 200),
        ]);

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
        ]);

        Livewire::test(FestivalResults::class)
            ->assertSee('No hay festivales');
    }

    public function test_results_shows_empty_state_on_401(): void
    {
        Http::fake([
            'https://festivalapi.com/v1/festivals*' => Http::response(['detail' => 'Invalid API key'], 401),
        ]);

        Livewire::test(FestivalResults::class)
            ->assertSee('No hay festivales');
    }

    public function test_results_pagination_works_with_api_data(): void
    {
        Http::fake([
            'https://festivalapi.com/v1/festivals*' => Http::response([
                'count' => 25,
                'results' => $this->fakeFestivals(25),
            ], 200),
        ]);

        $component = Livewire::test(FestivalResults::class);
        $component->assertSee('25 festivales', false);
        $component->assertSee('Festival 10');

        $component->call('gotoPage', 2);
        $component->assertSee('Festival 11');
        $component->assertSee('Festival 20');
        $component->assertSet('paginators.page', 2);
    }

    public function test_results_shows_external_link_when_no_local_festival(): void
    {
        Http::fake([
            'https://festivalapi.com/v1/festivals*' => Http::response([
                'count' => 1,
                'results' => [[
                    'id' => 99,
                    'name' => 'External Fest',
                    'categories' => ['feature'],
                    'submission_url' => 'https://filmfreeway.com/external',
                ]],
            ], 200),
        ]);

        Livewire::test(FestivalResults::class)
            ->assertSee('External Fest')
            ->assertSee('https://filmfreeway.com/external', false);
    }

    public function test_results_filters_past_deadlines_out(): void
    {
        Http::fake([
            'https://festivalapi.com/v1/festivals*' => Http::response([
                'count' => 2,
                'results' => [
                    ['id' => 1, 'name' => 'Closed', 'categories' => ['feature'], 'deadline_regular' => '2020-01-01'],
                    ['id' => 2, 'name' => 'Open', 'categories' => ['feature'], 'deadline_regular' => Carbon::now()->addDays(30)->toDateString()],
                ],
            ], 200),
        ]);

        Livewire::test(FestivalResults::class)
            ->assertSee('Open')
            ->assertDontSee('Closed');
    }

    public function test_results_shows_genre_chips(): void
    {
        Http::fake([
            'https://festivalapi.com/v1/festivals*' => Http::response([
                'count' => 1,
                'results' => [[
                    'id' => 1,
                    'name' => 'Genre Fest',
                    'categories' => ['feature'],
                    'genres' => ['drama', 'comedy', 'horror'],
                ]],
            ], 200),
        ]);

        Livewire::test(FestivalResults::class)
            ->assertSee('drama')
            ->assertSee('comedy')
            ->assertSee('horror');
    }

    public function test_results_search_dispatches_through_filters(): void
    {
        Http::fake([
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
        Http::fake([
            'https://festivalapi.com/v1/festivals*' => Http::response([
                'count' => 5000,
                'results' => $this->fakeFestivals(100),
            ], 200),
        ]);

        Livewire::test(FestivalResults::class)
            ->assertSee('100 festivales', false)
            ->assertSee('primeros 100 resultados', false);
    }
}
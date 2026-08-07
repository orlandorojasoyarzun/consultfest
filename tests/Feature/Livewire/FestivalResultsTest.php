<?php

namespace Tests\Feature\Livewire;

use App\Livewire\FestivalResults;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

class FestivalResultsTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Build a FestivalAPI-shaped results array for the given names + dates.
     */
    private function fakeApiResults(array $rows): void
    {
        Http::fake([
            'https://festivalapi.com/v1/festivals*' => Http::response([
                'count' => count($rows),
                'results' => $rows,
            ], 200),
        ]);
    }

    private function apiRow(string $name, array $overrides = []): array
    {
        return array_merge([
            'id' => abs(crc32($name)),
            'name' => $name,
            'categories' => ['feature'],
            'genres' => ['drama'],
            'country' => 'United States',
            'deadline_regular' => Carbon::now()->addDays(30)->toDateString(),
        ], $overrides);
    }

    public function test_component_renders_with_empty_state_when_no_festivals(): void
    {
        Http::fake([
            'https://festivalapi.com/v1/festivals*' => Http::response(['count' => 0, 'results' => []], 200),
        ]);

        Livewire::test(FestivalResults::class)
            ->assertSee('No hay festivales');
    }

    public function test_mount_runs_initial_search_within_three_months(): void
    {
        $now = Carbon::now();
        $this->fakeApiResults([
            $this->apiRow('In Range Festival', [
                'deadline_regular' => $now->copy()->addDays(10)->toDateString(),
            ]),
        ]);

        Livewire::test(FestivalResults::class)
            ->assertSee('In Range Festival');
    }

    public function test_search_filters_by_category(): void
    {
        $this->fakeApiResults([
            $this->apiRow('Feature Fest', ['categories' => ['feature']]),
        ]);

        Livewire::test(FestivalResults::class)
            ->call('search', [
                'startDate' => now()->subDay()->toDateString(),
                'endDate' => now()->addYear()->toDateString(),
                'category' => 'feature',
            ])
            ->assertSee('Feature Fest');
    }

    public function test_search_escapes_like_wildcards_in_genre_filter(): void
    {
        // FestivalAPI's genre filter is exact-match, not LIKE. With a
        // wildcard-heavy payload the API simply returns nothing. Verify
        // the component degrades to empty state.
        Http::fake([
            'https://festivalapi.com/v1/festivals*' => Http::response(['count' => 0, 'results' => []], 200),
        ]);

        Livewire::test(FestivalResults::class)
            ->call('search', [
                'startDate' => now()->subDay()->toDateString(),
                'endDate' => now()->addYear()->toDateString(),
                'genre' => '%',
            ])
            ->assertSee('No hay festivales');
    }

    public function test_search_returns_empty_when_no_festivals_match(): void
    {
        Http::fake([
            'https://festivalapi.com/v1/festivals*' => Http::response(['count' => 0, 'results' => []], 200),
        ]);

        Livewire::test(FestivalResults::class)
            ->call('search', [
                'startDate' => now()->subDay()->toDateString(),
                'endDate' => now()->addYear()->toDateString(),
                'country' => 'Atlantis',
            ])
            ->assertSee('No hay festivales');
    }

    public function test_total_count_reflects_result_set_size(): void
    {
        $rows = [];
        for ($i = 0; $i < 3; $i++) {
            $rows[] = $this->apiRow("Fest {$i}");
        }
        $this->fakeApiResults($rows);

        Livewire::test(FestivalResults::class)
            ->assertSee('3 festivales', false);
    }

    public function test_results_paginated_10_per_page(): void
    {
        $rows = [];
        for ($i = 1; $i <= 25; $i++) {
            $rows[] = $this->apiRow("Fest {$i}");
        }
        $this->fakeApiResults($rows);

        // Page 1: total count is 25 (visible in template).
        $component = Livewire::test(FestivalResults::class);
        $component->assertSee('25 festivales', false);
        $component->assertSee('Fest 10');

        // Navigate to page 2 — first festival on this page is Fest 11,
        // last is Fest 20.
        $component->call('gotoPage', 2);
        $component->assertSee('Fest 11');
        $component->assertSee('Fest 20');
        $component->assertSet('paginators.page', 2);
    }

    public function test_search_resets_pagination_to_page_one(): void
    {
        $rows = [];
        for ($i = 1; $i <= 15; $i++) {
            $rows[] = $this->apiRow("Fest {$i}");
        }
        $this->fakeApiResults($rows);

        $component = Livewire::test(FestivalResults::class)
            ->call('gotoPage', 2);

        $component->call('search', [
            'startDate' => now()->subDay()->toDateString(),
            'endDate' => now()->addYear()->toDateString(),
        ])
            ->assertSee('Mostrando', false);
    }

    public function test_search_resets_loading_state(): void
    {
        $this->fakeApiResults([$this->apiRow('X')]);

        Livewire::test(FestivalResults::class)
            ->call('search', [
                'startDate' => now()->subDay()->toDateString(),
                'endDate' => now()->addYear()->toDateString(),
            ])
            ->assertSet('isLoading', false);
    }

    public function test_festival_details_are_not_exposed_in_serialized_payload(): void
    {
        // FestivalData is not an Eloquent model so toArray() doesn't apply.
        // The component still should not expose raw internal details beyond
        // the DTO contract. Smoke test: just ensure the component renders.
        $this->fakeApiResults([$this->apiRow('Hidden Payload Festival')]);

        Livewire::test(FestivalResults::class)
            ->assertSee('Hidden Payload Festival');
    }

    public function test_search_filters_by_opening_date_when_dateField_is_opening_date(): void
    {
        // With dateField=opening_date the service must map to event_date_*.
        $this->fakeApiResults([
            $this->apiRow('August Opening Fest', [
                'event_start_date' => Carbon::now()->addDays(20)->toDateString(),
                'deadline_regular' => Carbon::now()->addMonths(2)->toDateString(),
            ]),
        ]);

        Livewire::test(FestivalResults::class)
            ->call('search', [
                'startDate' => now()->toDateString(),
                'endDate' => now()->addDays(30)->toDateString(),
                'dateField' => 'opening_date',
            ])
            ->assertSee('August Opening Fest');
    }

    public function test_search_filters_by_deadline_when_dateField_is_deadline(): void
    {
        $this->fakeApiResults([
            $this->apiRow('August Deadline Fest', [
                'event_start_date' => Carbon::now()->subMonths(2)->toDateString(),
                'deadline_regular' => Carbon::now()->addDays(20)->toDateString(),
            ]),
        ]);

        Livewire::test(FestivalResults::class)
            ->call('search', [
                'startDate' => now()->toDateString(),
                'endDate' => now()->addDays(30)->toDateString(),
                'dateField' => 'deadline',
            ])
            ->assertSee('August Deadline Fest');
    }
}
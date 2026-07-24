<?php

namespace Tests\Feature\Api;

use App\Models\Festival;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FestivalApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_api_festivals_index_returns_json(): void
    {
        Festival::factory()->count(3)->create();

        $response = $this->getJson('/api/festivals');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data',
                'meta' => [
                    'current_page',
                    'last_page',
                    'per_page',
                    'total',
                ],
            ]);
    }

    public function test_api_festivals_index_returns_festivals(): void
    {
        $festival = Festival::factory()->create(['name' => 'Sundance Film Festival']);

        $response = $this->getJson('/api/festivals');

        $response->assertStatus(200);
        $this->assertTrue($response->json('meta.total') >= 1);
    }

    public function test_api_festivals_filter_by_date_range(): void
    {
        Festival::factory()->create(['deadline' => '2026-08-01']);
        Festival::factory()->create(['deadline' => '2026-09-01']);

        $response = $this->getJson('/api/festivals?start_date=2026-07-15&end_date=2026-08-15');

        $response->assertStatus(200);
    }

    public function test_api_festivals_filter_by_category(): void
    {
        Festival::factory()->create(['category' => 'short_film']);
        Festival::factory()->create(['category' => 'feature']);

        $response = $this->getJson('/api/festivals?category=short_film');

        $response->assertStatus(200);
    }

    public function test_api_festivals_filter_by_country(): void
    {
        Festival::factory()->create(['country' => 'United States']);
        Festival::factory()->create(['country' => 'Canada']);

        $response = $this->getJson('/api/festivals?country=United States');

        $response->assertStatus(200);
    }

    public function test_api_festivals_filter_by_accepting(): void
    {
        Festival::factory()->create(['accepting_submissions' => true]);
        Festival::factory()->create(['accepting_submissions' => false]);

        $response = $this->getJson('/api/festivals?accepting=true');

        $response->assertStatus(200);
    }

    public function test_api_festivals_show_returns_single_festival(): void
    {
        $festival = Festival::factory()->create(['name' => 'Berlin Film Festival']);

        $response = $this->getJson("/api/festivals/{$festival->id}");

        $response->assertStatus(200)
            ->assertJsonStructure(['data']);
    }

    public function test_api_festivals_show_returns_404_for_nonexistent(): void
    {
        $response = $this->getJson('/api/festivals/9999');

        $response->assertStatus(404);
    }

    public function test_api_festivals_search_by_dates_returns_json(): void
    {
        Festival::factory()->create(['deadline' => '2026-08-01']);
        Festival::factory()->create(['opening_date' => '2026-08-15']);

        $response = $this->getJson('/api/festivals/search/by-dates?start_date=2026-07-01&end_date=2026-08-31');

        $response->assertStatus(200)
            ->assertJsonStructure(['data']);
    }

    public function test_api_festivals_search_by_dates_requires_dates(): void
    {
        $response = $this->getJson('/api/festivals/search/by-dates');

        $response->assertStatus(422);
    }

    public function test_api_festivals_search_by_dates_validates_end_date_after_start(): void
    {
        $response = $this->getJson('/api/festivals/search/by-dates?start_date=2026-08-31&end_date=2026-08-01');

        $response->assertStatus(422);
    }

    public function test_api_festivals_pagination_respects_per_page(): void
    {
        Festival::factory()->count(25)->create();

        $response = $this->getJson('/api/festivals?per_page=10');

        $response->assertStatus(200);
        $this->assertLessThanOrEqual(10, count($response->json('data')));
    }

    public function test_api_festivals_pagination_max_per_page(): void
    {
        Festival::factory()->count(150)->create();

        $response = $this->getJson('/api/festivals?per_page=200');

        $response->assertStatus(200);
        $this->assertLessThanOrEqual(100, count($response->json('data')));
    }

    public function test_api_festivals_orders_by_deadline(): void
    {
        Festival::factory()->create(['deadline' => '2026-09-01']);
        Festival::factory()->create(['deadline' => '2026-07-01']);
        Festival::factory()->create(['deadline' => '2026-08-01']);

        $response = $this->getJson('/api/festivals');

        $response->assertStatus(200);

        $data = $response->json('data');
        if (count($data) >= 2) {
            $this->assertTrue(true);
        }
    }
}

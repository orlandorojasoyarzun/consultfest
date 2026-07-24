<?php

namespace Tests\Unit\Services;

use App\Models\Festival;
use App\Services\FestivalApiService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FestivalApiServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_service_can_be_instantiated(): void
    {
        $service = new FestivalApiService();
        $this->assertInstanceOf(FestivalApiService::class, $service);
    }

    public function test_sync_festivals_returns_array(): void
    {
        $service = new FestivalApiService();
        $result = $service->syncFestivals();

        $this->assertIsArray($result);
        $this->assertArrayHasKey('synced', $result);
    }

    public function test_sync_festival_details_returns_array_on_failure(): void
    {
        $service = new FestivalApiService();
        $result = $service->syncFestivalDetails(999999);

        $this->assertNull($result);
    }

    public function test_get_scored_festivals_returns_array(): void
    {
        $service = new FestivalApiService();
        $result = $service->getScoredFestivals();

        $this->assertIsArray($result);
    }

    public function test_search_festivals_returns_array(): void
    {
        $service = new FestivalApiService();
        $result = $service->searchFestivals(['category' => 'short_film']);

        $this->assertIsArray($result);
    }

    public function test_search_festivals_with_filters(): void
    {
        $service = new FestivalApiService();
        $result = $service->searchFestivals([
            'category' => 'feature',
            'country' => 'United States',
            'fee_max' => 100,
        ]);

        $this->assertIsArray($result);
    }

    public function test_search_festivals_with_deadline_filter(): void
    {
        $service = new FestivalApiService();
        $result = $service->searchFestivals([
            'deadline_before' => '2026-12-31',
        ]);

        $this->assertIsArray($result);
    }

    public function test_search_festivals_with_text_query(): void
    {
        $service = new FestivalApiService();
        $result = $service->searchFestivals([
            'q' => 'Sundance',
        ]);

        $this->assertIsArray($result);
    }
}

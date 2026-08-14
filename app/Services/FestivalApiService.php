<?php

namespace App\Services;

use App\Models\Festival;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class FestivalApiService
{
    private string $apiKey;
    private string $baseUrl;
    private int $timeout;

    public function __construct()
    {
        $this->apiKey = config('festivalapi.api_key');
        $this->baseUrl = config('festivalapi.base_url');
        $this->timeout = config('festivalapi.timeout', 30);
    }

    public function syncFestivals(int $perPage = 100): array
    {
        $synced = 0;
        $page = 1;

        do {
            try {
                $response = Http::timeout($this->timeout)
                    ->withHeaders([
                        'Authorization' => 'Bearer ' . $this->apiKey,
                        'Accept' => 'application/json',
                    ])
                    ->get("{$this->baseUrl}/festivals", [
                        'page' => $page,
                        'per_page' => $perPage,
                    ]);

                if ($response->successful()) {
                    $data = $response->json();
                    $results = $data['results'] ?? [];

                    foreach ($results as $festivalData) {
                        $this->upsertFestival($festivalData);
                        $synced++;
                    }

                    $totalPages = $data['total_pages'] ?? 1;
                    $page++;
                    $hasMore = $page <= $totalPages;
                } else {
                    Log::error('FestivalAPI sync failed', [
                        'status' => $response->status(),
                        'body' => $response->body(),
                    ]);
                    break;
                }
            } catch (\Exception $e) {
                Log::error('FestivalAPI sync exception', ['error' => $e->getMessage()]);
                break;
            }
        } while ($hasMore ?? false);

        return ['synced' => $synced];
    }

    public function syncFestivalDetails(int $apiId): ?array
    {
        $data = $this->getFestivalDetails($apiId);
        if ($data !== null) {
            $this->upsertFestival($data, true);
        }
        return $data;
    }

    /**
     * Read-only fetch of a single festival's full detail payload.
     *
     * Costs 1 FestivalAPI credit per call (no batch endpoint). The full
     * detail is the only place we can trust `submission_url`/`website` —
     * the list endpoint returns these fields mis-mapped to other festivals
     * (verified 2026-08-09 with Almeria → AlmeriaWesternFilmFestival).
     *
     * Returns null on any error so callers degrade gracefully — the
     * caller (FestivalSearchService::details) will fall back to building
     * a search URL from the festival name.
     */
    public function getFestivalDetails(int $apiId): ?array
    {
        try {
            $response = Http::timeout($this->timeout)
                ->withHeaders([
                    'Authorization' => 'Bearer ' . $this->apiKey,
                    'Accept' => 'application/json',
                ])
                ->get("{$this->baseUrl}/festivals/{$apiId}/");

            if ($response->successful()) {
                return $response->json();
            }

            Log::warning('FestivalAPI detail non-2xx', [
                'api_id' => $apiId,
                'status' => $response->status(),
            ]);
        } catch (\Exception $e) {
            Log::error('FestivalAPI detail exception', [
                'api_id' => $apiId,
                'error' => $e->getMessage(),
            ]);
        }

        return null;
    }

    public function getScoredFestivals(int $perPage = 20): array
    {
        try {
            $response = Http::timeout($this->timeout)
                ->withHeaders([
                    'Authorization' => 'Bearer ' . $this->apiKey,
                    'Accept' => 'application/json',
                ])
                ->get("{$this->baseUrl}/festivals/scored", [
                    'per_page' => $perPage,
                ]);

            if ($response->successful()) {
                return $response->json();
            }
        } catch (\Exception $e) {
            Log::error('FestivalAPI scored festivals failed', ['error' => $e->getMessage()]);
        }

        return [];
    }

    /**
     * Live search against FestivalAPI.com.
     *
     * Used by FestivalSearchService — does NOT persist anything. The auth
     * header is mandatory: FestivalAPI returns 401 without it.
     *
     * Filter keys we accept map 1:1 to FestivalAPI's query params (see
     * FestivalSearchService::mapFilters for the component→API translation).
     */
    public function searchFestivals(array $filters = []): array
    {
        try {
            $response = Http::timeout($this->timeout)
                ->withHeaders([
                    'Authorization' => 'Bearer ' . $this->apiKey,
                    'Accept' => 'application/json',
                ])
                ->get("{$this->baseUrl}/festivals", $filters);

            if ($response->successful()) {
                return $response->json() ?? [];
            }

            Log::warning('FestivalAPI search non-2xx', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
        } catch (\Exception $e) {
            Log::error('FestivalAPI search exception', ['error' => $e->getMessage()]);
        }

        return [];
    }

    private function upsertFestival(array $data, bool $isDetail = false): void
    {
        $apiId = $data['id'] ?? $data['festival_id'] ?? null;

        if (!$apiId) {
            return;
        }

        $details = $isDetail ? $data : ($data['details'] ?? []);

        Festival::updateOrCreate(
            ['api_id' => $apiId],
            [
                'name' => $data['name'] ?? 'Unknown',
                'category' => $data['category'] ?? $details['category'] ?? null,
                'country' => $data['country'] ?? $details['country'] ?? null,
                'deadline' => isset($data['deadline']) ? Carbon::parse($data['deadline']) : null,
                'opening_date' => isset($data['opening_date']) ? Carbon::parse($data['opening_date']) : null,
                'submission_fee' => $data['submission_fee'] ?? $details['submission_fee'] ?? null,
                'accepting_submissions' => $data['accepting_submissions'] ?? $details['accepting_submissions'] ?? true,
                'festival_score' => $data['festival_score'] ?? null,
                'details' => $data,
                'last_synced_at' => now(),
            ]
        );
    }
}

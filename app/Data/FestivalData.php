<?php

namespace App\Data;

use Carbon\Carbon;

/**
 * Immutable view of a festival returned by FestivalAPI.com.
 *
 * We do NOT persist festivals locally (Estrategia A — see plan) so this DTO
 * is the contract the UI and the search service talk in. Keeps the Livewire
 * view decoupled from the raw API payload, which has been known to drift.
 *
 * Field mapping (external → DTO):
 *   id                  → apiId
 *   categories[0]       → primaryCategory (compat with existing UI)
 *   categories          → categories (full list)
 *   genres              → genres
 *   deadline_regular    → deadline
 *   event_start_date    → eventStartDate
 *   regular_fee         → regularFee
 *   composite_score     → compositeScore
 */
class FestivalData
{
    public function __construct(
        public readonly int $apiId,
        public readonly string $name,
        public readonly array $categories,
        public readonly ?string $primaryCategory,
        public readonly ?string $country,
        public readonly ?string $city,
        public readonly ?string $state,
        public readonly array $genres,
        public readonly ?Carbon $deadline,
        public readonly ?Carbon $eventStartDate,
        public readonly ?float $regularFee,
        public readonly ?string $submissionUrl,
        public readonly ?string $website,
        public readonly ?float $compositeScore,
    ) {}

    /**
     * Build a DTO from a single entry of FestivalAPI's `results` array.
     *
     * Tolerant of missing fields: any non-required column can be null.
     * Throws if `id` or `name` are missing — those are essential to render.
     */
    public static function fromApi(array $payload): self
    {
        $apiId = (int) ($payload['id'] ?? 0);
        $name = (string) ($payload['name'] ?? '');

        if ($apiId === 0 || $name === '') {
            throw new \InvalidArgumentException(
                'FestivalAPI payload missing required fields (id, name).'
            );
        }

        $categories = (array) ($payload['categories'] ?? []);
        // Strip empty entries just in case the API ever returns "" as a category.
        $categories = array_values(array_filter($categories, fn ($c) => $c !== '' && $c !== null));

        return new self(
            apiId: $apiId,
            name: $name,
            categories: $categories,
            primaryCategory: $categories[0] ?? null,
            country: isset($payload['country']) ? (string) $payload['country'] : null,
            city: isset($payload['city']) ? (string) $payload['city'] : null,
            state: isset($payload['state']) ? (string) $payload['state'] : null,
            genres: array_values((array) ($payload['genres'] ?? [])),
            deadline: self::parseDate($payload['deadline_regular'] ?? null),
            eventStartDate: self::parseDate($payload['event_start_date'] ?? null),
            regularFee: isset($payload['regular_fee']) ? (float) $payload['regular_fee'] : null,
            submissionUrl: isset($payload['submission_url']) ? (string) $payload['submission_url'] : null,
            website: isset($payload['website']) ? (string) $payload['website'] : null,
            compositeScore: isset($payload['composite_score']) ? (float) $payload['composite_score'] : null,
        );
    }

    /**
     * Whether the festival is still accepting submissions, inferred from
     * the deadline. If we don't know the deadline we default to true (open)
     * — better to show a possibly-closed festival than hide an open one.
     */
    public function isAcceptingSubmissions(): bool
    {
        if ($this->deadline === null) {
            return true;
        }
        return $this->deadline->isFuture();
    }

    /**
     * Best URL to send the user to when they click the card. Falls back
     * through submission_url → website → null.
     */
    public function bestUrl(): ?string
    {
        return $this->submissionUrl ?: $this->website;
    }

    private static function parseDate(mixed $value): ?Carbon
    {
        if ($value === null || $value === '') {
            return null;
        }
        try {
            return Carbon::parse((string) $value);
        } catch (\Throwable) {
            return null;
        }
    }
}
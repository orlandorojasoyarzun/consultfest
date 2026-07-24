<?php

namespace Database\Factories;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\Factory;

class FestivalFactory extends Factory
{
    public function definition(): array
    {
        $categories = ['short_film', 'feature', 'documentary', 'animation', 'horror', 'sci_fi', 'comedy', 'experimental'];
        $countries = ['United States', 'Canada', 'United Kingdom', 'France', 'Germany', 'Italy', 'Spain', 'Australia', 'Japan', 'India'];

        return [
            'api_id' => abs(crc32(uniqid())),
            'name' => fake()->company() . ' Film Festival',
            'category' => fake()->randomElement($categories),
            'country' => fake()->randomElement($countries),
            'deadline' => Carbon::parse('+' . fake()->numberBetween(1, 180) . ' days'),
            'opening_date' => Carbon::parse('-' . fake()->numberBetween(1, 90) . ' days'),
            'submission_fee' => fake()->randomFloat(2, 0, 150),
            'accepting_submissions' => fake()->boolean(80),
            'festival_score' => fake()->optional()->numberBetween(1, 100),
            'details' => [
                'genres' => fake()->randomElements(['drama', 'comedy', 'horror', 'sci_fi', 'documentary', 'animation'], 2),
            ],
            'last_synced_at' => now(),
        ];
    }

    public function withApiId(int $apiId): static
    {
        return $this->state(fn (array $attributes) => ['api_id' => $apiId]);
    }

    public function acceptingSubmissions(): static
    {
        return $this->state(fn (array $attributes) => ['accepting_submissions' => true]);
    }

    public function notAcceptingSubmissions(): static
    {
        return $this->state(fn (array $attributes) => ['accepting_submissions' => false]);
    }

    public function withDeadline(Carbon $date): static
    {
        return $this->state(fn (array $attributes) => ['deadline' => $date]);
    }

    public function withOpeningDate(Carbon $date): static
    {
        return $this->state(fn (array $attributes) => ['opening_date' => $date]);
    }

    public function shortFilm(): static
    {
        return $this->state(fn (array $attributes) => ['category' => 'short_film']);
    }

    public function feature(): static
    {
        return $this->state(fn (array $attributes) => ['category' => 'feature']);
    }
}

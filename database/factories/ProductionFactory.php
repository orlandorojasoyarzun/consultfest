<?php

namespace Database\Factories;

use App\Models\Production;
use App\Models\Subscriber;
use Illuminate\Database\Eloquent\Factories\Factory;

class ProductionFactory extends Factory
{
    protected $model = Production::class;

    public function definition(): array
    {
        return [
            'subscriber_id' => Subscriber::factory(),
            'title' => fake()->sentence(3),
            'synopsis' => fake()->paragraph(),
            'runtime_minutes' => fake()->numberBetween(2, 40),
            'format' => fake()->randomElement(['digital', 'film', 'any']),
            'country' => fake()->randomElement(['Mexico', 'Spain', 'Argentina', 'Colombia', 'Chile']),
            'production_year' => fake()->numberBetween(2018, 2026),
            'category' => 'short_film',
            'genres' => ['drama'],
            'status' => 'draft',
        ];
    }

    public function active(): static
    {
        return $this->state(fn () => ['status' => 'active']);
    }
}
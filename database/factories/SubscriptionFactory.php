<?php

namespace Database\Factories;

use App\Models\Festival;
use App\Models\Subscriber;
use App\Models\Subscription;
use Illuminate\Database\Eloquent\Factories\Factory;

class SubscriptionFactory extends Factory
{
    protected $model = Subscription::class;

    public function definition(): array
    {
        return [
            'subscriber_id' => Subscriber::factory(),
            'festival_id' => Festival::factory(),
            'notification_type' => fake()->randomElement(['opening', 'deadline', 'both']),
            'notified_opening' => false,
            'notified_deadline' => false,
        ];
    }
}

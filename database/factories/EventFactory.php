<?php

namespace Database\Factories;

use App\Models\Event;
use App\Models\Organisation;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class EventFactory extends Factory
{
    protected $model = Event::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->words(3, true),
            'organisation_id' => Organisation::factory(),
            'order_token' => Str::random(32),
            'waiter_token' => Str::random(32),
        ];
    }
}

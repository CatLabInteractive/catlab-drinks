<?php

namespace Database\Factories;

use App\Models\Event;
use App\Models\Order;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class OrderFactory extends Factory
{
    protected $model = Order::class;

    public function definition(): array
    {
        return [
            'uid' => Str::uuid(),
            'event_id' => Event::factory(),
            'status' => Order::STATUS_PENDING,
            'assigned_device_id' => null,
            'location' => 'Table ' . $this->faker->numberBetween(1, 50),
        ];
    }
}

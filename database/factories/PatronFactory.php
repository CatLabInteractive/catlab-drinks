<?php

namespace Database\Factories;

use App\Models\Event;
use App\Models\Patron;
use Illuminate\Database\Eloquent\Factories\Factory;

class PatronFactory extends Factory
{
    protected $model = Patron::class;

    public function definition(): array
    {
        return [
            'event_id' => Event::factory(),
            'name' => $this->faker->firstName(),
            'table_id' => null,
        ];
    }
}

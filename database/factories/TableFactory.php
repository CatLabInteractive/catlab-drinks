<?php

namespace Database\Factories;

use App\Models\Event;
use App\Models\Table;
use Illuminate\Database\Eloquent\Factories\Factory;

class TableFactory extends Factory
{
    protected $model = Table::class;

    public function definition(): array
    {
        return [
            'event_id' => Event::factory(),
            'table_number' => $this->faker->unique()->numberBetween(1, 10000),
            'name' => 'Table ' . $this->faker->numberBetween(1, 100),
        ];
    }
}

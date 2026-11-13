<?php

namespace Database\Factories;

use App\Models\Event;
use App\Models\MenuHeading;
use Illuminate\Database\Eloquent\Factories\Factory;

class MenuHeadingFactory extends Factory
{
    protected $model = MenuHeading::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->word(),
            'event_id' => Event::factory(),
        ];
    }
}

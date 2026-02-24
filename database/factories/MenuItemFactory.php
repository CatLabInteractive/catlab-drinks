<?php

namespace Database\Factories;

use App\Models\Category;
use App\Models\Event;
use App\Models\MenuItem;
use Illuminate\Database\Eloquent\Factories\Factory;

class MenuItemFactory extends Factory
{
    protected $model = MenuItem::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->word(),
            'price' => $this->faker->numberBetween(100, 2000),
            'event_id' => Event::factory(),
            'category_id' => Category::factory(),
        ];
    }
}

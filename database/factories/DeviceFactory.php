<?php

namespace Database\Factories;

use App\Models\Device;
use App\Models\Organisation;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class DeviceFactory extends Factory
{
    protected $model = Device::class;

    public function definition(): array
    {
        return [
            'uid' => Str::uuid(),
            'name' => $this->faker->words(2, true) . ' POS',
            'organisation_id' => Organisation::factory(),
            'secret_key' => 'test-secret-' . $this->faker->randomNumber(4),
            'last_ping' => Carbon::now(),
            'allow_remote_orders' => true,
            'allow_live_orders' => true,
            'category_filter_id' => null,
        ];
    }

    public function offline(): self
    {
        return $this->state(['last_ping' => null]);
    }
}

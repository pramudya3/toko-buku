<?php

namespace Database\Factories;

use App\Models\SalesChannel;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SalesChannel>
 */
class SalesChannelFactory extends Factory
{
    protected $model = SalesChannel::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'code' => fake()->unique()->lexify('channel????'),
            'name' => fake()->unique()->words(2, true),
            'is_active' => true,
            'sort_order' => 0,
        ];
    }
}

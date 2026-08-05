<?php

namespace Database\Factories;

use App\Models\Category;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Category>
 */
class CategoryFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $words = fake()->unique()->words(2, true);
        $nama = is_array($words) ? implode(' ', $words) : $words;

        return [
            'nama' => $nama,
            'slug' => Str::slug($nama),
        ];
    }
}

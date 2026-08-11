<?php

namespace Database\Factories;

use App\Models\Book;
use App\Models\BookEdition;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BookEdition>
 */
class BookEditionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'book_id' => Book::factory(),
            'cetakan_ke' => 1,
            'nama' => fake()->optional(0.8, null)->words(3, true),
            'harga_beli' => fake()->numberBetween(10, 200) * 1000,
            'harga_jual' => fake()->numberBetween(25, 350) * 1000,
            'is_active' => true,
        ];
    }

    public function cetakanKe(int $ke): static
    {
        return $this->state(fn () => ['cetakan_ke' => $ke]);
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }
}

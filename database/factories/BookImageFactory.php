<?php

namespace Database\Factories;

use App\Models\Book;
use App\Models\BookImage;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BookImage>
 */
class BookImageFactory extends Factory
{
    public function definition(): array
    {
        return [
            'book_id' => Book::factory(),
            'image_url' => fake()->imageUrl(400, 600, 'book'),
            'urutan' => fake()->numberBetween(0, 10),
        ];
    }
}

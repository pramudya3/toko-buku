<?php

namespace Database\Factories;

use App\Models\Article;
use App\Models\ArticleCategory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Article>
 */
class ArticleFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $judul = fake()->sentence(5);

        return [
            'judul' => $judul,
            'slug' => Str::slug($judul).'-'.Str::lower(Str::random(6)),
            'article_category_id' => ArticleCategory::factory(),
            'penulis' => 'Tim Penerbit',
            'ringkasan' => fake()->paragraph(),
            'isi' => implode("\n", [
                '<p>'.e(fake()->paragraph(4)).'</p>',
                '<p>'.e(fake()->paragraph(4)).'</p>',
                '<blockquote><p>'.e(fake()->sentence(8)).'</p></blockquote>',
                '<p>'.e(fake()->paragraph(3)).'</p>',
            ]),
            'motif' => fake()->randomElement(array_keys(Article::motifOptions())),
            'cover_url' => null,
            'is_active' => true,
            'published_at' => fake()->dateTimeBetween('-2 months', 'now')->format('Y-m-d'),
        ];
    }

    /**
     * Artikel yang belum terbit (disimpan di draft).
     */
    public function draft(): static
    {
        return $this->state(fn (): array => [
            'is_active' => false,
            'published_at' => null,
        ]);
    }
}

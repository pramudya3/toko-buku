<?php

namespace Database\Factories;

use App\Enums\PromotionType;
use App\Models\Promotion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Promotion>
 */
class PromotionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'promo_name' => fake()->unique()->words(3, true),
            'promo_type' => PromotionType::Percentage,
            'discount_percentage' => fake()->numberBetween(5, 50),
            'promo_value' => null,
            'bundle_qty' => null,
            'start_date' => now()->subDay()->toDateString(),
            'end_date' => now()->addDays(7)->toDateString(),
            'is_active' => true,
        ];
    }

    /**
     * Promo persentase.
     */
    public function percentage(int $percent = 10): static
    {
        return $this->state(fn (array $attributes) => [
            'promo_type' => PromotionType::Percentage,
            'discount_percentage' => $percent,
            'promo_value' => null,
            'bundle_qty' => null,
        ]);
    }

    /**
     * Promo harga tetap.
     */
    public function fixed(int $value = 50000): static
    {
        return $this->state(fn (array $attributes) => [
            'promo_type' => PromotionType::Fixed,
            'promo_value' => $value,
            'discount_percentage' => null,
            'bundle_qty' => null,
        ]);
    }

    /**
     * Promo bundle (diskon % saat qty ≥ bundle_qty).
     */
    public function bundle(int $qty = 3, int $percent = 15): static
    {
        return $this->state(fn (array $attributes) => [
            'promo_type' => PromotionType::Bundle,
            'bundle_qty' => $qty,
            'discount_percentage' => $percent,
            'promo_value' => null,
        ]);
    }

    /**
     * Promo global.
     */
    public function global(): static
    {
        return $this->state(fn (array $attributes) => []);
    }

    /**
     * Promo nonaktif.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    /**
     * Promo kadaluarsa.
     */
    public function expired(): static
    {
        return $this->state(fn (array $attributes) => [
            'start_date' => now()->subDays(10)->toDateString(),
            'end_date' => now()->subDays(3)->toDateString(),
        ]);
    }

    /**
     * Promo belum mulai.
     */
    public function upcoming(): static
    {
        return $this->state(fn (array $attributes) => [
            'start_date' => now()->addDays(2)->toDateString(),
            'end_date' => now()->addDays(9)->toDateString(),
        ]);
    }
}

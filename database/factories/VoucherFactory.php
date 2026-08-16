<?php

namespace Database\Factories;

use App\Enums\VoucherScope;
use App\Enums\VoucherType;
use App\Models\Voucher;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Voucher>
 */
class VoucherFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'nama' => fake()->unique()->words(3, true),
            'kode' => strtoupper(fake()->unique()->bothify('VC-####')),
            'voucher_type' => VoucherType::Percentage,
            'discount_scope' => VoucherScope::Item,
            'discount_percentage' => fake()->numberBetween(5, 30),
            'discount_value' => null,
            'min_order_amount' => 0,
            'max_uses' => null,
            'max_uses_per_user' => null,
            'start_date' => now()->subDay()->toDateString(),
            'end_date' => now()->addDays(7)->toDateString(),
            'is_active' => true,
        ];
    }

    /**
     * Voucher persentase.
     */
    public function percentage(int $percent = 10): static
    {
        return $this->state(fn (array $attributes) => [
            'voucher_type' => VoucherType::Percentage,
            'discount_percentage' => $percent,
            'discount_value' => null,
        ]);
    }

    /**
     * Voucher nominal tetap.
     */
    public function fixed(int $value = 50000): static
    {
        return $this->state(fn (array $attributes) => [
            'voucher_type' => VoucherType::Fixed,
            'discount_value' => $value,
            'discount_percentage' => null,
        ]);
    }

    /**
     * Voucher target ongkos kirim.
     */
    public function ongkir(): static
    {
        return $this->state(fn (array $attributes) => [
            'discount_scope' => VoucherScope::Ongkir,
        ]);
    }

    /**
     * Voucher nonaktif.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    /**
     * Voucher kadaluarsa.
     */
    public function expired(): static
    {
        return $this->state(fn (array $attributes) => [
            'start_date' => now()->subDays(10)->toDateString(),
            'end_date' => now()->subDays(3)->toDateString(),
        ]);
    }

    /**
     * Voucher belum mulai.
     */
    public function upcoming(): static
    {
        return $this->state(fn (array $attributes) => [
            'start_date' => now()->addDays(2)->toDateString(),
            'end_date' => now()->addDays(9)->toDateString(),
        ]);
    }
}

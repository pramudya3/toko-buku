<?php

namespace Database\Factories;

use App\Enums\CustomerTier;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
            'is_admin' => false,
            'status_pelanggan' => CustomerTier::Reguler,
            'whatsapp_number' => fake()->numerify('08##########'),
        ];
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    /**
     * Indicate that the user has two-factor authentication configured.
     */
    public function withTwoFactor(): static
    {
        return $this;
    }

    /**
     * Indicate that the user is an admin (is_admin = true).
     */
    public function admin(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_admin' => true,
            'status_pelanggan' => CustomerTier::Reguler,
        ]);
    }

    /**
     * Indicate that the user is a regular customer (bukan admin).
     */
    public function customer(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_admin' => false,
            'status_pelanggan' => CustomerTier::Reguler,
        ]);
    }

    /**
     * Set tier pelanggan.
     */
    public function tier(CustomerTier $tier): static
    {
        return $this->state(fn (array $attributes) => [
            'status_pelanggan' => $tier,
        ]);
    }
}

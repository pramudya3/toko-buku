<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Akun customer demo — dibuat idempotent (updateOrCreate).
 *
 * Kredensial: jono@email.com / password — untuk mencoba alur storefront
 * (checkout, pesanan, profil) sebagai customer.
 */
class CustomerSeeder extends Seeder
{
    public function run(): void
    {
        User::updateOrCreate(
            ['email' => 'jono@email.com'],
            [
                'name' => 'Jono',
                'is_admin' => false,
                'is_active' => true,
                'password' => Hash::make('password'),
            ],
        );
    }
}

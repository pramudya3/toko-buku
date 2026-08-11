<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Akun superadmin untuk tester — dibuat idempotent (updateOrCreate).
 *
 * Kredensial dari env: TESTER_ADMIN_EMAIL / TESTER_ADMIN_PASSWORD /
 * TESTER_ADMIN_NAME (fallback bawaan). Password tidak divalidasi aturan
 * production (min 12) karena ini seeding awal — ganti setelah login.
 */
class SuperAdminSeeder extends Seeder
{
    public function run(): void
    {
        $email = env('TESTER_ADMIN_EMAIL', 'admin@tokobuku.test');

        User::updateOrCreate(
            ['email' => $email],
            [
                'name' => env('TESTER_ADMIN_NAME', 'Super Admin'),
                'is_admin' => true,
                'password' => Hash::make(env('TESTER_ADMIN_PASSWORD', 'password')),
            ],
        );
    }
}

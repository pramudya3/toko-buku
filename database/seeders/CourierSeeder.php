<?php

namespace Database\Seeders;

use App\Models\Courier;
use Illuminate\Database\Seeder;

class CourierSeeder extends Seeder
{
    /**
     * Seed ekspedisi bawaan dari config/shipping.php.
     */
    public function run(): void
    {
        foreach (config('shipping.couriers') as $code => $name) {
            Courier::firstOrCreate(['code' => $code], [
                'name' => $name,
                'is_active' => true,
            ]);
        }
    }
}

<?php

namespace Database\Seeders;

use App\Enums\PaymentMethod as PaymentMethodEnum;
use App\Models\PaymentMethod;
use Illuminate\Database\Seeder;

class PaymentMethodSeeder extends Seeder
{
    /**
     * Seed metode pembayaran bawaan dari enum PaymentMethod.
     */
    public function run(): void
    {
        $sort = 0;

        foreach (PaymentMethodEnum::options() as $code => $name) {
            PaymentMethod::firstOrCreate(['code' => $code], [
                'name' => $name,
                'is_active' => true,
                'sort_order' => ++$sort,
            ]);
        }
    }
}

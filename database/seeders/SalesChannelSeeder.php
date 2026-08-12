<?php

namespace Database\Seeders;

use App\Enums\SalesChannel as SalesChannelEnum;
use App\Models\SalesChannel;
use Illuminate\Database\Seeder;

class SalesChannelSeeder extends Seeder
{
    /**
     * Seed channel penjualan bawaan dari enum SalesChannel.
     */
    public function run(): void
    {
        $sort = 0;

        foreach (SalesChannelEnum::options() as $code => $name) {
            SalesChannel::firstOrCreate(['code' => $code], [
                'name' => $name,
                'is_active' => true,
                'sort_order' => ++$sort,
            ]);
        }
    }
}

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tier_discounts', function (Blueprint $table) {
            $table->unsignedInteger('max_qty')->nullable()->after('min_qty');
        });

        // Update existing data using PHP for cross-database compatibility
        $tiers = DB::table('tier_discounts')
            ->select('tier')
            ->distinct()
            ->pluck('tier');

        foreach ($tiers as $tier) {
            $discounts = DB::table('tier_discounts')
                ->where('tier', $tier)
                ->orderBy('min_qty')
                ->get();

            foreach ($discounts as $idx => $discount) {
                $nextDiscount = $discounts->get($idx + 1);
                $maxQty = $nextDiscount ? $nextDiscount->min_qty - 1 : null;

                DB::table('tier_discounts')
                    ->where('id', $discount->id)
                    ->update(['max_qty' => $maxQty]);
            }
        }

        // Update unique constraint
        Schema::table('tier_discounts', function (Blueprint $table) {
            $table->dropUnique(['tier', 'min_qty']);
            $table->unique(['tier', 'min_qty', 'max_qty']);
        });
    }

    public function down(): void
    {
        Schema::table('tier_discounts', function (Blueprint $table) {
            $table->dropUnique(['tier', 'min_qty', 'max_qty']);
            $table->dropColumn('max_qty');
            $table->unique(['tier', 'min_qty']);
        });
    }
};

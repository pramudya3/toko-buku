<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tier_discounts', function (Blueprint $table) {
            $table->id();
            $table->string('tier'); // reguler | bazaf | guru | reseller
            $table->unsignedInteger('min_qty'); // qty >= min_qty → diskon berlaku
            $table->unsignedInteger('discount_percent');
            $table->timestamps();

            $table->unique(['tier', 'min_qty']);
        });

        // Aturan default = perilaku lama dari config/pricing.php.
        DB::table('tier_discounts')->insert([
            ['tier' => 'reseller', 'min_qty' => 10, 'discount_percent' => 10],
            ['tier' => 'reseller', 'min_qty' => 20, 'discount_percent' => 15],
            ['tier' => 'bazaf', 'min_qty' => 10, 'discount_percent' => 5],
            ['tier' => 'bazaf', 'min_qty' => 20, 'discount_percent' => 8],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('tier_discounts');
    }
};

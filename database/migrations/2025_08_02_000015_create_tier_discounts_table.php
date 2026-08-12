<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
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
        // Catatan: tanpa data default — aturan tier discount diisi via
        // menu Tier Discount (form atau import CSV).
    }

    public function down(): void
    {
        Schema::dropIfExists('tier_discounts');
    }
};

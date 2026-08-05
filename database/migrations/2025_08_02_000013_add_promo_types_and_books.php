<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('promotions', function (Blueprint $table) {
            // percentage (diskon %), fixed (harga tetap), bundle (diskon % bila qty >= bundle_qty)
            $table->string('promo_type')->default('percentage')->after('promo_name');
            $table->unsignedInteger('promo_value')->nullable()->after('discount_percentage');
            $table->unsignedInteger('bundle_qty')->nullable()->after('promo_value');
        });

        Schema::create('promotion_book', function (Blueprint $table) {
            $table->id();
            $table->foreignId('promotion_id')->constrained()->cascadeOnDelete();
            $table->foreignId('book_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['promotion_id', 'book_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('promotion_book');

        Schema::table('promotions', function (Blueprint $table) {
            $table->dropColumn(['promo_type', 'promo_value', 'bundle_qty']);
        });
    }
};

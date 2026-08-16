<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Voucher diskon level order — dipakai customer saat checkout.
     * Quota pemakaian dicatat di tabel voucher_usages.
     */
    public function up(): void
    {
        Schema::create('vouchers', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('nama');
            $table->string('kode')->nullable()->unique();
            $table->string('voucher_type'); // percentage | fixed
            $table->integer('discount_percentage')->unsigned()->nullable();
            $table->integer('discount_value')->unsigned()->nullable();
            $table->integer('min_order_amount')->unsigned()->default(0);
            $table->integer('max_uses')->unsigned()->nullable();
            $table->integer('max_uses_per_user')->unsigned()->nullable();
            $table->date('start_date');
            $table->date('end_date');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['is_active', 'end_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vouchers');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Konsinyasi (titip jual) — barang diserahkan ke mitra tier Bazaf,
     * baru dianggap terjual saat mitra melaporkan laku. Piutang mengikuti
     * laporan laku; sisa barang bisa diretur.
     */
    public function up(): void
    {
        // Serah terima barang ke mitra — stok pindah dari toko ke titipan.
        Schema::create('consignment_deliveries', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('customer_id')->constrained('users');
            $table->date('delivery_date');
            $table->text('notes')->nullable();
            $table->foreignUuid('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['customer_id', 'delivery_date']);
        });

        Schema::create('consignment_delivery_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('delivery_id')->constrained('consignment_deliveries')->cascadeOnDelete();
            $table->foreignUuid('book_id')->constrained('books')->cascadeOnDelete();
            $table->unsignedInteger('qty');
            $table->timestamps();

            $table->index('book_id');
        });

        // Laporan laku dari mitra — memicu piutang (receivables).
        Schema::create('consignment_sales', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('customer_id')->constrained('users');
            $table->date('sale_date');
            $table->text('notes')->nullable();
            $table->foreignUuid('receivable_id')->nullable()->constrained('receivables')->nullOnDelete();
            $table->foreignUuid('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['customer_id', 'sale_date']);
        });

        Schema::create('consignment_sale_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('sale_id')->constrained('consignment_sales')->cascadeOnDelete();
            $table->foreignUuid('book_id')->constrained('books')->cascadeOnDelete();
            $table->unsignedInteger('qty');
            $table->unsignedInteger('price'); // Harga titip per buku saat lapor laku.
            $table->timestamps();

            $table->index('book_id');
        });

        // Retur sisa barang dari mitra — stok kembali ke toko.
        Schema::create('consignment_returns', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('customer_id')->constrained('users');
            $table->foreignUuid('book_id')->constrained('books')->cascadeOnDelete();
            $table->unsignedInteger('qty');
            $table->date('return_date');
            $table->text('notes')->nullable();
            $table->foreignUuid('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['customer_id', 'book_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('consignment_returns');
        Schema::dropIfExists('consignment_sale_items');
        Schema::dropIfExists('consignment_sales');
        Schema::dropIfExists('consignment_delivery_items');
        Schema::dropIfExists('consignment_deliveries');
    }
};

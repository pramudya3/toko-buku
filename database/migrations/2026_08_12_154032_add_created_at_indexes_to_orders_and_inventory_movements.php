<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Index created_at di inventory_movements untuk whereBetween(created_at)
     * pada inventory report (index komposit book_id+created_at tidak dipakai
     * saat query tanpa filter buku). Index orders.created_at sudah ada.
     */
    public function up(): void
    {
        Schema::table('inventory_movements', fn (Blueprint $t) => $t->index('created_at'));
    }

    public function down(): void
    {
        Schema::table('inventory_movements', fn (Blueprint $t) => $t->dropIndex(['created_at']));
    }
};

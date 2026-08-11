<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Index FK yang terlewat dari migration konversi UUID (plan §4 — SEMUA
 * kolom foreign key wajib terindex eksplisit di Postgres).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('dropshippers', fn (Blueprint $t) => $t->index('user_id'));
        Schema::table('inventory_movements', fn (Blueprint $t) => $t->index('book_edition_id'));
        Schema::table('receivables', fn (Blueprint $t) => $t->index('order_id'));
    }

    public function down(): void
    {
        Schema::table('dropshippers', fn (Blueprint $t) => $t->dropIndex(['user_id']));
        Schema::table('inventory_movements', fn (Blueprint $t) => $t->dropIndex(['book_edition_id']));
        Schema::table('receivables', fn (Blueprint $t) => $t->dropIndex(['order_id']));
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->foreignId('book_edition_id')->nullable()
                ->after('book_id')->constrained('book_editions')->nullOnDelete();
            $table->string('edition_snapshot')->nullable()->after('harga_snapshot');
        });
    }

    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropForeign(['book_edition_id']);
            $table->dropColumn(['book_edition_id', 'edition_snapshot']);
        });
    }
};

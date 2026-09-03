<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        foreach (['supplier_purchase_items', 'supplier_return_items'] as $tableName) {
            if (Schema::hasColumn($tableName, 'book_edition_id')) {
                continue;
            }
            try {
                Schema::table($tableName, function (Blueprint $table) {
                    $table->uuid('book_edition_id')->nullable()->after('book_id');
                    $table->foreign('book_edition_id')->references('id')->on('book_editions')->nullOnDelete();
                    $table->index('book_edition_id');
                });
            } catch (Throwable $e) {
                if (! str_contains($e->getMessage(), 'duplicate column name')) {
                    throw $e;
                }
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        foreach (['supplier_purchase_items', 'supplier_return_items'] as $tableName) {
            if (! Schema::hasTable($tableName) || ! Schema::hasColumn($tableName, 'book_edition_id')) {
                continue;
            }
            try {
                Schema::table($tableName, function (Blueprint $table) {
                    $table->dropForeign(['book_edition_id']);
                });
            } catch (Throwable $e) {
                // SQLite atau foreign tidak ada — abaikan
            }
            try {
                Schema::table($tableName, function (Blueprint $table) {
                    $table->dropColumn('book_edition_id');
                });
            } catch (Throwable $e) {
                // Fallback untuk SQLite: coba drop tanpa foreign
            }
        }
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('books', function (Blueprint $table) {
            $table->string('kode_sku')->nullable()->after('id');
            $table->boolean('is_preorder')->default(false)->after('aktif');
            $table->string('po_label')->nullable()->after('is_preorder');
        });
    }

    public function down(): void
    {
        Schema::table('books', function (Blueprint $table) {
            $table->dropColumn(['kode_sku', 'is_preorder', 'po_label']);
        });
    }
};

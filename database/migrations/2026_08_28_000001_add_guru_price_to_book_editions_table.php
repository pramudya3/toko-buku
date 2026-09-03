<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('book_editions', function (Blueprint $table): void {
            $table->string('harga_guru_type', 10)->nullable()->after('harga_jual');
            $table->unsignedInteger('harga_guru_value')->nullable()->after('harga_guru_type');
        });
    }

    public function down(): void
    {
        Schema::table('book_editions', function (Blueprint $table): void {
            $table->dropColumn(['harga_guru_type', 'harga_guru_value']);
        });
    }
};

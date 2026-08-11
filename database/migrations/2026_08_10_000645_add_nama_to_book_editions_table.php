<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('book_editions', function (Blueprint $table): void {
            $table->string('nama', 100)->nullable()->after('cetakan_ke');
        });
    }

    public function down(): void
    {
        Schema::table('book_editions', function (Blueprint $table): void {
            $table->dropColumn('nama');
        });
    }
};

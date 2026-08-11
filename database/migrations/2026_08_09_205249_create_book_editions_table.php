<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('book_editions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('book_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('cetakan_ke')->default(1);
            $table->unsignedInteger('harga_beli')->default(0);
            $table->unsignedInteger('harga_jual')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['book_id', 'cetakan_ke']);
            $table->index(['book_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('book_editions');
    }
};

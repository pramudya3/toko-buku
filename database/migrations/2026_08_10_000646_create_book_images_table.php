<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('book_images', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('book_id')->constrained()->cascadeOnDelete();
            $table->string('image_url');
            $table->unsignedTinyInteger('urutan')->default(0);
            $table->timestamps();

            $table->index(['book_id', 'urutan']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('book_images');
    }
};

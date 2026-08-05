<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('books', function (Blueprint $table) {
            $table->id();
            $table->string('judul');
            $table->string('penulis');
            $table->string('penerbit')->nullable();
            $table->year('tahun')->nullable();
            $table->string('isbn')->nullable();
            $table->text('sinopsis')->nullable();
            $table->integer('harga')->unsigned();
            $table->integer('stok')->unsigned()->default(0);
            $table->foreignId('category_id')->nullable()->constrained()->nullOnDelete();
            $table->string('cover_url')->nullable();
            $table->boolean('aktif')->default(true);
            $table->timestamps();

            $table->index(['aktif', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('books');
    }
};

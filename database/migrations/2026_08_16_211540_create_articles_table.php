<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Artikel (menu storefront + CMS panel admin). Body disimpan sebagai teks
     * polos: paragraf dipisah baris kosong, baris berawalan ">" = blockquote.
     */
    public function up(): void
    {
        Schema::create('articles', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('judul');
            $table->string('slug')->unique();
            $table->string('kategori');
            $table->string('penulis')->nullable();
            $table->text('ringkasan');
            $table->longText('isi');
            $table->string('motif')->nullable();
            $table->string('cover_url')->nullable();
            $table->boolean('is_active')->default(true);
            $table->date('published_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['is_active', 'published_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('articles');
    }
};

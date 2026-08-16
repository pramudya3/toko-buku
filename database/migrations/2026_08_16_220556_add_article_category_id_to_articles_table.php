<?php

use App\Models\Article;
use App\Models\ArticleCategory;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Pindahkan kolom string `kategori` pada articles ke FK article_categories.
     * Data lama (termasuk soft-deleted) di-backfill otomatis: nilai string
     * menjadi kategori baru bila belum ada.
     */
    public function up(): void
    {
        Schema::table('articles', function (Blueprint $table) {
            $table->foreignUuid('article_category_id')
                ->nullable()
                ->after('kategori')
                ->constrained('article_categories')
                ->nullOnDelete();
        });

        foreach (Article::withTrashed()->get(['id', 'kategori']) as $article) {
            if ($article->kategori === null || $article->kategori === '') {
                continue;
            }

            // Buat kategori (bila belum ada) dari nilai lama, lalu tautkan.
            $category = ArticleCategory::withTrashed()
                ->where('slug', Str::slug($article->kategori))
                ->first();

            if ($category === null) {
                $category = ArticleCategory::create([
                    'nama' => $article->kategori,
                    'slug' => Str::slug($article->kategori),
                ]);
            }

            Article::withTrashed()
                ->whereKey($article->getKey())
                ->update(['article_category_id' => $category->id]);
        }

        Schema::table('articles', function (Blueprint $table) {
            $table->dropColumn('kategori');
        });
    }

    public function down(): void
    {
        Schema::table('articles', function (Blueprint $table) {
            $table->string('kategori')->nullable()->after('article_category_id');
        });

        // Kembalikan nilai string dari relasi (jika masih ada).
        foreach (Article::withTrashed()->with('category')->get(['id']) as $article) {
            if ($article->category !== null) {
                Article::withTrashed()
                    ->whereKey($article->getKey())
                    ->update(['kategori' => $article->category->nama]);
            }
        }

        Schema::table('articles', function (Blueprint $table) {
            $table->dropConstrainedForeignId('article_category_id');
        });
    }
};

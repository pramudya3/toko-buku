<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Tambah slug unik untuk URL publik buku (/buku/{slug}).
 * Backfill: slug dari judul, suffix numerik bila bentrok.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('books', function (Blueprint $table): void {
            $table->string('slug')->nullable()->unique()->after('judul');
        });

        DB::table('books')->orderBy('id')->chunkById(500, function ($books): void {
            foreach ($books as $book) {
                $base = Str::slug($book->judul) ?: 'buku';
                $slug = $base;
                $suffix = 2;

                while (DB::table('books')->where('slug', $slug)->where('id', '!=', $book->id)->exists()) {
                    $slug = $base.'-'.$suffix;
                    $suffix++;
                }

                DB::table('books')->where('id', $book->id)->update(['slug' => $slug]);
            }
        });
    }

    public function down(): void
    {
        Schema::table('books', fn (Blueprint $table) => $table->dropUnique(['slug']));

        Schema::table('books', fn (Blueprint $table) => $table->dropColumn('slug'));
    }
};

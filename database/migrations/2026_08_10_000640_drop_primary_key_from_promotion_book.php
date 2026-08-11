<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Hapus primary key dari pivot `promotion_book`.
 *
 * Pivot tidak butuh id sendiri — unique (promotion_id, book_id) sudah cukup,
 * dan Eloquent BelongsToMany::attach()/sync() melakukan raw insert tanpa id
 * (tidak lewat model pivot), sehingga PK uuid membuat attach gagal.
 *
 * Rollback: pasangan (promotion_id, book_id) disimpan di `uuid_migration_map`
 * (kolom pivot_key) saat up() sehingga down() mengembalikan uuid asli — dan
 * down() migration 000639 tetap bisa me-restore id int via mapping tsb.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::transaction(function (): void {
            // 1. Simpan pasangan pivot di tabel mapping (masih berisi data dari 000639).
            if (Schema::hasTable('uuid_migration_map') && ! Schema::hasColumn('uuid_migration_map', 'pivot_key')) {
                Schema::table('uuid_migration_map', function (Blueprint $table): void {
                    $table->string('pivot_key')->nullable();
                });

                if (DB::getDriverName() === 'pgsql') {
                    DB::statement(
                        "UPDATE uuid_migration_map m
                         SET pivot_key = pb.promotion_id || ':' || pb.book_id
                         FROM promotion_book pb
                         WHERE m.entity = 'promotion_book' AND m.new_id = pb.id"
                    );
                } else {
                    $map = DB::table('uuid_migration_map')
                        ->where('entity', 'promotion_book')
                        ->select('id', 'new_id')
                        ->get();

                    foreach ($map as $row) {
                        $pb = DB::table('promotion_book')->where('id', $row->new_id)->first(['promotion_id', 'book_id']);

                        if ($pb !== null) {
                            DB::table('uuid_migration_map')
                                ->where('id', $row->id)
                                ->update(['pivot_key' => $pb->promotion_id.':'.$pb->book_id]);
                        }
                    }
                }
            }

            // 2. Drop PK & kolom id.
            $this->dropPivotId();
        });
    }

    public function down(): void
    {
        DB::transaction(function (): void {
            Schema::table('promotion_book', function (Blueprint $table): void {
                $table->uuid('id')->nullable();
            });

            // Kembalikan uuid asli dari mapping (via pasangan pivot).
            if (Schema::hasColumn('uuid_migration_map', 'pivot_key')) {
                if (DB::getDriverName() === 'pgsql') {
                    DB::statement(
                        "UPDATE promotion_book pb
                         SET id = m.new_id
                         FROM uuid_migration_map m
                         WHERE m.entity = 'promotion_book'
                           AND m.pivot_key = pb.promotion_id || ':' || pb.book_id"
                    );
                } else {
                    $map = DB::table('uuid_migration_map')
                        ->where('entity', 'promotion_book')
                        ->whereNotNull('pivot_key')
                        ->select('new_id', 'pivot_key')
                        ->get();

                    foreach ($map as $row) {
                        [$promotionId, $bookId] = explode(':', $row->pivot_key, 2);

                        DB::table('promotion_book')
                            ->where('promotion_id', $promotionId)
                            ->where('book_id', $bookId)
                            ->update(['id' => $row->new_id]);
                    }
                }
            }

            // Sisa baris yang tidak ada di mapping (data baru setelah up) — uuid baru.
            $rows = DB::table('promotion_book')->whereNull('id')->count();

            if ($rows > 0) {
                DB::table('promotion_book')->whereNull('id')->orderBy('promotion_id')->chunkById(500, function ($chunk): void {
                    foreach ($chunk as $row) {
                        DB::table('promotion_book')->where('promotion_id', $row->promotion_id)
                            ->where('book_id', $row->book_id)
                            ->update(['id' => (string) Str::uuid7()]);
                    }
                });
            }

            Schema::table('promotion_book', function (Blueprint $table): void {
                $table->uuid('id')->nullable(false)->change();
                $table->primary('id');
            });
        });
    }

    private function dropPivotId(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            $pk = DB::scalar(
                "SELECT conname FROM pg_constraint WHERE conrelid = 'promotion_book'::regclass AND contype = 'p'"
            );

            DB::statement("ALTER TABLE promotion_book DROP CONSTRAINT {$pk}");
            DB::statement('ALTER TABLE promotion_book DROP COLUMN id');

            return;
        }

        // SQLite: lepas autoincrement dulu (PK melekat pada modifier kolom), lalu drop.
        Schema::table('promotion_book', function (Blueprint $table): void {
            $table->dropPrimary('promotion_book_pkey');
            $table->unsignedBigInteger('id')->nullable()->change();
        });

        Schema::table('promotion_book', fn (Blueprint $t) => $t->dropColumn('id'));
    }
};

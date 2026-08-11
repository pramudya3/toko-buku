<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Konversi bank_accounts.id ke UUID.
 *
 * Tabel ini dibuat oleh template lama sebelum migrasi konversi 000639,
 * sehingga di database existing id-nya masih bigint. Di fresh install
 * (tabel dibuat langsung uuid di 000638) migrasi ini jadi no-op.
 */
return new class extends Migration
{
    public function up(): void
    {
        $type = Schema::getColumnType('bank_accounts', 'id');

        if (! in_array($type, ['bigint', 'integer', 'int', 'int8', 'int4'], true)) {
            return;
        }

        Schema::table('bank_accounts', function (Blueprint $table): void {
            $table->uuid('uuid')->nullable();
            $table->unique('uuid');
        });

        DB::table('bank_accounts')->orderBy('id')->chunkById(200, function ($rows): void {
            foreach ($rows as $row) {
                DB::table('bank_accounts')
                    ->where('id', $row->id)
                    ->update(['uuid' => (string) Str::uuid7()]);
            }
        });

        Schema::table('bank_accounts', function (Blueprint $table): void {
            $table->dropPrimary('bank_accounts_pkey');
            $table->dropColumn('id');
            $table->renameColumn('uuid', 'id');
            $table->primary('id');
        });
    }

    public function down(): void
    {
        // Tidak ada rollback otomatis — id uuid dipertahankan.
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Email users boleh kosong — pelanggan hasil import CSV tidak punya email.
 *
 * Unique index parsial (users_email_unique, WHERE deleted_at IS NULL) tetap
 * berlaku: PostgreSQL memperlakukan NULL sebagai nilai berbeda, jadi banyak
 * baris email NULL tidak saling bentrok.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('email')->nullable()->change();
        });

        $this->restorePartialEmailIndex();
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('email')->nullable(false)->change();
        });

        $this->restorePartialEmailIndex();
    }

    /**
     * SQLite me-rebuild tabel saat change() dan mengganti partial unique index
     * (WHERE deleted_at IS NULL) dengan unique index biasa — buat ulang.
     */
    private function restorePartialEmailIndex(): void
    {
        if (DB::getDriverName() !== 'sqlite') {
            return;
        }

        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique('users_email_unique');
        });

        DB::statement('CREATE UNIQUE INDEX users_email_unique ON users (email) WHERE deleted_at IS NULL');
    }
};

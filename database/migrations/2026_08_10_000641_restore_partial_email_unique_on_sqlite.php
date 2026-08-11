<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Pulihkan partial unique index `users_email_unique` di SQLite.
 *
 * Migration 000639 (konversi UUID) melakukan table-rebuild di SQLite yang
 * menyalin index TANPA klausa `WHERE` — email jadi unik penuh sehingga email
 * akun soft-deleted tidak bisa dipakai mendaftar lagi. Postgres tidak
 * terpengaruh (index asli tetap).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            return;
        }

        DB::statement('DROP INDEX IF EXISTS users_email_unique');
        DB::statement('CREATE UNIQUE INDEX users_email_unique ON users (email) WHERE deleted_at IS NULL');
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            return;
        }

        DB::statement('DROP INDEX IF EXISTS users_email_unique');

        Schema::table('users', fn ($table) => $table->unique('email'));
    }
};

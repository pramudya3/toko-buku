<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Partial unique index — email hanya unik untuk user yang TIDAK terhapus
 * (soft delete), sehingga email akun terhapus bisa dipakai mendaftar lagi.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function ($table) {
            $table->dropUnique('users_email_unique');
        });

        DB::statement('CREATE UNIQUE INDEX users_email_unique ON users (email) WHERE deleted_at IS NULL');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX users_email_unique');

        Schema::table('users', function ($table) {
            $table->unique('email');
        });
    }
};

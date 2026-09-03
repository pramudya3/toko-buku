<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('google_id')->nullable()->unique()->after('email');
            $table->string('avatar')->nullable()->after('google_id');
            $table->string('password')->nullable()->change();
        });

        $this->restorePartialEmailIndex();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            // SQLite: drop unique index dulu sebelum drop kolom (hindari "no such column" error)
            try {
                Schema::table('users', function (Blueprint $table) {
                    $table->dropUnique('users_google_id_unique');
                });
            } catch (Throwable) {
                try {
                    DB::statement('DROP INDEX IF EXISTS users_google_id_unique');
                } catch (Throwable) {
                }
            }

            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn(['google_id', 'avatar']);
            });

            Schema::table('users', function (Blueprint $table) {
                $table->string('password')->nullable(false)->change();
            });

            $this->restorePartialEmailIndex();

            return;
        }

        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['google_id']);
            $table->dropColumn(['google_id', 'avatar']);
            $table->string('password')->nullable(false)->change();
        });
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

        try {
            Schema::table('users', function (Blueprint $table) {
                $table->dropUnique('users_email_unique');
            });
        } catch (Throwable) {
            try {
                DB::statement('DROP INDEX IF EXISTS users_email_unique');
            } catch (Throwable) {
            }
        }

        DB::statement('CREATE UNIQUE INDEX users_email_unique ON users (email) WHERE deleted_at IS NULL');
    }
};

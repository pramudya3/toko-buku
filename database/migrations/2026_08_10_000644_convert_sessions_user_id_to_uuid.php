<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * `sessions.user_id` masih bigint — setelah users.id jadi uuid, login
 * menulis uuid ke kolom bigint dan gagal. Ubah ke uuid, repoint data
 * existing via `uuid_migration_map` (bila tersedia), dan pertahankan index.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sessions', function (Blueprint $table): void {
            $table->uuid('user_id_uuid')->nullable();
        });

        $this->repoint(
            from: 'user_id',
            to: 'user_id_uuid',
            entity: 'users',
            direction: 'old-to-new',
        );

        Schema::table('sessions', function (Blueprint $table): void {
            $table->dropIndex('sessions_user_id_index');
            $table->dropColumn('user_id');
        });

        Schema::table('sessions', fn (Blueprint $table) => $table->renameColumn('user_id_uuid', 'user_id'));

        Schema::table('sessions', function (Blueprint $table): void {
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::table('sessions', function (Blueprint $table): void {
            $table->dropIndex('sessions_user_id_index');
            $table->unsignedBigInteger('user_id_int')->nullable();
        });

        $this->repoint(
            from: 'user_id',
            to: 'user_id_int',
            entity: 'users',
            direction: 'new-to-old',
        );

        Schema::table('sessions', function (Blueprint $table): void {
            $table->dropColumn('user_id');
        });

        Schema::table('sessions', fn (Blueprint $table) => $table->renameColumn('user_id_int', 'user_id'));

        Schema::table('sessions', function (Blueprint $table): void {
            $table->index('user_id');
        });
    }

    /**
     * Repoint sessions.{from} dari/tuju uuid via uuid_migration_map.
     */
    private function repoint(string $from, string $to, string $entity, string $direction): void
    {
        if (! Schema::hasTable('uuid_migration_map')) {
            return;
        }

        if (DB::getDriverName() === 'pgsql') {
            $expression = $direction === 'old-to-new' ? 'm.new_id' : 'm.old_id';

            DB::statement(
                "UPDATE sessions s SET {$to} = {$expression}
                 FROM uuid_migration_map m
                 WHERE m.entity = '{$entity}' AND m.old_id = s.{$from}"
            );

            return;
        }

        $pairs = DB::table('uuid_migration_map')
            ->where('entity', $entity)
            ->select('old_id', 'new_id')
            ->get();

        foreach ($pairs as $pair) {
            DB::table('sessions')
                ->where($from, $direction === 'old-to-new' ? $pair->old_id : $pair->new_id)
                ->update([$to => $direction === 'old-to-new' ? $pair->new_id : $pair->old_id]);
        }
    }
};

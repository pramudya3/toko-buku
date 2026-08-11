<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Konversi seluruh kolom timestamp naif ke timestamp with time zone (PostgreSQL).
 *
 * Latar belakang (lihat planning timezone):
 * - App diarahkan ke Asia/Jakarta (APP_TIMEZONE), DB session Asia/Jakarta.
 * - Kolom timestamptz menyimpan instan UTC secara internal — inilah "DB dalam UTC".
 * - Nilai lama ditulis saat APP_TIMEZONE=UTC → wall-clock UTC, konversi AT TIME ZONE 'UTC'.
 * - Kecuali failed_jobs.failed_at (default CURRENT_TIMESTAMP, session WIB) → interpretasi WIB.
 *
 * SQLite (test) tidak memiliki konsep timezone kolom → no-op.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        foreach ($this->naiveTimestampColumns() as $column) {
            $interpretAs = $this->interpretAs($column);

            DB::statement(sprintf(
                'ALTER TABLE "%s" ALTER COLUMN "%s" TYPE timestamptz USING "%s" AT TIME ZONE \'%s\'',
                $column->table_name,
                $column->column_name,
                $column->column_name,
                $interpretAs,
            ));
        }
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        foreach ($this->naiveTimestampColumns() as $column) {
            DB::statement(sprintf(
                'ALTER TABLE "%s" ALTER COLUMN "%s" TYPE timestamp without time zone USING "%s" AT TIME ZONE \'UTC\'',
                $column->table_name,
                $column->column_name,
                $column->column_name,
            ));
        }
    }

    /**
     * @return array<int, object{table_name: string, column_name: string}>
     */
    private function naiveTimestampColumns(): array
    {
        return DB::select(
            "SELECT table_name, column_name
             FROM information_schema.columns
             WHERE table_schema = 'public'
               AND data_type = 'timestamp without time zone'
             ORDER BY table_name, column_name",
        );
    }

    /**
     * @param  object{table_name: string, column_name: string}  $column
     */
    private function interpretAs(object $column): string
    {
        // failed_at memakai default CURRENT_TIMESTAMP (session WIB saat itu) → wall-clock WIB.
        if ($column->table_name === 'failed_jobs' && $column->column_name === 'failed_at') {
            return 'Asia/Jakarta';
        }

        // Kolom lain ditulis Eloquent saat APP_TIMEZONE=UTC → wall-clock UTC.
        return 'UTC';
    }
};

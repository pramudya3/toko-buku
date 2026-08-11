<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Normalisasi inventori multi-gudang:
 * - inventory_stocks (kolom fixed malang/sidoarjo/defect) → baris per gudang.
 * - inventory_movements (string enum) → FK ke tabel warehouses.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Pastikan gudang bawaan ada SEBELUM backfill (migration tidak boleh
        // bergantung pada urutan seeder). updateOrCreate-style: idempoten.
        $now = now()->toDateTimeString();
        $defaults = [
            ['kode' => 'malang', 'nama' => 'Malang', 'is_defect' => false, 'is_active' => true],
            ['kode' => 'sidoarjo', 'nama' => 'Sidoarjo', 'is_defect' => false, 'is_active' => true],
            ['kode' => 'defect', 'nama' => 'Defect', 'is_defect' => true, 'is_active' => true],
        ];

        foreach ($defaults as $default) {
            if (! DB::table('warehouses')->where('kode', $default['kode'])->exists()) {
                DB::table('warehouses')->insert($default + ['created_at' => $now, 'updated_at' => $now]);
            }
        }

        $warehouseIds = DB::table('warehouses')->pluck('id', 'kode');

        // 1. inventory_stocks → baris per (book, warehouse).
        Schema::rename('inventory_stocks', 'inventory_stocks_legacy');

        Schema::create('inventory_stocks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('book_id')->constrained()->cascadeOnDelete();
            $table->foreignId('warehouse_id')->constrained()->cascadeOnDelete();
            $table->integer('qty')->unsigned()->default(0);
            $table->timestamps();

            $table->unique(['book_id', 'warehouse_id']);
        });

        DB::table('inventory_stocks_legacy')->orderBy('id')->chunkById(500, function ($rows) use ($warehouseIds): void {
            foreach ($rows as $row) {
                $rowsToInsert = [
                    ['warehouse' => 'malang', 'column' => 'stock_malang'],
                    ['warehouse' => 'sidoarjo', 'column' => 'stock_sidoarjo'],
                    ['warehouse' => 'defect', 'column' => 'stock_defect'],
                ];

                foreach ($rowsToInsert as $spec) {
                    $qty = (int) $row->{$spec['column']};
                    $warehouseId = $warehouseIds[$spec['warehouse']] ?? null;

                    if ($qty > 0 && $warehouseId !== null) {
                        DB::table('inventory_stocks')->insert([
                            'book_id' => $row->book_id,
                            'warehouse_id' => $warehouseId,
                            'qty' => $qty,
                            'created_at' => $row->created_at,
                            'updated_at' => $row->updated_at,
                        ]);
                    }
                }
            }
        });

        Schema::dropIfExists('inventory_stocks_legacy');

        // 2. inventory_movements → FK gudang.
        Schema::table('inventory_movements', function (Blueprint $table) {
            $table->foreignId('from_warehouse_id')->nullable()->after('to_warehouse')->constrained('warehouses')->nullOnDelete();
            $table->foreignId('to_warehouse_id')->nullable()->after('from_warehouse_id')->constrained('warehouses')->nullOnDelete();
        });

        DB::table('inventory_movements')->orderBy('id')->chunkById(500, function ($rows) use ($warehouseIds): void {
            foreach ($rows as $row) {
                $fromId = $row->from_warehouse !== null ? ($warehouseIds[$row->from_warehouse] ?? null) : null;
                $toId = $row->to_warehouse !== null ? ($warehouseIds[$row->to_warehouse] ?? null) : null;

                DB::table('inventory_movements')->where('id', $row->id)->update([
                    'from_warehouse_id' => $fromId,
                    'to_warehouse_id' => $toId,
                ]);
            }
        });

        Schema::table('inventory_movements', function (Blueprint $table) {
            $table->dropColumn(['from_warehouse', 'to_warehouse']);
        });
    }

    public function down(): void
    {
        $warehouseKodes = DB::table('warehouses')->pluck('kode', 'id');

        Schema::table('inventory_movements', function (Blueprint $table) {
            $table->string('from_warehouse')->nullable()->after('to_warehouse_id');
            $table->string('to_warehouse')->nullable()->after('from_warehouse');
        });

        DB::table('inventory_movements')->orderBy('id')->chunkById(500, function ($rows) use ($warehouseKodes): void {
            foreach ($rows as $row) {
                DB::table('inventory_movements')->where('id', $row->id)->update([
                    'from_warehouse' => $row->from_warehouse_id !== null ? ($warehouseKodes[$row->from_warehouse_id] ?? null) : null,
                    'to_warehouse' => $row->to_warehouse_id !== null ? ($warehouseKodes[$row->to_warehouse_id] ?? null) : null,
                ]);
            }
        });

        Schema::table('inventory_movements', function (Blueprint $table) {
            $table->dropForeign(['from_warehouse_id']);
            $table->dropForeign(['to_warehouse_id']);
            $table->dropColumn(['from_warehouse_id', 'to_warehouse_id']);
        });

        Schema::rename('inventory_stocks', 'inventory_stocks_legacy');

        Schema::create('inventory_stocks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('book_id')->constrained()->cascadeOnDelete();
            $table->integer('stock_malang')->unsigned()->default(0);
            $table->integer('stock_sidoarjo')->unsigned()->default(0);
            $table->integer('stock_defect')->unsigned()->default(0);
        });

        DB::table('inventory_stocks_legacy')->orderBy('id')->chunkById(500, function ($rows) use ($warehouseKodes): void {
            foreach ($rows as $row) {
                $malangId = $warehouseKodes->search('malang');
                $sidoarjoId = $warehouseKodes->search('sidoarjo');
                $defectId = $warehouseKodes->search('defect');

                DB::table('inventory_stocks')->insert([
                    'book_id' => $row->book_id,
                    'stock_malang' => $row->warehouse_id === $malangId ? $row->qty : 0,
                    'stock_sidoarjo' => $row->warehouse_id === $sidoarjoId ? $row->qty : 0,
                    'stock_defect' => $row->warehouse_id === $defectId ? $row->qty : 0,
                ]);
            }
        });

        Schema::dropIfExists('inventory_stocks_legacy');
    }
};

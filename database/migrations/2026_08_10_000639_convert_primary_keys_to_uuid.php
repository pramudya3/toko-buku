<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Konversi seluruh primary key tabel aplikasi ke UUID v7 (data-preserving).
 *
 * Strategi (lihat docs/implementation-plan-uuid.md):
 *  1. Setiap tabel: tambah kolom `uuid` (nullable) + unique index → backfill
 *     (pgsql: `gen_random_uuid()`; sqlite: `Str::uuid7()` per-chunk) → simpan
 *     mapping id-int-lama → uuid di `uuid_migration_map` (untuk rollback).
 *  2. Setiap FK anak: tambah kolom `{col}_uuid` → backfill via JOIN ke induk
 *     → drop FK & kolom int lama → rename.
 *  3. Induk: drop PK int + kolom `id` → rename `uuid` → `id` → jadikan PK.
 *  4. Buat ulang seluruh FK + index (Postgres tidak meng-index FK otomatis).
 *
 * down(): kebalikannya, mengembalikan id int dari mapping + sequence (pgsql).
 * Seluruh proses dibungkus DB::transaction — jika gagal di tengah, semua batal.
 */
return new class extends Migration
{
    /** Semua tabel aplikasi yang PK-nya diubah ke UUID v7. */
    private const TABLES = [
        'users', 'categories', 'books', 'book_editions', 'book_edition_stocks',
        'promotions', 'promotion_book', 'orders', 'order_items', 'dropshippers',
        'cash_flows', 'warehouses', 'inventory_stocks', 'inventory_movements',
        'suppliers', 'supplier_purchases', 'supplier_purchase_items',
        'supplier_returns', 'supplier_return_items', 'supplier_payments',
        'tier_discounts', 'receivables', 'receivable_payments', 'sales_returns',
        'sales_return_items', 'provinces', 'cities', 'districts', 'villages',
        'settings',
    ];

    /**
     * FK per tabel anak: kolom => [tabel induk, onDelete].
     * onDelete: 'cascade' | 'restrict' | null (NO ACTION).
     */
    private const FOREIGN_KEYS = [
        'books' => ['category_id' => ['categories', null]],
        'orders' => ['user_id' => ['users', null]],
        'order_items' => [
            'order_id' => ['orders', 'cascade'],
            'book_id' => ['books', 'restrict'],
            'book_edition_id' => ['book_editions', null],
        ],
        'cash_flows' => ['order_id' => ['orders', null]],
        'dropshippers' => [
            'order_id' => ['orders', 'cascade'],
            'user_id' => ['users', null],
        ],
        'inventory_stocks' => [
            'book_id' => ['books', 'cascade'],
            'warehouse_id' => ['warehouses', 'cascade'],
        ],
        'inventory_movements' => [
            'book_id' => ['books', 'cascade'],
            'book_edition_id' => ['book_editions', null],
            'user_id' => ['users', null],
            'from_warehouse_id' => ['warehouses', null],
            'to_warehouse_id' => ['warehouses', null],
        ],
        'book_editions' => ['book_id' => ['books', 'cascade']],
        'book_edition_stocks' => [
            'book_edition_id' => ['book_editions', 'cascade'],
            'warehouse_id' => ['warehouses', 'cascade'],
        ],
        'promotion_book' => [
            'promotion_id' => ['promotions', 'cascade'],
            'book_id' => ['books', 'cascade'],
        ],
        'receivables' => [
            'customer_id' => ['users', 'cascade'],
            'order_id' => ['orders', null],
        ],
        'receivable_payments' => ['receivable_id' => ['receivables', 'cascade']],
        'sales_returns' => [
            'order_id' => ['orders', 'cascade'],
            'user_id' => ['users', null],
        ],
        'sales_return_items' => [
            'sales_return_id' => ['sales_returns', 'cascade'],
            'order_item_id' => ['order_items', 'cascade'],
            'book_id' => ['books', 'cascade'],
            'book_edition_id' => ['book_editions', null],
        ],
        'supplier_purchases' => [
            'supplier_id' => ['suppliers', 'cascade'],
            'user_id' => ['users', null],
        ],
        'supplier_purchase_items' => [
            'supplier_purchase_id' => ['supplier_purchases', 'cascade'],
            'book_id' => ['books', 'restrict'],
        ],
        'supplier_returns' => [
            'supplier_id' => ['suppliers', 'cascade'],
            'supplier_purchase_id' => ['supplier_purchases', null],
            'user_id' => ['users', null],
        ],
        'supplier_return_items' => [
            'supplier_return_id' => ['supplier_returns', 'cascade'],
            'book_id' => ['books', 'restrict'],
        ],
        'supplier_payments' => [
            'supplier_id' => ['suppliers', 'cascade'],
            'supplier_purchase_id' => ['supplier_purchases', null],
            'user_id' => ['users', null],
        ],
    ];

    /**
     * Index yang dijamin ada setelah up() (plan §4 — semua FK terindex eksplisit).
     * Spec: array kolom; flag 'unique' opsional.
     */
    private const INDEXES = [
        'order_items' => [['order_id'], ['book_id'], ['book_edition_id'], ['order_id', 'book_id']],
        'orders' => [['user_id'], ['created_at']],
        'cash_flows' => [['order_id']],
        'inventory_stocks' => [['book_id', 'warehouse_id', 'unique']],
        'inventory_movements' => [['book_id'], ['from_warehouse_id'], ['to_warehouse_id'], ['user_id'], ['book_id', 'created_at']],
        'book_editions' => [['book_id'], ['book_id', 'cetakan_ke', 'unique'], ['book_id', 'is_active']],
        'book_edition_stocks' => [['book_edition_id', 'warehouse_id', 'unique']],
        'promotion_book' => [['promotion_id'], ['book_id'], ['promotion_id', 'book_id', 'unique']],
        'dropshippers' => [['order_id', 'unique']],
        'receivables' => [['customer_id'], ['customer_id', 'created_at']],
        'receivable_payments' => [['receivable_id']],
        'sales_returns' => [['order_id'], ['user_id'], ['return_date', 'order_id']],
        'sales_return_items' => [['sales_return_id'], ['order_item_id'], ['book_id'], ['book_edition_id']],
        'supplier_purchases' => [['supplier_id'], ['user_id']],
        'supplier_purchase_items' => [['supplier_purchase_id'], ['book_id']],
        'supplier_returns' => [['supplier_id'], ['supplier_purchase_id'], ['user_id']],
        'supplier_return_items' => [['supplier_return_id'], ['book_id']],
        'supplier_payments' => [['supplier_id'], ['supplier_purchase_id'], ['user_id']],
        'books' => [['category_id']],
    ];

    /** Index yang sudah ada SEBELUM up() — dipulihkan di down(). */
    private const ORIGINAL_INDEXES = [
        'book_editions' => [['book_id', 'cetakan_ke', 'unique'], ['book_id', 'is_active']],
        'book_edition_stocks' => [['book_edition_id', 'warehouse_id', 'unique']],
        'inventory_stocks' => [['book_id', 'warehouse_id', 'unique']],
        'inventory_movements' => [['book_id']],
        'promotion_book' => [['promotion_id', 'book_id', 'unique']],
        'dropshippers' => [['order_id', 'unique']],
        'receivables' => [['customer_id', 'created_at']],
        'receivable_payments' => [['receivable_id']],
        'sales_returns' => [['return_date', 'order_id']],
        'sales_return_items' => [['order_item_id']],
    ];

    public function up(): void
    {
        DB::transaction(function (): void {
            // 1. Tabel mapping untuk rollback.
            Schema::create('uuid_migration_map', function (Blueprint $table): void {
                $table->string('entity');
                $table->unsignedBigInteger('old_id');
                $table->uuid('new_id');
                $table->index(['entity', 'old_id']);
            });

            // 2. Semua tabel: tambah uuid + backfill + mapping.
            foreach (self::TABLES as $table) {
                Schema::table($table, function (Blueprint $t) use ($table): void {
                    $t->uuid('uuid')->nullable();
                    $t->unique('uuid', "{$table}_uuid_unique");
                });

                $this->backfillUuid($table);

                DB::statement(
                    "INSERT INTO uuid_migration_map (entity, old_id, new_id)
                     SELECT '{$table}', id, uuid FROM {$table}"
                );
            }

            // 3. Anak: re-point FK ke uuid induk (drop FK & kolom int lama).
            foreach (self::FOREIGN_KEYS as $child => $columns) {
                foreach ($columns as $column => [$parent]) {
                    $uuidColumn = $column.'_uuid';

                    Schema::table($child, function (Blueprint $t) use ($uuidColumn): void {
                        $t->uuid($uuidColumn)->nullable();
                    });

                    $this->repointChild($child, $parent, $column, $uuidColumn);

                    $this->dropForeignKey($child, $column);
                    $this->dropIndexesForColumn($child, $column);

                    Schema::table($child, fn (Blueprint $t) => $t->dropColumn($column));
                    Schema::table($child, fn (Blueprint $t) => $t->renameColumn($uuidColumn, $column));
                }
            }

            // 4. Induk: uuid menjadi primary key.
            foreach (self::TABLES as $table) {
                $pkName = $this->primaryKeyName($table);

                if ($this->isPgsql()) {
                    // compileDropPrimary Postgres selalu generate "{table}_pkey" —
                    // tidak berlaku untuk semua tabel, jadi drop constraint via raw SQL.
                    DB::statement("ALTER TABLE {$table} DROP CONSTRAINT {$pkName}");

                    Schema::table($table, function (Blueprint $t) use ($table): void {
                        $t->dropUnique("{$table}_uuid_unique");
                        $t->dropColumn('id');
                    });

                    Schema::table($table, fn (Blueprint $t) => $t->renameColumn('uuid', 'id'));

                    Schema::table($table, function (Blueprint $t): void {
                        $t->uuid('id')->nullable(false)->change();
                        $t->primary('id');
                    });
                } else {
                    // SQLite: rebuild per fase. change() kolom id melepas
                    // autoincrement (PK melekat pada modifier kolom).
                    Schema::table($table, function (Blueprint $t) use ($table, $pkName): void {
                        $t->dropUnique("{$table}_uuid_unique");
                        $t->dropPrimary($pkName);
                        $t->unsignedBigInteger('id')->nullable()->change();
                    });

                    Schema::table($table, fn (Blueprint $t) => $t->dropColumn('id'));
                    Schema::table($table, fn (Blueprint $t) => $t->renameColumn('uuid', 'id'));

                    Schema::table($table, function (Blueprint $t): void {
                        $t->uuid('id')->nullable(false)->change();
                        $t->primary('id');
                    });
                }
            }

            // 5. Buat ulang FK + index.
            foreach (self::FOREIGN_KEYS as $child => $columns) {
                foreach ($columns as $column => [$parent, $onDelete]) {
                    Schema::table($child, function (Blueprint $t) use ($column, $parent, $onDelete): void {
                        $fk = $t->foreign($column)->references('id')->on($parent);
                        if ($onDelete !== null) {
                            $fk->onDelete($onDelete);
                        }
                    });
                }
            }

            foreach (self::INDEXES as $table => $specs) {
                foreach ($specs as $spec) {
                    $name = $this->indexName($table, $spec);
                    if (Schema::hasIndex($table, $name)) {
                        continue;
                    }

                    $cols = $this->indexColumns($spec);
                    $unique = in_array('unique', $spec, true);
                    Schema::table($table, function (Blueprint $t) use ($cols, $name, $unique): void {
                        $unique ? $t->unique($cols, $name) : $t->index($cols, $name);
                    });
                }
            }
        });
    }

    public function down(): void
    {
        DB::transaction(function (): void {
            // 1. Drop FK & index yang dibuat di up().
            foreach (self::FOREIGN_KEYS as $child => $columns) {
                foreach (array_keys($columns) as $column) {
                    $this->dropForeignKey($child, $column);
                }

                foreach (self::INDEXES[$child] ?? [] as $spec) {
                    $name = $this->indexName($child, $spec);
                    if (! Schema::hasIndex($child, $name)) {
                        continue;
                    }

                    $unique = in_array('unique', $spec, true);
                    Schema::table($child, function (Blueprint $t) use ($name, $unique): void {
                        $unique ? $t->dropUnique($name) : $t->dropIndex($name);
                    });
                }
            }

            // 2. Anak: kembalikan kolom FK int (induk masih ber-uuid).
            foreach (self::FOREIGN_KEYS as $child => $columns) {
                foreach ($columns as $column => [$parent]) {
                    $intColumn = $column.'_int';

                    Schema::table($child, function (Blueprint $t) use ($intColumn): void {
                        $t->unsignedBigInteger($intColumn)->nullable();
                    });

                    $this->restoreChildColumn($child, $parent, $column, $intColumn);
                    $this->dropIndexesForColumn($child, $column);

                    Schema::table($child, fn (Blueprint $t) => $t->dropColumn($column));
                    Schema::table($child, fn (Blueprint $t) => $t->renameColumn($intColumn, $column));
                }
            }

            // 3. Induk: kembalikan PK int (+ sequence di pgsql).
            foreach (self::TABLES as $table) {
                $pkName = $this->primaryKeyName($table);

                if ($this->isPgsql()) {
                    DB::statement("ALTER TABLE {$table} DROP CONSTRAINT {$pkName}");

                    Schema::table($table, function (Blueprint $t): void {
                        $t->renameColumn('id', 'uuid');
                        $t->unsignedBigInteger('id')->nullable();
                    });

                    DB::statement(
                        "UPDATE {$table} SET id = m.old_id FROM uuid_migration_map m
                         WHERE m.entity = '{$table}' AND m.new_id = {$table}.uuid"
                    );

                    Schema::table($table, function (Blueprint $t): void {
                        $t->dropColumn('uuid');
                        $t->unsignedBigInteger('id')->nullable(false)->change();
                        $t->primary('id');
                    });

                    $sequence = "{$table}_id_seq";
                    DB::statement("DROP SEQUENCE IF EXISTS {$sequence}");
                    DB::statement("CREATE SEQUENCE {$sequence} OWNED BY {$table}.id");
                    DB::statement("SELECT setval('{$sequence}', COALESCE((SELECT MAX(id) FROM {$table}), 1))");
                    DB::statement("ALTER TABLE {$table} ALTER COLUMN id SET DEFAULT nextval('{$sequence}')");
                } else {
                    // SQLite: PK uuid bukan autoincrement → dropPrimary rebuild bersih.
                    Schema::table($table, function (Blueprint $t) use ($pkName): void {
                        $t->dropPrimary($pkName);
                    });

                    Schema::table($table, fn (Blueprint $t) => $t->renameColumn('id', 'uuid'));
                    Schema::table($table, fn (Blueprint $t) => $t->unsignedBigInteger('id')->nullable());

                    // Restore id int lama — kolom uuid masih ada di tahap ini.
                    $pairs = DB::table('uuid_migration_map')
                        ->where('entity', $table)
                        ->select('old_id', 'new_id')
                        ->get();

                    foreach ($pairs as $pair) {
                        DB::table($table)->where('uuid', $pair->new_id)->update(['id' => $pair->old_id]);
                    }

                    Schema::table($table, fn (Blueprint $t) => $t->dropColumn('uuid'));

                    Schema::table($table, function (Blueprint $t): void {
                        $t->unsignedBigInteger('id')->nullable(false)->change();
                        $t->primary('id');
                    });
                }
            }

            // 4. Pulihkan index asli yang hilang saat kolom int di-drop.
            foreach (self::ORIGINAL_INDEXES as $table => $specs) {
                foreach ($specs as $spec) {
                    $name = $this->indexName($table, $spec);
                    if (Schema::hasIndex($table, $name)) {
                        continue;
                    }

                    $cols = $this->indexColumns($spec);
                    $unique = in_array('unique', $spec, true);
                    Schema::table($table, function (Blueprint $t) use ($cols, $name, $unique): void {
                        $unique ? $t->unique($cols, $name) : $t->index($cols, $name);
                    });
                }
            }

            // 5. Hapus tabel mapping.
            Schema::dropIfExists('uuid_migration_map');
        });
    }

    /** Backfill kolom uuid: pgsql via SQL (cepat), sqlite via PHP per-chunk. */
    private function backfillUuid(string $table): void
    {
        if ($this->isPgsql()) {
            DB::table($table)->whereNull('uuid')->update(['uuid' => DB::raw('gen_random_uuid()')]);

            return;
        }

        DB::table($table)->whereNull('uuid')->orderBy('id')->chunkById(500, function ($rows) use ($table): void {
            foreach ($rows as $row) {
                DB::table($table)->where('id', $row->id)->update(['uuid' => (string) Str::uuid7()]);
            }
        });
    }

    /** Isi kolom uuid FK anak dari uuid induk (join). */
    private function repointChild(string $child, string $parent, string $column, string $uuidColumn): void
    {
        if ($this->isPgsql()) {
            DB::statement(
                "UPDATE {$child} AS c SET {$uuidColumn} = p.uuid
                 FROM {$parent} AS p
                 WHERE c.{$column} = p.id AND c.{$uuidColumn} IS NULL"
            );

            return;
        }

        $pairs = DB::table("{$child} as c")
            ->join("{$parent} as p", 'c.'.$column, '=', 'p.id')
            ->whereNull('c.'.$uuidColumn)
            ->select('c.id as child_id', 'p.uuid as parent_uuid')
            ->get();

        foreach ($pairs as $pair) {
            DB::table($child)->where('id', $pair->child_id)->update([$uuidColumn => $pair->parent_uuid]);
        }
    }

    /** Kembalikan kolom FK int anak dari mapping (join uuid induk). */
    private function restoreChildColumn(string $child, string $parent, string $column, string $intColumn): void
    {
        if ($this->isPgsql()) {
            DB::statement(
                "UPDATE {$child} AS c SET {$intColumn} = m.old_id
                 FROM uuid_migration_map m, {$parent} AS p
                 WHERE m.entity = '{$parent}' AND p.id = c.{$column} AND m.new_id = p.id"
            );

            return;
        }

        $pairs = DB::table("{$child} as c")
            ->join("{$parent} as p", 'c.'.$column, '=', 'p.id')
            ->join('uuid_migration_map as m', function ($j) use ($parent): void {
                $j->on('m.new_id', '=', 'p.id')->where('m.entity', '=', $parent);
            })
            ->select('c.id as child_id', 'm.old_id')
            ->get();

        foreach ($pairs as $pair) {
            DB::table($child)->where('id', $pair->child_id)->update([$intColumn => $pair->old_id]);
        }
    }

    private function isPgsql(): bool
    {
        return DB::getDriverName() === 'pgsql';
    }

    /** Nama constraint primary key aktual (bisa bukan {table}_pkey, lihat inventory_stocks). */
    private function primaryKeyName(string $table): string
    {
        if (! $this->isPgsql()) {
            // SQLite: dropPrimary ditangani table-rebuild Laravel (argumen diabaikan).
            return "{$table}_pkey";
        }

        $name = DB::scalar(
            "SELECT conname FROM pg_constraint WHERE conrelid = ?::regclass AND contype = 'p'",
            [$table]
        );

        if ($name === null) {
            throw new RuntimeException("Primary key tidak ditemukan pada tabel {$table}.");
        }

        return $name;
    }

    /** Nama constraint FK aktual untuk (table, column) via schema introspection. */
    private function fkName(string $table, string $column): ?string
    {
        foreach (Schema::getForeignKeys($table) as $fk) {
            if (($fk['columns'] ?? []) === [$column]) {
                return $fk['name'];
            }
        }

        return null;
    }

    /** Drop FK: pgsql by name, sqlite by column (tidak mendukung by name). */
    private function dropForeignKey(string $child, string $column): void
    {
        if ($this->isPgsql()) {
            $fkName = $this->fkName($child, $column);
            if ($fkName !== null) {
                Schema::table($child, fn (Blueprint $t) => $t->dropForeign($fkName));
            }

            return;
        }

        Schema::table($child, fn (Blueprint $t) => $t->dropForeign([$column]));
    }

    /** SQLite menolak drop column selama ada index yang melibatkan kolom itu. */
    private function dropIndexesForColumn(string $table, string $column): void
    {
        $specs = array_merge(self::INDEXES[$table] ?? [], self::ORIGINAL_INDEXES[$table] ?? []);

        foreach ($specs as $spec) {
            $cols = $this->indexColumns($spec);
            if (! in_array($column, $cols, true)) {
                continue;
            }

            $name = $this->indexName($table, $spec);
            if (! Schema::hasIndex($table, $name)) {
                continue;
            }

            $unique = in_array('unique', $spec, true);
            Schema::table($table, function (Blueprint $t) use ($name, $unique): void {
                $unique ? $t->dropUnique($name) : $t->dropIndex($name);
            });
        }
    }

    private function indexName(string $table, array $spec): string
    {
        $cols = $this->indexColumns($spec);

        return $table.'_'.implode('_', $cols).(in_array('unique', $spec, true) ? '_unique' : '_index');
    }

    private function indexColumns(array $spec): array
    {
        return array_values(array_filter($spec, fn (string $c): bool => $c !== 'unique'));
    }
};

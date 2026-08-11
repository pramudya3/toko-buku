<?php

use Illuminate\Support\Facades\Schema;

/**
 * Semua kolom foreign key terindex eksplisit (plan §4) — Postgres tidak
 * meng-index FK otomatis. Index bisa tunggal, komposit, atau unique.
 */
it('indexes every foreign key column', function (string $table, array $columns): void {
    $indexed = collect(Schema::getIndexes($table))
        ->flatMap(fn (array $index): array => $index['columns'])
        ->all();

    foreach ($columns as $column) {
        expect($indexed)->toContain($column);
    }
})->with([
    'books.category_id' => ['books', ['category_id']],
    'orders.user_id' => ['orders', ['user_id']],
    'order_items' => ['order_items', ['order_id', 'book_id', 'book_edition_id']],
    'cash_flows.order_id' => ['cash_flows', ['order_id']],
    'dropshippers' => ['dropshippers', ['order_id', 'user_id']],
    'inventory_stocks' => ['inventory_stocks', ['book_id', 'warehouse_id']],
    'inventory_movements' => ['inventory_movements', ['book_id', 'book_edition_id', 'user_id', 'from_warehouse_id', 'to_warehouse_id']],
    'book_editions.book_id' => ['book_editions', ['book_id']],
    'book_edition_stocks' => ['book_edition_stocks', ['book_edition_id', 'warehouse_id']],
    'promotion_book' => ['promotion_book', ['promotion_id', 'book_id']],
    'receivables' => ['receivables', ['customer_id', 'order_id']],
    'receivable_payments.receivable_id' => ['receivable_payments', ['receivable_id']],
    'sales_returns' => ['sales_returns', ['order_id', 'user_id']],
    'sales_return_items' => ['sales_return_items', ['sales_return_id', 'order_item_id', 'book_id', 'book_edition_id']],
    'supplier_purchases' => ['supplier_purchases', ['supplier_id', 'user_id']],
    'supplier_purchase_items' => ['supplier_purchase_items', ['supplier_purchase_id', 'book_id']],
    'supplier_returns' => ['supplier_returns', ['supplier_id', 'supplier_purchase_id', 'user_id']],
    'supplier_return_items' => ['supplier_return_items', ['supplier_return_id', 'book_id']],
    'supplier_payments' => ['supplier_payments', ['supplier_id', 'supplier_purchase_id', 'user_id']],
]);

it('has a foreign key constraint for every app-level FK column', function (string $table, array $columns): void {
    $fkColumns = collect(Schema::getForeignKeys($table))->flatMap(fn (array $fk): array => $fk['columns']);

    foreach ($columns as $column) {
        expect($fkColumns)->toContain($column);
    }
})->with([
    'books.category_id' => ['books', ['category_id']],
    'orders.user_id' => ['orders', ['user_id']],
    'order_items' => ['order_items', ['order_id', 'book_id', 'book_edition_id']],
    'cash_flows.order_id' => ['cash_flows', ['order_id']],
    'dropshippers' => ['dropshippers', ['order_id', 'user_id']],
    'inventory_stocks' => ['inventory_stocks', ['book_id', 'warehouse_id']],
    'inventory_movements' => ['inventory_movements', ['book_id', 'book_edition_id', 'user_id', 'from_warehouse_id', 'to_warehouse_id']],
    'book_editions.book_id' => ['book_editions', ['book_id']],
    'book_edition_stocks' => ['book_edition_stocks', ['book_edition_id', 'warehouse_id']],
    'promotion_book' => ['promotion_book', ['promotion_id', 'book_id']],
    'receivables' => ['receivables', ['customer_id', 'order_id']],
    'receivable_payments.receivable_id' => ['receivable_payments', ['receivable_id']],
    'sales_returns' => ['sales_returns', ['order_id', 'user_id']],
    'sales_return_items' => ['sales_return_items', ['sales_return_id', 'order_item_id', 'book_id', 'book_edition_id']],
    'supplier_purchases' => ['supplier_purchases', ['supplier_id', 'user_id']],
    'supplier_purchase_items' => ['supplier_purchase_items', ['supplier_purchase_id', 'book_id']],
    'supplier_returns' => ['supplier_returns', ['supplier_id', 'supplier_purchase_id', 'user_id']],
    'supplier_return_items' => ['supplier_return_items', ['supplier_return_id', 'book_id']],
    'supplier_payments' => ['supplier_payments', ['supplier_id', 'supplier_purchase_id', 'user_id']],
]);

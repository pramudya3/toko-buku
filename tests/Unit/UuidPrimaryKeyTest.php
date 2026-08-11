<?php

use App\Models\Book;
use App\Models\BookEdition;
use App\Models\CashFlow;
use App\Models\Category;
use App\Models\Dropshipper;
use App\Models\InventoryMovement;
use App\Models\InventoryStock;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Promotion;
use App\Models\Receivable;
use App\Models\ReceivablePayment;
use App\Models\SalesReturn;
use App\Models\SalesReturnItem;
use App\Models\Setting;
use App\Models\Supplier;
use App\Models\SupplierPayment;
use App\Models\SupplierPurchase;
use App\Models\SupplierReturn;
use App\Models\TierDiscount;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Support\Str;

/**
 * Semua model bisnis memakai UUID v7 sebagai primary key (plan §7).
 * Model wilayah (Province/City/District/Village) sengaja TIDAK diikutkan —
 * mereka memakai `code` (string) sebagai PK model.
 */
it('generates a valid UUID v7 primary key for every business model', function (callable $create): void {
    $model = $create();

    expect($model->getKey())->toBeString()
        ->and(Str::isUuid((string) $model->getKey()))->toBeTrue()
        ->and(Str::substr((string) $model->getKey(), 14, 1))->toBe('7')
        ->and($model->getIncrementing())->toBeFalse()
        ->and($model->getKeyType())->toBe('string');
})->with([
    'users' => fn () => User::factory()->create(),
    'categories' => fn () => Category::factory()->create(),
    'books' => fn () => Book::factory()->create(),
    'book_editions' => fn () => BookEdition::factory()->create([
        // Buku dari Book::factory() sudah punya cetakan ke-1 (configure).
        'book_id' => Book::factory()->create()->id,
        'cetakan_ke' => 2,
    ]),
    'book_edition_stocks' => fn () => BookEdition::factory()->create([
        'book_id' => Book::factory()->create()->id,
        'cetakan_ke' => 2,
    ])
        ->stocks()
        ->create(['warehouse_id' => Warehouse::factory()->create()->id, 'qty' => 3]),
    'promotions' => fn () => Promotion::factory()->create(),
    'orders' => fn () => Order::factory()->create(),
    'order_items' => fn () => OrderItem::factory()->create(),
    'dropshippers' => fn () => Dropshipper::factory()->create(),
    'cash_flows' => fn () => CashFlow::factory()->create(),
    'warehouses' => fn () => Warehouse::factory()->create(),
    'inventory_stocks' => fn () => InventoryStock::factory()->create(),
    'inventory_movements' => fn () => InventoryMovement::factory()->create(),
    'suppliers' => fn () => Supplier::factory()->create(),
    'supplier_purchases' => fn () => SupplierPurchase::factory()->create(),
    'supplier_purchase_items' => fn () => SupplierPurchase::factory()->create()
        ->items()
        ->create(['book_id' => Book::factory()->create()->id, 'qty' => 2, 'price' => 10000, 'subtotal' => 20000]),
    'supplier_returns' => fn () => SupplierReturn::factory()->create(),
    'supplier_return_items' => fn () => SupplierReturn::factory()->create()
        ->items()
        ->create(['book_id' => Book::factory()->create()->id, 'qty' => 1, 'price' => 10000, 'subtotal' => 10000, 'reason' => 'rusak']),
    'supplier_payments' => fn () => SupplierPayment::factory()->create(),
    'tier_discounts' => fn () => TierDiscount::create([
        'tier' => 'reseller',
        'min_qty' => 5,
        'discount_percent' => 10,
    ]),
    'receivables' => fn () => Receivable::create([
        'customer_id' => User::factory()->create()->id,
        'amount' => 100000,
        'paid_amount' => 0,
    ]),
    'receivable_payments' => fn () => ReceivablePayment::create([
        'receivable_id' => Receivable::create([
            'customer_id' => User::factory()->create()->id,
            'amount' => 100000,
            'paid_amount' => 0,
        ])->id,
        'paid_at' => now(),
        'amount' => 50000,
        'metode' => 'transfer',
    ]),
    'sales_returns' => fn () => SalesReturn::create([
        'order_id' => Order::factory()->create()->id,
        'return_date' => now()->toDateString(),
        'total_refund' => 0,
    ]),
    'sales_return_items' => fn () => SalesReturnItem::create([
        'sales_return_id' => SalesReturn::create([
            'order_id' => Order::factory()->create()->id,
            'return_date' => now()->toDateString(),
            'total_refund' => 0,
        ])->id,
        'order_item_id' => OrderItem::factory()->create()->id,
        'book_id' => Book::factory()->create()->id,
        'qty' => 1,
        'price_refund' => 0,
        'condition' => 'baik',
    ]),
    'settings' => fn () => Setting::create(['key' => 'test-key-'.Str::random(6), 'value' => 'x']),
]);

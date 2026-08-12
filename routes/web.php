<?php

use App\Http\Controllers\Admin\ActivityLogController;
use App\Http\Controllers\Admin\AddressController;
use App\Http\Controllers\Admin\BankAccountController;
use App\Http\Controllers\Admin\BookController;
use App\Http\Controllers\Admin\CashFlowController;
use App\Http\Controllers\Admin\CategoryController;
use App\Http\Controllers\Admin\CourierController;
use App\Http\Controllers\Admin\CustomerController;
use App\Http\Controllers\Admin\DailyRecapController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\DropshipController;
use App\Http\Controllers\Admin\ImportTemplateController;
use App\Http\Controllers\Admin\InventoryAdjustmentController;
use App\Http\Controllers\Admin\InventoryController;
use App\Http\Controllers\Admin\InventoryReportController;
use App\Http\Controllers\Admin\OrderController;
use App\Http\Controllers\Admin\PaymentMethodController;
use App\Http\Controllers\Admin\PromotionController;
use App\Http\Controllers\Admin\ReceivableController;
use App\Http\Controllers\Admin\SalesChannelController;
use App\Http\Controllers\Admin\SalesReportController;
use App\Http\Controllers\Admin\SalesReturnController;
use App\Http\Controllers\Admin\SettingController;
use App\Http\Controllers\Admin\SupplierController;
use App\Http\Controllers\Admin\SupplierDebtController;
use App\Http\Controllers\Admin\SupplierPurchaseController;
use App\Http\Controllers\Admin\SupplierReportController;
use App\Http\Controllers\Admin\SupplierReturnController;
use App\Http\Controllers\Admin\TierDiscountController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Admin\WarehouseController;
use App\Http\Controllers\CheckoutController;
use App\Http\Controllers\MyOrderController;
use App\Http\Controllers\PublicAddressController;
use App\Http\Controllers\StorefrontController;
use Illuminate\Support\Facades\Route;

Route::get('/', [StorefrontController::class, 'catalog'])->name('home');

Route::get('wilayah/provinces', [PublicAddressController::class, 'provinces'])->name('wilayah.provinces');
Route::get('wilayah/cities', [PublicAddressController::class, 'cities'])->name('wilayah.cities');
Route::get('wilayah/districts', [PublicAddressController::class, 'districts'])->name('wilayah.districts');
Route::get('wilayah/villages', [PublicAddressController::class, 'villages'])->name('wilayah.villages');

Route::get('tentang-kami', [StorefrontController::class, 'about'])->name('about');
Route::get('buku', [StorefrontController::class, 'catalog'])->name('books.catalog');
Route::get('buku/lainnya', [StorefrontController::class, 'loadMore'])->name('books.load-more');
// URL publik: /buku/{uuid}-{judul} — lookup tetap pakai uuid (36 char pertama),
// judul hanya hiasan URL (dibersihkan via Str::slug). Lihat StorefrontController::show.
Route::get('buku/{bookUrl}', [StorefrontController::class, 'show'])
    ->where('bookUrl', '[a-f0-9-]{36}(-[a-z0-9-]+)?')
    ->name('books.show');

Route::get('checkout', [CheckoutController::class, 'index'])->name('checkout.index');
Route::post('checkout', [CheckoutController::class, 'store'])->name('checkout.store')->middleware('throttle:5,1');
Route::post('checkout/ongkir', [CheckoutController::class, 'shippingCosts'])->name('checkout.shipping-cost');
Route::get('checkout/sukses', [CheckoutController::class, 'success'])->name('checkout.success');
Route::post('keranjang', [CheckoutController::class, 'add'])->name('cart.add');
Route::post('keranjang/bulk', [CheckoutController::class, 'addBulk'])->name('cart.add-bulk');
Route::post('keranjang/{book}/remove', [CheckoutController::class, 'remove'])->name('cart.remove');
Route::post('keranjang/grup/toggle', [CheckoutController::class, 'toggleGroup'])->name('cart.toggle-group');
Route::post('keranjang/grup/hapus', [CheckoutController::class, 'removeGroup'])->name('cart.remove-group');
Route::post('keranjang/{book}/qty', [CheckoutController::class, 'updateQty'])->name('cart.qty');

Route::middleware(['auth'])->group(function () {
    Route::get('pesanan-saya', [MyOrderController::class, 'index'])->name('my-orders.index');
});

Route::redirect('admin/login', '/login')->name('admin.login');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::redirect('dashboard', '/')->name('dashboard');
});

require __DIR__.'/settings.php';

/*
|--------------------------------------------------------------------------
| Panel Admin /admin/** (AUTH-05)
|--------------------------------------------------------------------------
|
| Seluruh route admin dilindungi middleware `auth` + `admin` di backend.
|
*/
Route::middleware(['auth', 'admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::redirect('/', '/admin/dashboard');

    Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard');

    Route::get('imports/templates/{type}', [ImportTemplateController::class, 'download'])
        ->name('imports.template')
        ->whereIn('type', ['customers', 'books', 'categories', 'promotions', 'tier-discounts']);

    Route::resource('books', BookController::class)->except(['show']);
    Route::post('books/import', [BookController::class, 'importCsv'])->name('books.import');
    Route::post('books/{book}/restore', [BookController::class, 'restore'])->name('books.restore')->withTrashed();
    Route::patch('books/{book}/toggle-active', [BookController::class, 'toggleActive'])->name('books.toggle-active');

    Route::resource('categories', CategoryController::class)->except(['show']);
    Route::post('categories/import', [CategoryController::class, 'importCsv'])->name('categories.import');
    Route::post('categories/{category}/restore', [CategoryController::class, 'restore'])->name('categories.restore')->withTrashed();

    Route::get('customers', [CustomerController::class, 'index'])->name('customers.index');
    Route::get('customers/create', [CustomerController::class, 'create'])->name('customers.create');
    Route::post('customers', [CustomerController::class, 'store'])->name('customers.store');
    Route::get('customers/{user}/edit', [CustomerController::class, 'edit'])->name('customers.edit');
    Route::put('customers/{user}', [CustomerController::class, 'update'])->name('customers.update');
    Route::get('customers/{user}/summary', [CustomerController::class, 'summary'])->name('customers.summary');
    Route::post('customers/import', [CustomerController::class, 'importCsv'])->name('customers.import');

    // Nonaktifkan via is_active — tanpa hapus (destroy).
    Route::resource('users', UserController::class)->except(['show', 'destroy']);
    Route::patch('users/{user}/toggle-active', [UserController::class, 'toggleActive'])->name('users.toggle-active');

    Route::get('orders', [OrderController::class, 'index'])->name('orders.index');
    Route::get('orders/create', [OrderController::class, 'create'])->name('orders.create');
    Route::get('orders/options/books', [OrderController::class, 'bookOptions'])->name('orders.options.books');
    Route::get('orders/options/customers', [OrderController::class, 'customerOptions'])->name('orders.options.customers');
    Route::post('orders/cek-ongkir', [OrderController::class, 'checkOngkir'])->name('orders.check-ongkir');
    Route::post('orders', [OrderController::class, 'store'])->name('orders.store');
    Route::get('orders/{order}', [OrderController::class, 'show'])->name('orders.show');
    Route::get('orders/{order}/invoice', [OrderController::class, 'invoice'])->name('orders.invoice');
    Route::patch('orders/{order}/process', [OrderController::class, 'process'])->name('orders.process');
    Route::patch('orders/{order}/status', [OrderController::class, 'updateStatus'])->name('orders.status');
    Route::patch('orders/{order}/payment', [OrderController::class, 'confirmPayment'])->name('orders.payment');

    Route::get('promotions/options/books', [PromotionController::class, 'bookOptions'])->name('promotions.options.books');
    Route::resource('promotions', PromotionController::class)->except(['show']);
    Route::post('promotions/import', [PromotionController::class, 'importCsv'])->name('promotions.import');
    Route::patch('promotions/{promotion}/toggle', [PromotionController::class, 'toggle'])->name('promotions.toggle');
    Route::post('promotions/{promotion}/restore', [PromotionController::class, 'restore'])->name('promotions.restore')->withTrashed();

    Route::get('inventory', [InventoryController::class, 'index'])->name('inventory.index');
    Route::post('inventory/movements', [InventoryController::class, 'store'])->name('inventory.movements.store');

    // Koreksi stok opname fisik — tipe mutasi terpisah dari in/out/transfer.
    Route::get('inventory-adjustments/options/books', [InventoryAdjustmentController::class, 'bookOptions'])->name('inventory-adjustments.options.books');
    Route::get('inventory-adjustments', [InventoryAdjustmentController::class, 'index'])->name('inventory-adjustments.index');
    Route::post('inventory-adjustments', [InventoryAdjustmentController::class, 'store'])->name('inventory-adjustments.store');

    Route::post('warehouses/{warehouse}/restore', [WarehouseController::class, 'restore'])->name('warehouses.restore')->withTrashed();
    Route::resource('warehouses', WarehouseController::class)->except(['show']);

    Route::get('tier-discounts', [TierDiscountController::class, 'index'])->name('tier-discounts.index');
    Route::post('tier-discounts/import', [TierDiscountController::class, 'importCsv'])->name('tier-discounts.import');
    Route::post('tier-discounts', [TierDiscountController::class, 'store'])->name('tier-discounts.store');
    Route::put('tier-discounts/{tierDiscount}', [TierDiscountController::class, 'update'])->name('tier-discounts.update');
    Route::delete('tier-discounts/{tierDiscount}', [TierDiscountController::class, 'destroy'])->name('tier-discounts.destroy');
    Route::post('tier-discounts/{tierDiscount}/restore', [TierDiscountController::class, 'restore'])->name('tier-discounts.restore')->withTrashed();

    // Kas: pencatatan (index bulanan + detail 2 tabel) & laporan.
    Route::get('kas/laporan', [CashFlowController::class, 'laporan'])->name('kas.laporan');
    Route::post('kas/months', [CashFlowController::class, 'storeMonth'])->name('kas.months.store');
    Route::get('kas/{bulan}', [CashFlowController::class, 'detail'])->name('kas.detail')->where('bulan', '[0-9]{4}-[0-9]{2}');
    Route::get('kas', [CashFlowController::class, 'pencatatan'])->name('kas.index');
    Route::post('kas', [CashFlowController::class, 'store'])->name('kas.store');

    // Piutang pelanggan (bayar sebagian / cicilan).
    Route::get('receivables', [ReceivableController::class, 'index'])->name('receivables.index');
    Route::post('receivables', [ReceivableController::class, 'store'])->name('receivables.store');
    Route::post('receivables/{receivable}/payments', [ReceivableController::class, 'pay'])->name('receivables.payments.store');
    Route::delete('receivables/{receivable}', [ReceivableController::class, 'destroy'])->name('receivables.destroy');

    // Retur penjualan (barang dikembalikan pembeli).
    Route::get('sales-returns/options/orders', [SalesReturnController::class, 'orderOptions'])->name('sales-returns.options.orders');
    Route::get('sales-returns/orders/{order}', [SalesReturnController::class, 'orderDetail'])->name('sales-returns.orders.detail');
    Route::get('sales-returns/{salesReturn}/invoice', [SalesReturnController::class, 'invoice'])->name('sales-returns.invoice');
    Route::resource('sales-returns', SalesReturnController::class)->only(['index', 'store']);

    // Rekap harian penjualan (.xlsx).
    Route::get('daily-recap', [DailyRecapController::class, 'index'])->name('daily-recap.index');
    Route::get('daily-recap/export', [DailyRecapController::class, 'export'])->name('daily-recap.export');

    // Supplier: CRUD data saja — transaksi (barang masuk, retur, hutang,
    // laporan) dipindah ke menu masing-masing.
    Route::resource('suppliers', SupplierController::class)->except(['show']);
    Route::post('suppliers/{supplier}/restore', [SupplierController::class, 'restore'])->name('suppliers.restore')->withTrashed();

    // Barang masuk dari supplier (pembelian).
    Route::get('purchases/options/books', [SupplierPurchaseController::class, 'bookOptions'])->name('purchases.options.books');
    Route::get('purchases/{supplierPurchase}/invoice', [SupplierPurchaseController::class, 'invoice'])->name('purchases.invoice');
    Route::resource('purchases', SupplierPurchaseController::class)->only(['index', 'create', 'store']);

    // Retur barang ke supplier (dengan alasan).
    Route::get('supplier-returns/options/books', [SupplierReturnController::class, 'bookOptions'])->name('supplier-returns.options.books');
    Route::get('supplier-returns/options/purchases', [SupplierReturnController::class, 'purchaseOptions'])->name('supplier-returns.options.purchases');
    Route::get('supplier-returns/options/purchases/{supplierPurchase}', [SupplierReturnController::class, 'purchaseDetail'])->name('supplier-returns.options.purchases.detail');
    Route::resource('supplier-returns', SupplierReturnController::class)->only(['index', 'create', 'store']);

    // Hutang ke supplier (pembayaran secara hutang).
    Route::get('supplier-debts', [SupplierDebtController::class, 'index'])->name('supplier-debts.index');
    Route::post('supplier-debts/payments', [SupplierDebtController::class, 'store'])->name('supplier-debts.payments.store');

    // Laporan barang masuk / retur supplier (.xlsx).
    Route::get('supplier-reports', [SupplierReportController::class, 'index'])->name('supplier-reports.index');
    Route::get('supplier-reports/export', [SupplierReportController::class, 'export'])->name('supplier-reports.export');

    // Laporan penjualan (.xlsx) — filter periode, metode bayar & status.
    Route::get('sales-reports', [SalesReportController::class, 'index'])->name('sales-reports.index');
    Route::get('sales-reports/export', [SalesReportController::class, 'export'])->name('sales-reports.export');

    // Laporan mutasi stok (.xlsx) — filter periode, tipe, gudang & buku.
    Route::get('inventory-reports', [InventoryReportController::class, 'index'])->name('inventory-reports.index');
    Route::get('inventory-reports/export', [InventoryReportController::class, 'export'])->name('inventory-reports.export');

    Route::get('address/provinces', [AddressController::class, 'provinces'])->name('address.provinces');
    Route::get('address/cities', [AddressController::class, 'cities'])->name('address.cities');
    Route::get('address/districts', [AddressController::class, 'districts'])->name('address.districts');
    Route::get('address/villages', [AddressController::class, 'villages'])->name('address.villages');

    Route::get('dropship', [DropshipController::class, 'index'])->name('dropship.index');

    // Log aktivitas (audit).
    Route::get('aktivitas', [ActivityLogController::class, 'index'])->name('aktivitas.index');

    // Panduan penggunaan panel admin.
    Route::get('panduan', fn () => inertia('admin/Panduan'))->name('panduan');

    // Pengaturan toko: alamat & identitas digabung di halaman Lembaga.
    Route::redirect('settings', '/admin/settings/lembaga')->name('settings.index');
    Route::get('settings/lembaga', [SettingController::class, 'lembaga'])->name('settings.lembaga');
    Route::put('settings/lembaga', [SettingController::class, 'updateLembaga'])->name('settings.lembaga.update');

    // Pengaturan toko: rekening bank (soft delete + undo).
    Route::get('settings/rekening', [BankAccountController::class, 'index'])->name('settings.rekening');
    Route::post('settings/rekening', [BankAccountController::class, 'store'])->name('settings.rekening.store');
    Route::put('settings/rekening/{bankAccount}', [BankAccountController::class, 'update'])->name('settings.rekening.update');
    Route::delete('settings/rekening/{bankAccount}', [BankAccountController::class, 'destroy'])->name('settings.rekening.destroy');
    Route::post('settings/rekening/{bankAccount}/restore', [BankAccountController::class, 'restore'])->name('settings.rekening.restore')->withTrashed();

    // Pengaturan toko: pilihan ekspedisi & metode pembayaran (CRUD, soft delete + undo).
    Route::get('settings/ekspedisi', [SettingController::class, 'ekspedisi'])->name('settings.ekspedisi');
    Route::post('settings/ekspedisi', [CourierController::class, 'store'])->name('settings.ekspedisi.store');
    Route::put('settings/ekspedisi/{courier}', [CourierController::class, 'update'])->name('settings.ekspedisi.update');
    Route::delete('settings/ekspedisi/{courier}', [CourierController::class, 'destroy'])->name('settings.ekspedisi.destroy');
    Route::post('settings/ekspedisi/{courier}/restore', [CourierController::class, 'restore'])->name('settings.ekspedisi.restore')->withTrashed();
    Route::get('settings/pembayaran', [SettingController::class, 'pembayaran'])->name('settings.pembayaran');
    Route::post('settings/pembayaran', [PaymentMethodController::class, 'store'])->name('settings.pembayaran.store');
    Route::put('settings/pembayaran/{paymentMethod}', [PaymentMethodController::class, 'update'])->name('settings.pembayaran.update');
    Route::delete('settings/pembayaran/{paymentMethod}', [PaymentMethodController::class, 'destroy'])->name('settings.pembayaran.destroy');
    Route::post('settings/pembayaran/{paymentMethod}/restore', [PaymentMethodController::class, 'restore'])->name('settings.pembayaran.restore')->withTrashed();
    Route::get('settings/sumber-penjualan', [SettingController::class, 'sumberPenjualan'])->name('settings.sumber-penjualan');
    Route::post('settings/sumber-penjualan', [SalesChannelController::class, 'store'])->name('settings.sumber-penjualan.store');
    Route::put('settings/sumber-penjualan/{salesChannel}', [SalesChannelController::class, 'update'])->name('settings.sumber-penjualan.update');
    Route::delete('settings/sumber-penjualan/{salesChannel}', [SalesChannelController::class, 'destroy'])->name('settings.sumber-penjualan.destroy');
    Route::post('settings/sumber-penjualan/{salesChannel}/restore', [SalesChannelController::class, 'restore'])->name('settings.sumber-penjualan.restore')->withTrashed();

    // Pengaturan toko: API key Biteship (cek ongkir) — nilai asli hanya di server.
    Route::get('settings/api-key', [SettingController::class, 'apiKey'])->name('settings.api-key');
    Route::put('settings/api-key', [SettingController::class, 'updateApiKey'])->name('settings.api-key.update');
});

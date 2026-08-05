<?php

use App\Http\Controllers\Admin\AddressController;
use App\Http\Controllers\Admin\BookController;
use App\Http\Controllers\Admin\CashFlowController;
use App\Http\Controllers\Admin\CategoryController;
use App\Http\Controllers\Admin\CustomerController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\DropshipController;
use App\Http\Controllers\Admin\InventoryController;
use App\Http\Controllers\Admin\OrderController;
use App\Http\Controllers\Admin\PromotionController;
use App\Http\Controllers\Admin\TierDiscountController;
use App\Http\Controllers\CheckoutController;
use App\Http\Controllers\MyOrderController;
use App\Http\Controllers\PublicAddressController;
use App\Http\Controllers\StorefrontController;
use Illuminate\Support\Facades\Route;

Route::get('/', [StorefrontController::class, 'catalog'])->name('home');

Route::get('wilayah/provinces', [PublicAddressController::class, 'provinces'])->name('wilayah.provinces');
Route::get('wilayah/cities', [PublicAddressController::class, 'cities'])->name('wilayah.cities');
Route::get('wilayah/districts', [PublicAddressController::class, 'districts'])->name('wilayah.districts');

Route::get('tentang-kami', [StorefrontController::class, 'about'])->name('about');
Route::get('buku', [StorefrontController::class, 'catalog'])->name('books.catalog');
Route::get('buku/lainnya', [StorefrontController::class, 'loadMore'])->name('books.load-more');
Route::get('buku/{book}', [StorefrontController::class, 'show'])->name('books.show');

Route::get('checkout', [CheckoutController::class, 'index'])->name('checkout.index');
Route::post('checkout', [CheckoutController::class, 'store'])->name('checkout.store')->middleware('throttle:5,1');
Route::get('checkout/sukses', [CheckoutController::class, 'success'])->name('checkout.success');
Route::post('keranjang', [CheckoutController::class, 'add'])->name('cart.add');
Route::post('keranjang/{book}/remove', [CheckoutController::class, 'remove'])->name('cart.remove');
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
    Route::redirect('dashboard', '/admin/dashboard');

    Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard');

    Route::resource('books', BookController::class)->except(['show']);
    Route::post('books/{book}/restore', [BookController::class, 'restore'])->name('books.restore')->withTrashed();

    Route::resource('categories', CategoryController::class)->except(['show']);
    Route::post('categories/{category}/restore', [CategoryController::class, 'restore'])->name('categories.restore')->withTrashed();

    Route::get('customers', [CustomerController::class, 'index'])->name('customers.index');
    Route::get('customers/create', [CustomerController::class, 'create'])->name('customers.create');
    Route::post('customers', [CustomerController::class, 'store'])->name('customers.store');
    Route::get('customers/{user}/edit', [CustomerController::class, 'edit'])->name('customers.edit');
    Route::put('customers/{user}', [CustomerController::class, 'update'])->name('customers.update');
    Route::get('customers/{user}/summary', [CustomerController::class, 'summary'])->name('customers.summary');

    Route::get('orders', [OrderController::class, 'index'])->name('orders.index');
    Route::get('orders/create', [OrderController::class, 'create'])->name('orders.create');
    Route::get('orders/options/books', [OrderController::class, 'bookOptions'])->name('orders.options.books');
    Route::get('orders/options/customers', [OrderController::class, 'customerOptions'])->name('orders.options.customers');
    Route::post('orders', [OrderController::class, 'store'])->name('orders.store');
    Route::get('orders/{order}', [OrderController::class, 'show'])->name('orders.show');
    Route::patch('orders/{order}/process', [OrderController::class, 'process'])->name('orders.process');
    Route::patch('orders/{order}/status', [OrderController::class, 'updateStatus'])->name('orders.status');

    Route::get('promotions/options/books', [PromotionController::class, 'bookOptions'])->name('promotions.options.books');
    Route::resource('promotions', PromotionController::class)->except(['show']);
    Route::patch('promotions/{promotion}/toggle', [PromotionController::class, 'toggle'])->name('promotions.toggle');
    Route::post('promotions/{promotion}/restore', [PromotionController::class, 'restore'])->name('promotions.restore')->withTrashed();

    Route::get('inventory', [InventoryController::class, 'index'])->name('inventory.index');
    Route::post('inventory/movements', [InventoryController::class, 'store'])->name('inventory.movements.store');

    Route::get('tier-discounts', [TierDiscountController::class, 'index'])->name('tier-discounts.index');
    Route::post('tier-discounts', [TierDiscountController::class, 'store'])->name('tier-discounts.store');
    Route::put('tier-discounts/{tierDiscount}', [TierDiscountController::class, 'update'])->name('tier-discounts.update');
    Route::delete('tier-discounts/{tierDiscount}', [TierDiscountController::class, 'destroy'])->name('tier-discounts.destroy');
    Route::post('tier-discounts/{tierDiscount}/restore', [TierDiscountController::class, 'restore'])->name('tier-discounts.restore')->withTrashed();

    Route::get('cash-flow', [CashFlowController::class, 'index'])->name('cash-flow.index');

    Route::get('address/provinces', [AddressController::class, 'provinces'])->name('address.provinces');
    Route::get('address/cities', [AddressController::class, 'cities'])->name('address.cities');
    Route::get('address/districts', [AddressController::class, 'districts'])->name('address.districts');

    Route::get('dropship', [DropshipController::class, 'index'])->name('dropship.index');
});

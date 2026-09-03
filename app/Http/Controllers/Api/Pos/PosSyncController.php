<?php

namespace App\Http\Controllers\Api\Pos;

use App\Http\Controllers\Controller;
use App\Models\Book;
use App\Models\BookEdition;
use App\Models\BookEditionStock;
use App\Models\Category;
use App\Models\Courier;
use App\Models\PaymentMethod;
use App\Models\Promotion;
use App\Models\SalesChannel;
use App\Models\Setting;
use App\Models\TierDiscount;
use App\Models\User;
use App\Models\Voucher;
use App\Models\Warehouse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class PosSyncController extends Controller
{
    public function health(Request $request): JsonResponse
    {
        return response()->json([
            'server_time' => now()->toIso8601String(),
            'db_ok' => true,
            'version' => config('app.version', '1.0.0'),
            'warehouses' => Warehouse::query()->where('is_active', true)->get(['id', 'kode', 'nama', 'alamat']),
            'user' => [
                'id' => $request->user()->id,
                'name' => $request->user()->name,
            ],
        ]);
    }

    /**
     * Pull master incremental — pos offline-first sync.
     *
     * Query param: since=ISO8601, limit=500, include_deleted=0/1
     */
    public function pull(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'since' => ['nullable', 'date'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:1000'],
        ]);

        $since = isset($validated['since']) ? Carbon::parse($validated['since']) : Carbon::createFromTimestamp(0);
        $limit = (int) ($validated['limit'] ?? 500);

        $serverTime = now();

        // Books — include soft deleted? handle separately
        $books = Book::query()
            ->withTrashed()
            ->where('updated_at', '>', $since)
            ->orWhere('deleted_at', '>', $since)
            ->orderBy('updated_at')
            ->orderBy('id')
            ->limit($limit)
            ->get(['id', 'kode_sku', 'isbn', 'judul', 'penulis', 'penerbit', 'harga', 'stok', 'category_id', 'cover_url', 'aktif', 'is_preorder', 'preorder_eta', 'berat_gr', 'deleted_at', 'updated_at']);

        // Book editions
        $editions = BookEdition::query()
            ->where('updated_at', '>', $since)
            ->orderBy('updated_at')
            ->orderBy('id')
            ->limit($limit)
            ->get(['id', 'book_id', 'cetakan_ke', 'nama', 'harga_beli', 'harga_jual', 'harga_guru_type', 'harga_guru_value', 'is_active', 'updated_at']);

        // Edition stocks — all for warehouse toko + updated since
        $editionStocks = BookEditionStock::query()
            ->whereHas('warehouse', fn ($q) => $q->where('kode', 'toko')->orWhere('updated_at', '>', $since))
            ->orWhere('updated_at', '>', $since)
            ->orderBy('updated_at')
            ->orderBy('id')
            ->limit($limit)
            ->get(['id', 'book_edition_id', 'warehouse_id', 'qty', 'updated_at']);

        // But also ensure all toko stocks are included if since is old — incremental above already covers, but full toko stocks for new device
        if ($since->isBefore(now()->subDays(30))) {
            $tokoWarehouse = Warehouse::where('kode', 'toko')->first();
            if ($tokoWarehouse) {
                $editionStocks = BookEditionStock::where('warehouse_id', $tokoWarehouse->id)->get(['id', 'book_edition_id', 'warehouse_id', 'qty', 'updated_at']);
            }
        }

        $categories = Category::query()
            ->withTrashed()
            ->where('updated_at', '>', $since)
            ->orWhere('deleted_at', '>', $since)
            ->orderBy('updated_at')
            ->limit($limit)
            ->get(['id', 'nama', 'updated_at', 'deleted_at']);

        $warehouses = Warehouse::query()
            ->withTrashed()
            ->where('updated_at', '>', $since)
            ->orWhere('deleted_at', '>', $since)
            ->orderBy('updated_at')
            ->limit($limit)
            ->get(['id', 'kode', 'nama', 'alamat', 'is_defect', 'is_active', 'updated_at', 'deleted_at']);

        $customers = User::query()
            ->where('is_admin', false)
            ->withTrashed()
            ->where('updated_at', '>', $since)
            ->orWhere('deleted_at', '>', $since)
            ->orderBy('updated_at')
            ->limit($limit)
            ->get(['id', 'name', 'email', 'whatsapp_number', 'status_pelanggan', 'alamat', 'provinsi', 'kabupaten_kota', 'kecamatan', 'kelurahan', 'kode_pos', 'updated_at', 'deleted_at']);

        $promotions = Promotion::query()
            ->withTrashed()
            ->where('updated_at', '>', $since)
            ->orWhere('deleted_at', '>', $since)
            ->orderBy('updated_at')
            ->limit($limit)
            ->get();

        $tierDiscounts = TierDiscount::query()
            ->where('updated_at', '>', $since)
            ->orderBy('updated_at')
            ->limit($limit)
            ->get();

        $vouchers = Voucher::query()
            ->withTrashed()
            ->where('updated_at', '>', $since)
            ->orWhere('deleted_at', '>', $since)
            ->orderBy('updated_at')
            ->limit($limit)
            ->get();

        $couriers = Courier::query()
            ->withTrashed()
            ->where('updated_at', '>', $since)
            ->orWhere('deleted_at', '>', $since)
            ->orderBy('updated_at')
            ->limit($limit)
            ->get();

        $paymentMethods = PaymentMethod::query()
            ->withTrashed()
            ->where('updated_at', '>', $since)
            ->orWhere('deleted_at', '>', $since)
            ->orderBy('updated_at')
            ->limit($limit)
            ->get();

        $salesChannels = SalesChannel::query()
            ->withTrashed()
            ->where('updated_at', '>', $since)
            ->orWhere('deleted_at', '>', $since)
            ->orderBy('updated_at')
            ->limit($limit)
            ->get();

        $settings = Setting::query()
            ->where('updated_at', '>', $since)
            ->orderBy('updated_at')
            ->limit($limit)
            ->get(['id', 'key', 'value', 'updated_at']);

        // Count for has_more
        $hasMore = $books->count() === $limit || $editions->count() === $limit;

        return response()->json([
            'server_time' => $serverTime->toIso8601String(),
            'since' => $since->toIso8601String(),
            'next_since' => $serverTime->toIso8601String(),
            'has_more' => $hasMore,
            'data' => [
                'books' => $books,
                'editions' => $editions,
                'edition_stocks' => $editionStocks,
                'categories' => $categories,
                'warehouses' => $warehouses,
                'customers' => $customers,
                'promotions' => $promotions,
                'tier_discounts' => $tierDiscounts,
                'vouchers' => $vouchers,
                'couriers' => $couriers,
                'payment_methods' => $paymentMethods,
                'sales_channels' => $salesChannels,
                'settings' => $settings,
            ],
        ]);
    }

    public function books(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'barcode' => ['nullable', 'string', 'max:50'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);

        $limit = (int) ($validated['limit'] ?? 20);
        $query = Book::query()->where('aktif', true);

        if (! empty($validated['barcode'])) {
            $code = $validated['barcode'];
            $query->where(function ($q) use ($code) {
                $q->where('kode_sku', $code)->orWhere('isbn', $code);
            });
        } elseif (! empty($validated['search'])) {
            $search = $validated['search'];
            $query->where(function ($q) use ($search) {
                $q->whereLike('judul', "%{$search}%")
                    ->orWhereLike('kode_sku', "%{$search}%")
                    ->orWhereLike('isbn', "%{$search}%")
                    ->orWhereLike('penulis', "%{$search}%");
            });
        }

        $books = $query->with(['editions' => fn ($q) => $q->orderBy('cetakan_ke')])->orderBy('judul')->limit($limit)->get();

        return response()->json(['data' => $books]);
    }

    public function editions(Book $book): JsonResponse
    {
        $editions = $book->editions()->withSum(['stocks as sellable_total' => fn ($q) => $q->whereHas('warehouse', fn ($w) => $w->where('is_defect', false)->where('is_active', true))], 'qty')->orderBy('cetakan_ke')->get();

        return response()->json(['data' => $editions]);
    }
}

<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ActivityAction;
use App\Enums\CustomerTier;
use App\Enums\MovementType;
use App\Enums\PromotionType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreConsignmentDeliveryRequest;
use App\Http\Requests\Admin\StoreConsignmentReturnRequest;
use App\Http\Requests\Admin\StoreConsignmentSaleRequest;
use App\Models\Book;
use App\Models\ConsignmentDelivery;
use App\Models\ConsignmentDeliveryItem;
use App\Models\ConsignmentReturn;
use App\Models\ConsignmentSale;
use App\Models\ConsignmentSaleItem;
use App\Models\InventoryStock;
use App\Models\Receivable;
use App\Models\Setting;
use App\Models\TierDiscount;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\ConsignmentXlsxExporter;
use App\Services\InventoryService;
use App\Services\PricingService;
use App\Support\ActivityLogger;
use App\Support\Pagination;
use Carbon\CarbonInterface;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Konsinyasi (titip jual) — barang diserahkan ke mitra tier Bazaf tanpa
 * dibayar di depan. Penjualan baru diakui saat mitra melapor laku; laku
 * otomatis menjadi piutang (receivables). Sisa barang bisa diretur.
 *
 * Aliran stok: serah terima = stok keluar (Out), retur sisa = stok masuk (In).
 * Laku TIDAK mengubah stok lagi (barang sudah keluar saat serah terima).
 */
class ConsignmentController extends Controller
{
    public function __construct(
        private readonly InventoryService $inventory,
        private readonly ConsignmentXlsxExporter $exporter,
    ) {}

    /**
     * Halaman konsinyasi: pencatatan serah terima, laporan laku,
     * dan retur sisa.
     */
    public function index(Request $request): Response
    {
        return Inertia::render('admin/konsinyasi/Index', [
            'partners' => $this->bazafPartners(),
            'tierDiscounts' => TierDiscount::query()->orderBy('tier')->orderBy('min_qty')->get(['tier', 'min_qty', 'discount_percent']),
            'warehouses' => Warehouse::query()->orderBy('is_defect')->orderBy('nama')->get(['id', 'kode', 'nama']),
            'deliveries' => ConsignmentDelivery::query()
                ->with(['customer:id,name,whatsapp_number', 'items:id,delivery_id,book_id,qty,harga_asli,harga_titip', 'items.book:id,judul,harga'])
                ->orderByDesc('delivery_date')
                ->orderByDesc('id')
                ->paginate(Pagination::perPage($request))
                ->withQueryString(),
            'sales' => ConsignmentSale::query()
                ->with([
                    'customer:id,name,whatsapp_number',
                    'items:id,sale_id,book_id,qty,price',
                    'items.book:id,judul',
                    'receivable:id,amount,paid_amount',
                ])
                ->orderByDesc('sale_date')
                ->orderByDesc('id')
                ->paginate(Pagination::perPage($request))
                ->withQueryString(),
            'returns' => ConsignmentReturn::query()
                ->with(['customer:id,name,whatsapp_number', 'book:id,judul,kode_sku'])
                ->orderByDesc('return_date')
                ->orderByDesc('id')
                ->paginate(Pagination::perPage($request))
                ->withQueryString(),
        ]);
    }

    /**
     * Opsi buku untuk form serah terima (buku aktif, dengan stok & harga).
     */
    public function bookOptions(Request $request): JsonResponse
    {
        $search = trim($request->string('search')->toString());
        $warehouseId = $request->filled('warehouse_id') ? $request->string('warehouse_id')->toString() : null;
        $warehouse = $warehouseId ? Warehouse::query()->find($warehouseId) : null;

        $books = Book::query()
            ->where('aktif', true)
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($query) use ($search): void {
                    $query->whereLike('judul', "%{$search}%")
                        ->orWhereLike('kode_sku', "%{$search}%");
                });
            })
            ->orderBy('judul')
            ->paginate(Pagination::perPage($request, 20))
            ->withQueryString();

        // Stok per gudang — 1 query untuk seluruh halaman (hindari N+1).
        $stockByBook = collect();
        if ($warehouse !== null) {
            $stockByBook = InventoryStock::query()
                ->where('warehouse_id', $warehouse->id)
                ->whereIn('book_id', $books->getCollection()->pluck('id'))
                ->pluck('qty', 'book_id');
        }

        return response()->json([
            'data' => $books->getCollection()->transform(function (Book $book) use ($warehouse, $stockByBook): array {
                $stok = $warehouse
                    ? (int) ($stockByBook->get($book->id) ?? 0)
                    : (int) $book->stok;

                return [
                    'id' => $book->id,
                    'judul' => $book->judul,
                    'kode_sku' => $book->kode_sku,
                    'harga' => $book->harga,
                    'stok' => $stok,
                ];
            })->values(),
            'current_page' => $books->currentPage(),
            'last_page' => $books->lastPage(),
            'total' => $books->total(),
        ]);
    }

    /**
     * Sisa titipan per buku untuk satu mitra — dasar form lapor laku
     * dan retur. Jika ?kode=KSN-D-... diisi, hanya hitung untuk delivery itu (per kode, tidak akumulasi).
     */
    public function titipanOptions(Request $request, User $user): JsonResponse
    {
        $kode = $request->string('kode')->toString() ?: null;
        if ($kode) {
            $delivery = ConsignmentDelivery::where('kode', $kode)->where('customer_id', $user->id)->first();
            if (! $delivery) {
                return response()->json([]);
            }
            $items = DB::table('consignment_delivery_items')
                ->where('delivery_id', $delivery->id)
                ->groupBy('book_id')
                ->get([
                    'book_id',
                    DB::raw('SUM(qty) as qty'),
                    DB::raw('MAX(harga_asli) as harga_asli'),
                    DB::raw('MAX(harga_titip) as harga_titip'),
                ]);
            $delivered = $items->pluck('qty', 'book_id');
            $hargaMap = $items->pluck('harga_titip', 'book_id');
            $hargaAsliMap = $items->pluck('harga_asli', 'book_id');
            $bookIds = $delivered->keys()->all();
            $sold = DB::table('consignment_sale_items as csi')
                ->join('consignment_sales as cs', 'cs.id', '=', 'csi.sale_id')
                ->where('cs.customer_id', $user->id)
                ->whereIn('csi.book_id', $bookIds)
                ->groupBy('csi.book_id')
                ->selectRaw('csi.book_id, SUM(csi.qty) as qty')
                ->pluck('qty', 'csi.book_id');
            $returned = DB::table('consignment_returns')
                ->where('customer_id', $user->id)
                ->whereIn('book_id', $bookIds)
                ->groupBy('book_id')
                ->selectRaw('book_id, SUM(qty) as qty')
                ->pluck('qty', 'book_id');
            $remaining = $delivered->map(fn ($qty, $bookId) => $qty - (int) ($sold[$bookId] ?? 0) - (int) ($returned[$bookId] ?? 0))->filter(fn ($v) => $v > 0);
            if ($remaining->isEmpty()) {
                return response()->json([]);
            }
            $books = Book::query()->whereIn('id', $remaining->keys())->get(['id', 'judul', 'kode_sku', 'harga']);

            return response()->json(
                $books->map(function (Book $book) use ($remaining, $hargaMap, $hargaAsliMap): array {
                    $hargaAsli = (int) ($hargaAsliMap[$book->id] ?? $book->harga);
                    $hargaTitip = (int) ($hargaMap[$book->id] ?? $book->harga);
                    $diskon = max(0, $hargaAsli - $hargaTitip);

                    return [
                        'book_id' => $book->id,
                        'judul' => $book->judul,
                        'kode_sku' => $book->kode_sku,
                        'harga' => $book->harga,
                        'harga_asli' => $hargaAsli,
                        'harga_titip' => $hargaTitip,
                        'diskon' => $diskon,
                        'diskon_percent' => $hargaAsli > 0 ? (int) round($diskon * 100 / $hargaAsli) : 0,
                        'sisa' => (int) $remaining->get((string) $book->id, 0),
                    ];
                })->filter(fn (array $row): bool => $row['sisa'] > 0)->sortBy('judul')->values()->all(),
            );
        }

        $remaining = self::remainingMap((string) $user->id);

        if ($remaining->isEmpty()) {
            return response()->json([]);
        }

        $books = Book::query()->whereIn('id', $remaining->keys())->get(['id', 'judul', 'kode_sku', 'harga']);
        $tier = $user->status_pelanggan;
        $tierDiscountPercent = 0;
        if ($tier) {
            $tierDiscountPercent = (int) (TierDiscount::query()->where('tier', $tier->value ?? $tier)->where('min_qty', '<=', 1)->orderByDesc('min_qty')->value('discount_percent') ?? 0);
        }

        return response()->json(
            $books
                ->map(function (Book $book) use ($remaining, $tierDiscountPercent): array {
                    $hargaAsli = (int) $book->harga;
                    $diskon = intdiv($hargaAsli * $tierDiscountPercent, 100);
                    $hargaTitip = max(0, $hargaAsli - $diskon);
                    $promo = app(PricingService::class)->activePromotion($book);
                    $promoDiskon = 0;
                    if ($promo) {
                        $promoDiskon = $promo->promo_type === PromotionType::Percentage
                            ? intdiv($hargaTitip * ($promo->discount_percentage ?? 0), 100)
                            : min($promo->promo_value ?? 0, $hargaTitip);
                        $hargaTitip = max(0, $hargaTitip - $promoDiskon);
                        $diskon += $promoDiskon;
                    }

                    return [
                        'book_id' => $book->id,
                        'judul' => $book->judul,
                        'kode_sku' => $book->kode_sku,
                        'harga' => $book->harga,
                        'harga_asli' => $hargaAsli,
                        'harga_titip' => $hargaTitip,
                        'diskon' => $diskon,
                        'diskon_percent' => $hargaAsli > 0 ? (int) round($diskon * 100 / $hargaAsli) : 0,
                        'sisa' => (int) $remaining->get((string) $book->id, 0),
                    ];
                })
                ->filter(fn (array $row): bool => $row['sisa'] > 0)
                ->sortBy('judul')
                ->values()
                ->all(),
        );
    }

    /**
     * Catat serah terima barang ke mitra — stok toko berkurang (Out).
     */
    public function storeDelivery(StoreConsignmentDeliveryRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $adminId = $request->user()->id;
        $partner = User::query()->findOrFail((string) $validated['customer_id']);

        try {
            [$delivery] = DB::transaction(function () use ($validated, $adminId, $partner): array {
                /** @var Collection<int, Book> $books */
                $books = Book::query()
                    ->whereIn('id', collect($validated['items'])->pluck('book_id'))
                    ->get()
                    ->keyBy('id');

                $warehouse = isset($validated['warehouse_id']) ? Warehouse::findOrFail((string) $validated['warehouse_id']) : Warehouse::default();

                $delivery = ConsignmentDelivery::create([
                    'kode' => $this->generateKode('KSN-D', $validated['delivery_date']),
                    'customer_id' => (string) $validated['customer_id'],
                    'warehouse_id' => $warehouse->id,
                    'delivery_date' => $validated['delivery_date'],
                    'notes' => $validated['notes'] ?? null,
                    'user_id' => $adminId,
                ]);

                foreach ($validated['items'] as $row) {
                    $book = $books->get((string) $row['book_id']);

                    if ($book === null) {
                        throw new RuntimeException('Buku tidak ditemukan.');
                    }

                    ConsignmentDeliveryItem::create([
                        'delivery_id' => $delivery->id,
                        'book_id' => $book->id,
                        'qty' => (int) $row['qty'],
                    ]);

                    $this->inventory->move(
                        book: $book,
                        type: MovementType::Out,
                        qty: (int) $row['qty'],
                        from: $warehouse,
                        reference: "KSN-D-{$delivery->id}",
                        userId: $adminId,
                        notes: 'Serah terima konsinyasi',
                    );
                }

                // Set relation eksplisit — hindari lazy-load (preventLazyLoading).
                $delivery->setRelation('customer', $partner);

                return [$delivery];
            });
        } catch (RuntimeException $exception) {
            return back()->withErrors(['items' => $exception->getMessage()]);
        }

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Serah terima konsinyasi tercatat ('.$delivery->items()->count().' judul).',
        ]);

        ActivityLogger::log(ActivityAction::ConsignmentDeliverCreate, 'Serah terima konsinyasi ke '.$delivery->customer->name, $delivery);

        return back();
    }

    /**
     * Lapor laku dari mitra — pengakuan penjualan + piutang otomatis.
     */
    public function storeSale(StoreConsignmentSaleRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $customerId = (string) $validated['customer_id'];
        $adminId = $request->user()->id;
        $partner = User::query()->findOrFail($customerId);

        try {
            [$sale] = DB::transaction(function () use ($validated, $customerId, $adminId, &$receivable): array {
                // Serialisasi laku/retur per mitra supaya validasi sisa
                // titipan tidak balapan dengan request paralel.
                DB::table('consignment_deliveries')
                    ->where('customer_id', $customerId)
                    ->lockForUpdate()
                    ->get();

                $remaining = self::remainingMap($customerId);
                $titles = Book::query()->whereIn('id', collect($validated['items'])->pluck('book_id'))->pluck('judul', 'id');

                foreach ($validated['items'] as $row) {
                    $sisa = (int) $remaining->get((string) $row['book_id'], 0);

                    if ((int) $row['qty'] > $sisa) {
                        throw new RuntimeException(
                            'Qty laku "'.($titles[(string) $row['book_id']] ?? 'buku').'" melebihi sisa titipan (sisa '.$sisa.').',
                        );
                    }
                }

                $total = collect($validated['items'])
                    ->sum(fn (array $row): int => (int) $row['qty'] * (int) $row['price']);

                $sale = ConsignmentSale::create([
                    'kode' => $this->generateKode('KSN-S', $validated['sale_date']),
                    'customer_id' => $customerId,
                    'sale_date' => $validated['sale_date'],
                    'notes' => $validated['notes'] ?? null,
                    'user_id' => $adminId,
                ]);

                foreach ($validated['items'] as $row) {
                    ConsignmentSaleItem::create([
                        'sale_id' => $sale->id,
                        'book_id' => (string) $row['book_id'],
                        'qty' => (int) $row['qty'],
                        'price' => (int) $row['price'],
                    ]);
                }

                // Laku konsinyasi langsung menjadi piutang mitra —
                // pelunasan dicatat lewat menu Piutang yang sudah ada.
                $receivable = Receivable::create([
                    'customer_id' => $customerId,
                    'amount' => $total,
                    'notes' => 'Konsinyasi '.$sale->sale_date->format('d/m/Y'),
                ]);

                $sale->update(['receivable_id' => $receivable->id]);

                return [$sale];
            });

            // Set relation eksplisit — hindari lazy-load (preventLazyLoading).
            $sale->setRelation('customer', $partner);
            $sale->setRelation('receivable', $receivable);
        } catch (RuntimeException $exception) {
            return back()->withErrors(['items' => $exception->getMessage()]);
        }

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Laporan laku tercatat — piutang Rp '.number_format($sale->receivable?->amount ?? 0, 0, ',', '.').'.',
        ]);

        ActivityLogger::log(ActivityAction::ConsignmentSaleCreate, 'Lapor laku konsinyasi dari '.$sale->customer->name, $sale);

        return back();
    }

    /**
     * Retur sisa barang dari mitra — stok kembali ke toko (In).
     */
    public function storeReturn(StoreConsignmentReturnRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $customerId = (string) $validated['customer_id'];
        $adminId = $request->user()->id;
        $partner = User::query()->findOrFail($customerId);

        try {
            [$retur] = DB::transaction(function () use ($validated, $customerId, $adminId): array {
                DB::table('consignment_deliveries')
                    ->where('customer_id', $customerId)
                    ->lockForUpdate()
                    ->get();

                $remaining = self::remainingMap($customerId);
                $sisa = (int) $remaining->get((string) $validated['book_id'], 0);

                if ((int) $validated['qty'] > $sisa) {
                    throw new RuntimeException('Qty retur melebihi sisa titipan (sisa '.$sisa.').');
                }

                $book = Book::query()->findOrFail((string) $validated['book_id']);
                // Gudang retur otomatis = gudang asal serah terima terakhir untuk buku ini
                $deliveryWarehouseId = ConsignmentDelivery::where('customer_id', $customerId)
                    ->whereIn('id', DB::table('consignment_delivery_items')
                        ->where('book_id', $book->id)
                        ->select('delivery_id'))
                    ->orderByDesc('delivery_date')
                    ->value('warehouse_id');
                $warehouse = $deliveryWarehouseId ? Warehouse::query()->find($deliveryWarehouseId) : Warehouse::default();
                if (! $warehouse) {
                    $warehouse = Warehouse::default();
                }

                $retur = ConsignmentReturn::create([
                    'kode' => $this->generateKode('KSN-R', $validated['return_date']),
                    'customer_id' => $customerId,
                    'warehouse_id' => $warehouse->id,
                    'book_id' => $book->id,
                    'qty' => (int) $validated['qty'],
                    'return_date' => $validated['return_date'],
                    'notes' => $validated['notes'] ?? null,
                    'user_id' => $adminId,
                ]);

                $this->inventory->move(
                    book: $book,
                    type: MovementType::In,
                    qty: (int) $validated['qty'],
                    to: $warehouse,
                    reference: "KSN-R-{$retur->id}",
                    userId: $adminId,
                    notes: 'Retur konsinyasi',
                );

                return [$retur];
            });

            $retur->setRelation('customer', $partner);
        } catch (RuntimeException $exception) {
            return back()->withErrors(['qty' => $exception->getMessage()]);
        }

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Retur konsinyasi tercatat ('.$retur->qty.' eks).',
        ]);

        ActivityLogger::log(ActivityAction::ConsignmentReturnCreate, 'Retur konsinyasi dari '.$retur->customer->name, $retur);

        return back();
    }

    /**
     * Hapus serah terima (salah catat) — hanya bila tidak ada laku/retur
     * yang sudah memakai stok titipannya; stok dikembalikan ke toko.
     */
    public function destroyDelivery(ConsignmentDelivery $delivery): RedirectResponse
    {
        try {
            DB::transaction(function () use ($delivery): void {
                DB::table('consignment_deliveries')
                    ->where('customer_id', $delivery->customer_id)
                    ->lockForUpdate()
                    ->get();

                $remaining = self::remainingMap((string) $delivery->customer_id);
                $delivery->loadMissing('items.book');

                foreach ($delivery->items as $item) {
                    if ($item->qty > (int) $remaining->get((string) $item->book_id, 0)) {
                        throw new RuntimeException(
                            'Tidak dapat dihapus: buku "'.$item->book->judul.'" sudah ada laku/returnya.',
                        );
                    }
                }

                $warehouse = Warehouse::default();

                foreach ($delivery->items as $item) {
                    $this->inventory->move(
                        book: $item->book,
                        type: MovementType::In,
                        qty: $item->qty,
                        to: $warehouse,
                        reference: "KSN-D-{$delivery->id}-hapus",
                        userId: auth()->id(),
                        notes: 'Pembatalan serah terima konsinyasi',
                    );
                }

                $delivery->delete();
            });
        } catch (RuntimeException $exception) {
            Inertia::flash('toast', ['type' => 'error', 'message' => $exception->getMessage()]);

            return back();
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Serah terima dihapus — stok dikembalikan.']);

        ActivityLogger::log(ActivityAction::ConsignmentDelete, 'Hapus serah terima konsinyasi', $delivery);

        return back();
    }

    /**
     * Hapus laporan laku (salah catat) — hanya bila piutangnya belum
     * dibayar; piutang ikut dihapus.
     */
    public function destroySale(ConsignmentSale $sale): RedirectResponse
    {
        try {
            DB::transaction(function () use ($sale): void {
                $receivable = $sale->receivable()->first();

                if ($receivable !== null && $receivable->paid_amount > 0) {
                    throw new RuntimeException('Tidak dapat dihapus: piutang konsinyasi ini sudah ada pembayaran.');
                }

                $receivable?->delete();
                $sale->delete();
            });
        } catch (RuntimeException $exception) {
            Inertia::flash('toast', ['type' => 'error', 'message' => $exception->getMessage()]);

            return back();
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Laporan laku dihapus beserta piutangnya.']);

        ActivityLogger::log(ActivityAction::ConsignmentDelete, 'Hapus laporan laku konsinyasi', $sale);

        return back();
    }

    /**
     * Hapus retur (salah catat) — stok dikeluarkan kembali dari toko.
     */
    public function destroyReturn(ConsignmentReturn $retur): RedirectResponse
    {
        try {
            DB::transaction(function () use ($retur): void {
                $book = $retur->book()->firstOrFail();

                $this->inventory->move(
                    book: $book,
                    type: MovementType::Out,
                    qty: $retur->qty,
                    from: Warehouse::default(),
                    reference: "KSN-R-{$retur->id}-hapus",
                    userId: auth()->id(),
                    notes: 'Pembatalan retur konsinyasi',
                );

                $retur->delete();
            });
        } catch (RuntimeException $exception) {
            Inertia::flash('toast', ['type' => 'error', 'message' => 'Tidak dapat dihapus: '.$exception->getMessage()]);

            return back();
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Retur dihapus — stok dikeluarkan kembali.']);

        ActivityLogger::log(ActivityAction::ConsignmentDelete, 'Hapus retur konsinyasi', $retur);

        return back();
    }

    /**
     * Halaman laporan konsinyasi: preview tabel transaksi (kolom sama
     * dengan export .xlsx) dengan filter periode, mitra & jenis.
     */
    public function laporan(Request $request): Response
    {
        [$from, $to, $customerId, $jenis] = $this->laporanFilters($request);

        $rows = $this->exporter->buildRows($from, $to, $customerId, $jenis);

        $page = max(1, (int) $request->query('page', 1));
        $perPage = Pagination::perPage($request);

        $paginated = new LengthAwarePaginator(
            $rows->forPage($page, $perPage)->values(),
            $rows->count(),
            $perPage,
            $page,
            ['path' => LengthAwarePaginator::resolveCurrentPath()],
        );

        return Inertia::render('admin/konsinyasi/Laporan', [
            'rows' => $paginated->withQueryString(),
            'partners' => $this->bazafPartners(),
            // Ringkasan posisi titipan dihitung dari SELURUH data
            // (tidak terpengaruh filter periode).
            'stockSummary' => [
                'delivered_qty' => $this->stockRows()->sum('delivered_qty'),
                'sold_qty' => $this->stockRows()->sum('sold_qty'),
                'returned_qty' => $this->stockRows()->sum('returned_qty'),
                'remaining_qty' => $this->stockRows()->sum('remaining_qty'),
            ],
            'filters' => [
                'from' => $from->toDateString(),
                'to' => $to->toDateString(),
                'customer_id' => $customerId,
                'jenis' => $jenis,
            ],
        ]);
    }

    /**
     * Export laporan konsinyasi ke .xlsx.
     */
    public function exportLaporan(Request $request): StreamedResponse
    {
        [$from, $to, $customerId, $jenis] = $this->laporanFilters($request);

        $rows = $this->exporter->buildRows($from, $to, $customerId, $jenis);

        return $this->exporter->download($rows, $from, $to, $customerId, $jenis);
    }

    /**
     * Filter laporan: periode (default 30 hari terakhir), mitra & jenis.
     *
     * @return array{CarbonInterface, CarbonInterface, string|null, string}
     */
    private function laporanFilters(Request $request): array
    {
        $from = $request->filled('from')
            ? Date::parse($request->string('from')->toString())->startOfDay()
            : Date::today()->subDays(30)->startOfDay();

        $to = $request->filled('to')
            ? Date::parse($request->string('to')->toString())->endOfDay()
            : Date::today()->endOfDay();

        if ($from->greaterThan($to)) {
            [$from, $to] = [$to, $from];
        }

        $customerId = $request->filled('customer_id') ? $request->string('customer_id')->toString() : null;
        $jenis = in_array($request->string('jenis')->toString(), ['serah_terima', 'laku', 'retur'], true)
            ? $request->string('jenis')->toString()
            : 'semua';

        return [$from, $to, $customerId, $jenis];
    }

    /**
     * Daftar mitra konsinyasi — customer aktif tier Bazaf.
     *
     * @return Collection<int, array<string, mixed>>
     */
    private function bazafPartners(): Collection
    {
        return User::query()
            ->where('is_admin', false)
            ->where('status_pelanggan', CustomerTier::Bazaf)
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'whatsapp_number', 'status_pelanggan']);
    }

    public function deliveryInvoice(ConsignmentDelivery $delivery): Response
    {
        $delivery->load([
            'customer:id,name,whatsapp_number,alamat,provinsi,kabupaten_kota,kecamatan,kelurahan,kode_pos,status_pelanggan',
            'items.book:id,judul,kode_sku,harga',
        ]);
        $tier = $delivery->customer?->status_pelanggan;
        $delivery->setRelation('items', $delivery->items->map(function ($item) use ($tier) {
            $hargaAsli = (int) ($item->harga_asli ?? $item->book->harga ?? 0);
            if (! empty($item->harga_titip)) {
                $item->setAttribute('harga_asli', $hargaAsli);

                return $item;
            }
            $qty = (int) $item->qty;
            $pct = 0;
            if ($tier) {
                $pct = (int) (TierDiscount::query()->where('tier', $tier->value ?? $tier)->where('min_qty', '<=', $qty)->orderByDesc('min_qty')->value('discount_percent') ?? 0);
            }
            $diskon = intdiv($hargaAsli * $pct, 100);
            $item->setAttribute('harga_titip', max(0, $hargaAsli - $diskon));
            $item->setAttribute('harga_asli', $hargaAsli);

            return $item;
        }));

        return Inertia::render('print/konsinyasi/DeliveryInvoice', [
            'delivery' => $delivery,
            'store' => [
                'nama' => Setting::get('store_nama_lembaga') ?: config('app.name'),
                'logo_url' => Setting::get('store_logo_url', ''),
                'alamat' => Setting::get('store_alamat', ''),
                'npwp' => Setting::get('store_npwp', ''),
                'telepon' => Setting::get('store_telepon', ''),
                'email' => Setting::get('store_email', ''),
            ],
        ]);
    }

    public function saleInvoice(ConsignmentSale $sale): Response
    {
        $sale->load([
            'customer:id,name,whatsapp_number,alamat,provinsi,kabupaten_kota,kecamatan,kelurahan,kode_pos',
            'items.book:id,judul,kode_sku,harga',
            'receivable:id,amount,paid_amount',
        ]);

        return Inertia::render('print/konsinyasi/SaleInvoice', [
            'sale' => $sale,
            'store' => [
                'nama' => Setting::get('store_nama_lembaga') ?: config('app.name'),
                'logo_url' => Setting::get('store_logo_url', ''),
                'alamat' => Setting::get('store_alamat', ''),
                'npwp' => Setting::get('store_npwp', ''),
                'telepon' => Setting::get('store_telepon', ''),
                'email' => Setting::get('store_email', ''),
            ],
        ]);
    }

    private function generateKode(string $prefix, string $date): string
    {
        $ymd = str_replace('-', '', substr($date, 0, 10));
        $like = $prefix.'-'.$ymd.'-%';
        $table = $prefix === 'KSN-D' ? 'consignment_deliveries' : ($prefix === 'KSN-S' ? 'consignment_sales' : 'consignment_returns');

        // Selalu dipanggil dalam DB::transaction — lock baris prefix biar
        // request paralel tidak menghasilkan kode duplikat.
        $last = DB::table($table)
            ->where('kode', 'like', $like)
            ->orderByDesc('kode')
            ->lockForUpdate()
            ->value('kode');
        $seq = $last ? ((int) substr($last, -4)) + 1 : 1;

        return $prefix.'-'.$ymd.'-'.str_pad((string) $seq, 4, '0', STR_PAD_LEFT);
    }

    /**
     * Ringkasan titipan per mitra+buku: diserahkan − laku − retur = sisa.
     *
     * @return Collection<int, array<string, mixed>>
     */
    private function stockRows(): Collection
    {
        $delivered = DB::table('consignment_delivery_items as cdi')
            ->join('consignment_deliveries as cd', 'cd.id', '=', 'cdi.delivery_id')
            ->groupBy('cd.customer_id', 'cdi.book_id')
            ->selectRaw('cd.customer_id, cdi.book_id, SUM(cdi.qty) AS qty');

        $sold = DB::table('consignment_sale_items as csi')
            ->join('consignment_sales as cs', 'cs.id', '=', 'csi.sale_id')
            ->groupBy('cs.customer_id', 'csi.book_id')
            ->selectRaw('cs.customer_id, csi.book_id, SUM(csi.qty) AS qty');

        $returned = DB::table('consignment_returns as cr')
            ->groupBy('cr.customer_id', 'cr.book_id')
            ->selectRaw('cr.customer_id, cr.book_id, SUM(cr.qty) AS qty');

        return DB::query()
            ->fromSub($delivered, 'd')
            ->leftJoinSub($sold, 's', function (Builder $join): void {
                $join->on('s.customer_id', '=', 'd.customer_id')->on('s.book_id', '=', 'd.book_id');
            })
            ->leftJoinSub($returned, 'r', function (Builder $join): void {
                $join->on('r.customer_id', '=', 'd.customer_id')->on('r.book_id', '=', 'd.book_id');
            })
            ->join('books as b', 'b.id', '=', 'd.book_id')
            ->join('users as u', 'u.id', '=', 'd.customer_id')
            ->orderBy('u.name')
            ->orderBy('b.judul')
            ->get([
                'd.customer_id',
                'u.name as customer_name',
                'u.whatsapp_number as customer_wa',
                'd.book_id',
                'b.judul as book_judul',
                'b.kode_sku as kode_sku',
                'b.harga as book_harga',
                'd.qty as delivered_qty',
                DB::raw('COALESCE(s.qty, 0) AS sold_qty'),
                DB::raw('COALESCE(r.qty, 0) AS returned_qty'),
                DB::raw('d.qty - COALESCE(s.qty, 0) - COALESCE(r.qty, 0) AS remaining_qty'),
            ])
            ->map(fn (object $row): array => [
                'customer_id' => (string) $row->customer_id,
                'customer_name' => (string) $row->customer_name,
                'customer_wa' => $row->customer_wa !== null ? (string) $row->customer_wa : null,
                'book_id' => (string) $row->book_id,
                'book_judul' => (string) $row->book_judul,
                'kode_sku' => $row->kode_sku !== null ? (string) $row->kode_sku : null,
                'book_harga' => (int) $row->book_harga,
                'delivered_qty' => (int) $row->delivered_qty,
                'sold_qty' => (int) $row->sold_qty,
                'returned_qty' => (int) $row->returned_qty,
                'remaining_qty' => (int) $row->remaining_qty,
            ]);
    }

    /**
     * Sisa titipan per buku untuk satu mitra (delivered − sold − returned).
     *
     * @return Collection<string, int>
     */
    private static function remainingMap(string $customerId): Collection
    {
        $delivered = DB::table('consignment_delivery_items as cdi')
            ->join('consignment_deliveries as cd', 'cd.id', '=', 'cdi.delivery_id')
            ->where('cd.customer_id', $customerId)
            ->groupBy('cdi.book_id')
            ->selectRaw('cdi.book_id, SUM(cdi.qty) AS qty')
            ->pluck('qty', 'cdi.book_id');

        $sold = DB::table('consignment_sale_items as csi')
            ->join('consignment_sales as cs', 'cs.id', '=', 'csi.sale_id')
            ->where('cs.customer_id', $customerId)
            ->groupBy('csi.book_id')
            ->selectRaw('csi.book_id, SUM(csi.qty) AS qty')
            ->pluck('qty', 'csi.book_id');

        $returned = DB::table('consignment_returns')
            ->where('customer_id', $customerId)
            ->groupBy('book_id')
            ->selectRaw('book_id, SUM(qty) AS qty')
            ->pluck('qty', 'book_id');

        return $delivered
            ->map(fn (int $qty, string $bookId): int => $qty - (int) ($sold[$bookId] ?? 0) - (int) ($returned[$bookId] ?? 0));
    }
}

<?php

namespace App\Http\Controllers\Admin;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\OrderProcessRequest;
use App\Http\Requests\Admin\OrderStatusRequest;
use App\Http\Requests\Admin\OrderStoreRequest;
use App\Models\Book;
use App\Models\Order;
use App\Models\Setting;
use App\Models\TierDiscount;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\InventoryService;
use App\Services\OrderStatusService;
use App\Services\PricingService;
use App\Services\ShippingCostService;
use App\Support\StoreSettings;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;

class OrderController extends Controller
{
    public function __construct(
        private readonly PricingService $pricing,
        private readonly OrderStatusService $statusService,
        private readonly InventoryService $inventoryService,
        private readonly ShippingCostService $shippingCost,
    ) {}

    /**
     * List order: filter status, pencarian, sort tanggal (ORD-01).
     */
    public function index(Request $request): Response
    {
        $orders = Order::query()
            ->with('user:id,name')
            ->withCount('items')
            ->when($request->filled('search'), function ($query) use ($request): void {
                $search = $request->string('search')->toString();

                $query->where(function ($query) use ($search): void {
                    $query->whereLike('no_order', "%{$search}%")
                        ->orWhereLike('nama_pembeli', "%{$search}%");
                });
            })
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')->toString()))
            ->when($request->boolean('dropship'), fn ($query) => $query->where('is_dropship', true))
            ->when($request->filled('sumber_pembelian'), fn ($query) => $query->where('sumber_pembelian', $request->string('sumber_pembelian')->toString()))
            ->orderByDesc('created_at')
            ->paginate(10)
            ->withQueryString();

        return Inertia::render('admin/orders/Index', [
            'orders' => $orders,
            'filters' => $request->only(['search', 'status', 'dropship', 'sumber_pembelian']),
            'statusOptions' => OrderStatus::options(),
            'salesChannels' => StoreSettings::allSalesChannels(),
        ]);
    }

    /**
     * Form buat order manual (ORD-03).
     */
    public function create(): Response
    {
        return Inertia::render('admin/orders/Create', [
            'customers' => User::where('is_admin', false)->orderBy('name')->limit(50)->get(['id', 'name', 'whatsapp_number', 'status_pelanggan', 'alamat', 'provinsi', 'kabupaten_kota', 'kecamatan', 'kode_pos']),
            'books' => Book::where('aktif', true)->orderBy('judul')->limit(50)->get(['id', 'judul', 'kode_sku', 'harga', 'stok']),
            'paymentOptions' => StoreSettings::enabledPaymentMethods(),
            'couriers' => StoreSettings::enabledCouriers(),
            'salesChannels' => StoreSettings::enabledSalesChannels(),
            'tierDiscounts' => TierDiscount::query()
                ->orderBy('tier')
                ->orderBy('min_qty')
                ->get(['tier', 'min_qty', 'discount_percent']),
        ]);
    }

    /**
     * Search active books for the manual-order picker.
     */
    public function bookOptions(Request $request): JsonResponse
    {
        $search = trim($request->string('search')->toString());

        $books = Book::query()
            ->where('aktif', true)
            ->with('editions:id,book_id,cetakan_ke,harga_jual,is_active')
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($query) use ($search): void {
                    $query->whereLike('judul', "%{$search}%")
                        ->orWhereLike('kode_sku', "%{$search}%");
                });
            })
            ->orderBy('judul')
            ->paginate(20)
            ->withQueryString();

        return response()->json([
            'data' => $books->items(),
            'current_page' => $books->currentPage(),
            'last_page' => $books->lastPage(),
            'total' => $books->total(),
        ]);
    }

    /**
     * Search customer options for the manual-order picker.
     */
    public function customerOptions(Request $request): JsonResponse
    {
        $search = trim($request->string('search')->toString());

        return response()->json(
            User::query()
                ->where('is_admin', false)
                ->when($search !== '', function ($query) use ($search): void {
                    $query->where(function ($query) use ($search): void {
                        $query->whereLike('name', "%{$search}%")
                            ->orWhereLike('email', "%{$search}%")
                            ->orWhereLike('whatsapp_number', "%{$search}%");
                    });
                })
                ->orderBy('name')
                ->limit(50)
                ->get(['id', 'name', 'whatsapp_number', 'status_pelanggan', 'alamat', 'provinsi', 'kabupaten_kota', 'kecamatan', 'kode_pos']),
        );
    }

    /**
     * Cek ongkir utk order manual (admin) — alamat tujuan + berat items.
     * Memakai ShippingCostService yang sama dgn storefront → cache 24 jam shared.
     */
    public function checkOngkir(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'postal_code' => ['required', 'string', 'max:10'],
            'weight_kg' => ['required', 'numeric', 'min:0.01', 'max:200'],
        ]);

        // Biteship butuh items[] — admin order manual, pakai 1 item dgn total berat.
        $items = [[
            'name' => 'Paket Buku',
            'value' => 1,
            'quantity' => 1,
            'weight_grams' => (int) ($validated['weight_kg'] * 1000),
        ]];

        try {
            $costs = $this->shippingCost->costs($validated['postal_code'], $items);
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'weight_kg' => (float) $validated['weight_kg'],
            'costs' => $costs,
        ]);
    }

    /**
     * Simpan order manual (ORD-03, ORD-08).
     */
    public function store(OrderStoreRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $isDropship = $request->boolean('is_dropship');
        $order = null;

        // Ongkir di-verify ulang di server (cache shared) — jangan percaya nilai client.
        $shippingCost = 0;
        $shippingEstimation = null;
        $courierCode = $data['ekspedisi'] ?? null;

        if ($courierCode !== null && $courierCode !== '') {
            $kodePos = $data['kode_pos'] ?? null;

            if (empty($kodePos)) {
                return back()->withErrors(['ekspedisi' => 'Pilih alamat lengkap (kode pos) untuk menghitung ongkir.']);
            }

            try {
                $items = $this->orderItemsForBiteship($data['items']);
                $matched = collect($this->shippingCost->costs($kodePos, $items))
                    ->firstWhere('courier_code', $courierCode);

                if ($matched === null) {
                    throw new RuntimeException('Ekspedisi tidak valid — pilih ulang dari daftar ongkir.');
                }

                $shippingCost = (int) $matched['price'];
                $shippingEstimation = $matched['estimation'] ?? null;
            } catch (RuntimeException $e) {
                return back()->withErrors(['ekspedisi' => $e->getMessage()]);
            }
        }

        for ($attempt = 0; $attempt < 3; $attempt++) {
            try {
                $order = DB::transaction(function () use ($data, $isDropship, $courierCode, $shippingCost, $shippingEstimation): Order {
                    $order = new Order([
                        'no_order' => $this->generateOrderNumber(),
                        'user_id' => $data['user_id'] ?? null,
                        'nama_pembeli' => $data['nama_pembeli'],
                        'no_hp' => $data['whatsapp_pembeli'] ?? null,
                        'alamat' => $data['alamat'] ?? null,
                        'provinsi' => $data['provinsi'] ?? null,
                        'kabupaten_kota' => $data['kabupaten_kota'] ?? null,
                        'kecamatan' => $data['kecamatan'] ?? null,
                        'kelurahan' => $data['kelurahan'] ?? null,
                        'kode_pos' => $data['kode_pos'] ?? null,
                        'metode_bayar' => $data['metode_bayar'],
                        'sumber_pembelian' => $data['sumber_pembelian'] ?? null,
                        'total' => 0,
                        'ekspedisi' => $courierCode,
                        'shipping_cost' => $shippingCost,
                        'ongkir_estimasi' => $shippingEstimation,
                        'is_dropship' => $isDropship,
                        'status' => OrderStatus::MenungguKonfirmasi,
                    ]);

                    $this->pricing->storeOrderWithItems($order, $data['items']);

                    if ($order->is_dropship) {
                        $order->dropshipper()->create([
                            'user_id' => $data['user_id'] ?? null,
                            'end_customer_name' => $data['end_customer_name'],
                            'end_customer_whatsapp' => $data['end_customer_whatsapp'] ?? null,
                            'end_customer_address' => $data['end_customer_address'] ?? null,
                        ]);
                    }

                    return $order->fresh();
                });

                break;
            } catch (UniqueConstraintViolationException $exception) {
                if ($attempt === 2) {
                    throw $exception;
                }
            }
        }

        if ($order === null) {
            throw new RuntimeException('Order gagal dibuat.');
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => "Order {$order->no_order} berhasil dibuat."]);

        return to_route('admin.orders.show', $order);
    }

    /**
     * Detail order (ORD-02, DROP-01).
     */
    public function show(Order $order): Response
    {
        $order->load([
            'user:id,name,whatsapp_number,status_pelanggan',
            'items.book:id,judul,kode_sku,cover_url',
            'dropshipper',
            'cashFlows',
        ]);

        return Inertia::render('admin/orders/Show', [
            'order' => $order,
            'statusOptions' => OrderStatus::options(),
            'couriers' => StoreSettings::enabledCouriers(),
            'paymentMethods' => StoreSettings::allPaymentMethods(),
            'salesChannels' => StoreSettings::allSalesChannels(),
            'warehouseOptions' => Warehouse::query()
                ->sellable()
                ->orderBy('nama')
                ->pluck('nama', 'kode')
                ->all(),
        ]);
    }

    /**
     * Nota/invoice penjualan — halaman print (A4), tanpa layout admin.
     */
    public function invoice(Order $order): Response
    {
        $order->load([
            'items:id,order_id,book_id,book_edition_id,judul_snapshot,edition_snapshot,qty,price_original,promo_discount_amount,tier_discount_amount,price_final',
            'dropshipper',
        ]);

        return Inertia::render('print/orders/Invoice', [
            'order' => $order,
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

    /**
     * Konfirmasi order: isi ongkir final + ekspedisi + gudang asal → diproses (ORD-05).
     */
    public function process(OrderProcessRequest $request, Order $order): RedirectResponse
    {
        try {
            DB::transaction(function () use ($request, $order): void {
                $lockedOrder = Order::query()
                    ->lockForUpdate()
                    ->with('items.book', 'items.edition', 'user')
                    ->findOrFail($order->getKey());

                if (! $this->statusService->canTransition($lockedOrder, OrderStatus::Diproses)) {
                    throw new RuntimeException(
                        "Transisi status tidak valid: {$lockedOrder->status->value} → ".OrderStatus::Diproses->value.'.',
                    );
                }

                $lockedOrder->update([
                    'shipping_cost' => $request->integer('shipping_cost'),
                    'ekspedisi' => $request->string('ekspedisi')->toString(),
                    'warehouse_origin' => $request->string('warehouse_origin')->toString(),
                ]);

                $warehouseOrigin = Warehouse::query()
                    ->sellable()
                    ->where('kode', $request->string('warehouse_origin')->toString())
                    ->firstOrFail();

                foreach ($lockedOrder->items as $item) {
                    $this->inventoryService->assertSufficientStock(
                        $item->book,
                        $warehouseOrigin,
                        $item->qty,
                        $item->edition,
                    );
                }

                // Reserve stok saat diproses — bukan menunggu sampai selesai,
                // supaya order lain tidak mengambil stok yang sama (BR-05).
                $this->inventoryService->deductForOrder($lockedOrder, $request->user()->id);

                // Total dihitung ulang: subtotal + ongkir final.
                $this->pricing->applyToOrder($lockedOrder);

                $this->statusService->transition($lockedOrder, OrderStatus::Diproses, $request->user()->id);
            });

            Inertia::flash('toast', ['type' => 'success', 'message' => "Order {$order->no_order} diproses."]);
        } catch (RuntimeException $exception) {
            Inertia::flash('toast', ['type' => 'error', 'message' => $exception->getMessage()]);
        }

        return back();
    }

    /**
     * Transisi status umum: dikirim / selesai / batal (ORD-04, BR-05).
     */
    public function updateStatus(OrderStatusRequest $request, Order $order): RedirectResponse
    {
        $target = OrderStatus::from($request->string('status')->toString());

        try {
            $this->statusService->transition($order, $target, $request->user()->id);

            Inertia::flash('toast', [
                'type' => 'success',
                'message' => "Order {$order->no_order} berstatus {$target->label()}.",
            ]);
        } catch (RuntimeException $exception) {
            Inertia::flash('toast', ['type' => 'error', 'message' => $exception->getMessage()]);
        }

        return back();
    }

    /**
     * Konfirmasi pembayaran: payment_status menunggu → lunas (ORD-08).
     */
    public function confirmPayment(Order $order): RedirectResponse
    {
        if ($order->payment_status === PaymentStatus::Lunas) {
            Inertia::flash('toast', ['type' => 'info', 'message' => "Order {$order->no_order} sudah berstatus lunas."]);

            return back();
        }

        $order->update(['payment_status' => PaymentStatus::Lunas]);

        Inertia::flash('toast', ['type' => 'success', 'message' => "Pembayaran order {$order->no_order} dikonfirmasi lunas."]);

        return back();
    }

    /**
     * Nomor order unik: ORD-YYYYMMDD-XXXX (urutan per hari).
     */
    /**
     * Bangun items[] untuk payload Biteship dari data items form admin.
     *
     * @param  array<int, array{book_id: int, qty: int}>  $rows
     * @return array<int, array{name: string, value: int, quantity: int, weight_grams: int}>
     */
    private function orderItemsForBiteship(array $rows): array
    {
        $books = Book::query()
            ->whereKey(collect($rows)->pluck('book_id'))
            ->get(['id', 'judul', 'harga', 'berat_gr'])
            ->keyBy('id');

        $items = [];

        foreach ($rows as $row) {
            $book = $books->get((string) $row['book_id']);

            if ($book === null) {
                continue;
            }

            $items[] = [
                'name' => $book->judul,
                'value' => $book->harga,
                'quantity' => (int) $row['qty'],
                'weight_grams' => max(100, (int) ($book->berat_gr ?? 100)),
            ];
        }

        return $items;
    }

    private function generateOrderNumber(): string
    {
        $prefix = 'ORD-'.now()->format('Ymd');

        $last = Order::where('no_order', 'like', $prefix.'-%')
            ->lockForUpdate()
            ->orderByDesc('no_order')
            ->value('no_order');

        $sequence = $last ? ((int) substr($last, -4)) + 1 : 1;

        return $prefix.'-'.str_pad((string) $sequence, 4, '0', STR_PAD_LEFT);
    }
}

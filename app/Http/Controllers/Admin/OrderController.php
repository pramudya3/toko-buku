<?php

namespace App\Http\Controllers\Admin;

use App\Enums\FulfillmentMethod;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\OrderProcessRequest;
use App\Http\Requests\Admin\OrderStatusRequest;
use App\Http\Requests\Admin\OrderStoreRequest;
use App\Http\Requests\Admin\ProcessAndShipRequest;
use App\Models\Book;
use App\Models\Order;
use App\Models\Setting;
use App\Models\TierDiscount;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\BiteshipShippingService;
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
        private readonly BiteshipShippingService $biteship,
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
                        ->orWhereLike('kode_sku', "%{$search}%")
                        ->orWhereLike('penulis', "%{$search}%")
                        ->orWhereLike('penterjemah', "%{$search}%");
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
                ->get(['id', 'name', 'whatsapp_number', 'status_pelanggan', 'alamat', 'provinsi', 'kabupaten_kota', 'kecamatan', 'kelurahan', 'village_code', 'kode_pos']),
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
            // Berat 0 diperbolehkan (buku tanpa berat) — service floor ke 1 gram.
            'weight_kg' => ['required', 'numeric', 'min:0', 'max:200'],
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

        // Metode pengambilan: kirim / ambil sendiri — default ambil sendiri
        // (toko & marketplace = pencatatan; kirim dipilih eksplisit).
        $fulfillment = $data['metode_pengambilan']
            ?? FulfillmentMethod::Ambil->value;

        // Ambil sendiri → tanpa ongkir (ekspedisi diabaikan).
        if ($fulfillment === FulfillmentMethod::Ambil->value) {
            $courierCode = null;
        }

        if ($courierCode !== null && $courierCode !== '') {
            $kodePos = $data['kode_pos'] ?? null;

            if (empty($kodePos)) {
                return back()->withErrors(['ekspedisi' => 'Pilih alamat lengkap (kode pos) untuk menghitung ongkir.']);
            }

            try {
                $items = $this->orderItemsForBiteship($data['items']);
                $serviceCode = $data['courier_service_code'] ?? null;
                $matched = collect($this->shippingCost->costs($kodePos, $items))
                    ->first(function (array $rate) use ($courierCode, $serviceCode): bool {
                        if ($rate['courier_code'] !== $courierCode) {
                            return false;
                        }

                        return $serviceCode === null || $serviceCode === ''
                            || strtolower((string) $rate['service_code']) === strtolower((string) $serviceCode);
                    });

                if ($matched === null) {
                    throw new RuntimeException('Ekspedisi tidak valid — pilih ulang dari daftar ongkir.');
                }

                $shippingCost = (int) $matched['price'];
                $shippingEstimation = $matched['estimation'] ?? null;
            } catch (RuntimeException $e) {
                return back()->withErrors(['ekspedisi' => $e->getMessage()]);
            }
        }

        // Resolve customer terdaftar bila tidak dipilih di picker — order
        // (mis. pembelian toko) otomatis tampil di "Pesanan Saya" customer.
        $userId = $data['user_id'] ?? null;

        if (! is_string($userId) || $userId === '') {
            $userId = $this->resolveCustomerId($data['nama_pembeli'], $data['whatsapp_pembeli'] ?? null);
        }

        // Pembayaran cash (di kasir) → langsung lunas; transfer → menunggu
        // konfirmasi admin (verifikasi manual).
        $paymentStatus = ($data['metode_bayar'] ?? null) === PaymentMethod::Cash->value
            ? PaymentStatus::Lunas
            : PaymentStatus::Menunggu;

        try {
            for ($attempt = 0; $attempt < 3; $attempt++) {
                try {
                    $order = DB::transaction(function () use ($request, $data, $isDropship, $courierCode, $shippingCost, $shippingEstimation, $userId, $paymentStatus, $fulfillment): Order {
                        $order = new Order([
                            'no_order' => $this->generateOrderNumber(),
                            'user_id' => $userId,
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
                            'courier_service_code' => $data['courier_service_code'] ?? null,
                            'shipping_cost' => $shippingCost,
                            'ongkir_estimasi' => $shippingEstimation,
                            'is_dropship' => $isDropship,
                            'status' => OrderStatus::MenungguKonfirmasi,
                            'payment_status' => $paymentStatus,
                            'metode_pengambilan' => $fulfillment,
                        ]);

                        $this->pricing->storeOrderWithItems($order, $data['items']);

                        if ($order->is_dropship) {
                            $order->dropshipper()->create([
                                'user_id' => $userId,
                                'end_customer_name' => $data['end_customer_name'],
                                'end_customer_whatsapp' => $data['end_customer_whatsapp'] ?? null,
                                'end_customer_address' => $data['end_customer_address'] ?? null,
                            ]);
                        }

                        // Cash → langsung diproses: stok otomatis terpotong dari
                        // gudang default (menunggu konfirmasi hanya utk transfer).
                        if ($paymentStatus === PaymentStatus::Lunas) {
                            $this->processCashOrder($order, (string) $request->user()->id);
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
        } catch (RuntimeException $exception) {
            return back()->withErrors(['items' => $exception->getMessage()]);
        }

        if ($order === null) {
            throw new RuntimeException('Order gagal dibuat.');
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => "Order {$order->no_order} berhasil dibuat."]);

        return to_route('admin.orders.show', $order);
    }

    /**
     * Order cash langsung diproses: cek stok gudang default, potong stok,
     * hitung ulang total, transisi → diproses.
     */
    private function processCashOrder(Order $order, string $userId): void
    {
        $warehouse = Warehouse::query()
            ->sellable()
            ->orderBy('kode')
            ->first();

        if ($warehouse === null) {
            throw new RuntimeException('Tidak ada gudang aktif untuk memproses pesanan.');
        }

        $order->warehouse_origin = $warehouse->kode;
        $order->save();

        $order->load('items.book', 'items.edition');

        foreach ($order->items as $item) {
            $this->inventoryService->assertSufficientStock(
                $item->book,
                $warehouse,
                $item->qty,
                $item->edition,
            );
        }

        $this->inventoryService->deductForOrder($order, $userId);
        $this->pricing->applyToOrder($order);
        $this->statusService->transition($order, OrderStatus::Diproses, $userId);
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
            // Template link tracking (mis. lacak Wahana) untuk auto-isi saat
            // input resi manual — placeholder {awb} diganti nomor resi.
            'trackingUrlTemplate' => config('shipping.tracking_url_template', ''),
            // Data origin toko untuk pengiriman manual (pengirim) & cek kelengkapan.
            'storeNamaLembaga' => Setting::get('store_nama_lembaga', ''),
            'storeTelepon' => Setting::get('store_telepon', ''),
            'storeEmail' => Setting::get('store_email', ''),
            'storeAlamat' => Setting::get('store_alamat', ''),
            'originPostalCode' => Setting::get('origin_postal_code', ''),
        ]);
    }

    /**
     * Booking pengiriman Biteship → AWB + label terbit (saldo terpotong).
     */
    public function createShipping(Request $request, Order $order): RedirectResponse
    {
        try {
            $result = $this->biteship->createOrder(
                $order,
                $request->string('courier')->toString(),
                $request->string('service')->toString() ?: null,
            );

            Inertia::flash('toast', [
                'type' => 'success',
                'message' => "Pengiriman {$order->no_order} dibuat — AWB: ".($result['waybill_id'] ?? '').'.',
            ]);
        } catch (RuntimeException $exception) {
            Inertia::flash('toast', ['type' => 'error', 'message' => $exception->getMessage()]);
        }

        return back();
    }

    /**
     * Batalkan pengiriman di Biteship (prasyarat sebelum order boleh batal).
     */
    public function cancelShipping(Order $order): RedirectResponse
    {
        try {
            $this->biteship->cancelOrder($order);

            Inertia::flash('toast', [
                'type' => 'success',
                'message' => "Pengiriman {$order->no_order} dibatalkan.",
            ]);
        } catch (RuntimeException $exception) {
            Inertia::flash('toast', ['type' => 'error', 'message' => $exception->getMessage()]);
        }

        return back();
    }

    /**
     * Jadwalkan penjemputan kurir (tanggal/jam opsional, default langsung).
     */
    public function requestPickup(Request $request, Order $order): RedirectResponse
    {
        try {
            $this->biteship->requestPickup(
                $order,
                $request->string('pickup_date')->toString() ?: null,
                $request->string('pickup_time')->toString() ?: null,
            );

            Inertia::flash('toast', [
                'type' => 'success',
                'message' => "Penjemputan {$order->no_order} dijadwalkan.",
            ]);
        } catch (RuntimeException $exception) {
            Inertia::flash('toast', ['type' => 'error', 'message' => $exception->getMessage()]);
        }

        return back();
    }

    /**
     * Sinkronkan status pengiriman terbaru dari Biteship.
     */
    public function refreshShipping(Order $order): RedirectResponse
    {
        try {
            $data = $this->biteship->retrieveOrder($order);

            $status = is_string($data['status'] ?? null) ? $data['status'] : null;
            $transitioned = $status !== null
                ? $this->statusService->applyBiteshipStatus($order, $status)
                : false;

            $message = 'Status pengiriman: '.($status ?? '-').'.';

            if ($transitioned) {
                $message .= ' Status order diperbarui ke '.$order->fresh()->status->label().'.';
            }

            Inertia::flash('toast', ['type' => 'success', 'message' => $message]);
        } catch (RuntimeException $exception) {
            Inertia::flash('toast', ['type' => 'error', 'message' => $exception->getMessage()]);
        }

        return back();
    }

    /**
     * Buka label pengiriman (PDF dari Biteship) di tab baru.
     */
    public function shippingLabel(Order $order): RedirectResponse
    {
        if (! $order->biteship_label_url) {
            abort(404);
        }

        return redirect()->away($order->biteship_label_url);
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
            DB::transaction(fn () => $this->processOrder($order, (string) $request->user()->id, $request->validated()));

            Inertia::flash('toast', ['type' => 'success', 'message' => "Order {$order->no_order} diproses."]);
        } catch (RuntimeException $exception) {
            Inertia::flash('toast', ['type' => 'error', 'message' => $exception->getMessage()]);
        }

        return back();
    }

    /**
     * Alur gabungan "Proses & Kirim": konfirmasi lunas (opsional) + proses +
     * booking Biteship + penjadwalan pickup — satu klik admin.
     *
     * Bila booking gagal, order tetap diproses sehingga dialog bisa dibuka
     * lagi sebagai retry booking.
     */
    public function processAndShip(ProcessAndShipRequest $request, Order $order): RedirectResponse
    {
        try {
            $order = DB::transaction(function () use ($request, $order): Order {
                $lockedOrder = Order::query()
                    ->lockForUpdate()
                    ->whereKey($order->getKey())
                    ->firstOrFail();

                // Konfirmasi pembayaran bila dicentang (gabung 1 langkah).
                if ($lockedOrder->payment_status !== PaymentStatus::Lunas
                    && $request->boolean('konfirmasi_lunas')) {
                    $lockedOrder->update(['payment_status' => PaymentStatus::Lunas]);
                }

                // Proses bila masih menunggu konfirmasi.
                if ($lockedOrder->status === OrderStatus::MenungguKonfirmasi) {
                    $this->processOrder($lockedOrder, (string) $request->user()->id, $request->validated());
                }

                return $lockedOrder->fresh();
            });
        } catch (RuntimeException $exception) {
            Inertia::flash('toast', ['type' => 'error', 'message' => $exception->getMessage()]);

            return back();
        }

        $collectionMethod = $request->string('collection_method')->toString() ?: 'pickup';
        $courier = $request->string('ekspedisi')->toString() ?: $order->ekspedisi ?? '';
        $service = $request->string('courier_service_code')->toString() ?: $order->courier_service_code;

        // Order non-website (toko/marketplace) tanpa pengiriman kurir —
        // cukup proses, tanpa booking Biteship.
        if ($order->sumber_pembelian !== 'website') {
            return back();
        }

        // Booking hanya bila belum pernah dibuat (biteship_order_id, bukan awb —
        // AWB bisa terbit asinkron). Sudah booked → sinkron status/waybill.
        if ($order->biteship_order_id === null) {
            try {
                $result = $this->biteship->createOrder(
                    $order,
                    $courier,
                    $service ?: null,
                    $collectionMethod,
                );

                $order->update(['shipping_collection_method' => $collectionMethod]);

                $waybill = $result['waybill_id'] ?? null;

                Inertia::flash('toast', [
                    'type' => 'success',
                    'message' => $waybill !== null && $waybill !== ''
                        ? "Pengiriman {$order->no_order} dibuat — AWB: {$waybill}."
                        : "Pengiriman {$order->no_order} dibuat — AWB akan terbit sebentar lagi, klik Refresh Status untuk memeriksa.",
                ]);
            } catch (RuntimeException $exception) {
                $message = $exception->getMessage();

                // Biteship menolak rute yang tidak didukung kurir — beri tahu
                // admin solusinya, bukan sekadar error mentah.
                if (str_contains(strtolower($message), 'route not found')) {
                    $message .= ' Rute tidak didukung kurir ini — coba kurir lain.';
                }

                Inertia::flash('toast', [
                    'type' => 'error',
                    'message' => $message.' Order sudah diproses — buka dialog lagi untuk mencoba ulang pengiriman.',
                ]);

                return back();
            }
        } elseif ($order->awb === null) {
            // Sudah dibooking tapi AWB belum terbit (asinkron) — tarik data
            // terbaru dari Biteship supaya retry tidak membuat duplikat.
            try {
                $data = $this->biteship->retrieveOrder($order);
                $this->statusService->applyBiteshipStatus($order, (string) ($data['status'] ?? ''));

                Inertia::flash('toast', [
                    'type' => 'success',
                    'message' => "Pengiriman {$order->no_order} disinkronkan — status: ".($data['status'] ?? '-').'.',
                ]);
            } catch (RuntimeException $exception) {
                Inertia::flash('toast', [
                    'type' => 'error',
                    'message' => $exception->getMessage().' Pengiriman sudah dibuat — coba Refresh Status.',
                ]);

                return back();
            }
        }

        // Jadwalkan penjemputan hanya untuk metode pickup.
        if (($order->shipping_collection_method ?? 'pickup') === 'pickup') {
            try {
                $this->biteship->requestPickup(
                    $order,
                    $request->string('pickup_date')->toString() ?: null,
                    $request->string('pickup_time')->toString() ?: null,
                );

                Inertia::flash('toast', [
                    'type' => 'success',
                    'message' => "Penjemputan {$order->no_order} dijadwalkan.",
                ]);
            } catch (RuntimeException $exception) {
                $message = $exception->getMessage();

                if (str_contains(strtolower($message), 'route not found')) {
                    $message .= ' Kurir ini tidak mendukung penjemputan — gunakan metode Antar ke Agen (batalkan & buat ulang) atau hubungi kurir langsung.';
                }

                Inertia::flash('toast', [
                    'type' => 'error',
                    'message' => "Pengiriman {$order->no_order} sudah dibuat, tetapi penjadwalan penjemputan gagal: {$message}",
                ]);
            }
        }

        return back();
    }

    /**
     * Proses order (shared oleh process & processAndShip): verifikasi,
     * reserve stok, transisi → diproses, total final.
     *
     * @param  array<string, mixed>  $data
     */
    private function processOrder(Order $order, string $userId, array $data): void
    {
        $lockedOrder = Order::query()
            ->lockForUpdate()
            ->with('items.book', 'items.edition', 'user')
            ->whereKey($order->getKey())
            ->firstOrFail();

        if (! $this->statusService->canTransition($lockedOrder, OrderStatus::Diproses)) {
            throw new RuntimeException(
                "Transisi status tidak valid: {$lockedOrder->status->value} → ".OrderStatus::Diproses->value.'.',
            );
        }

        // Channel utama (website/toko) wajib lunas sebelum diproses —
        // menunggu konfirmasi hanya utk pembayaran transfer. Marketplace
        // hanya pencatatan (proses di-handle platform e-commerce).
        if ($lockedOrder->isMainChannel()
            && $lockedOrder->payment_status !== PaymentStatus::Lunas) {
            throw new RuntimeException('Pembayaran belum dikonfirmasi lunas — konfirmasi pembayaran terlebih dahulu.');
        }

        $lockedOrder->update([
            // Ongkir memakai nilai tersimpan bila tidak dikirim ulang.
            'shipping_cost' => (int) ($data['shipping_cost'] ?? $lockedOrder->shipping_cost),
            'ekspedisi' => isset($data['ekspedisi']) && $data['ekspedisi'] !== ''
                ? (string) $data['ekspedisi']
                : null,
            'courier_service_code' => isset($data['courier_service_code']) && $data['courier_service_code'] !== ''
                ? (string) $data['courier_service_code']
                : null,
            'warehouse_origin' => (string) $data['warehouse_origin'],
        ]);

        $warehouseOrigin = Warehouse::query()
            ->sellable()
            ->where('kode', (string) $data['warehouse_origin'])
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
        $this->inventoryService->deductForOrder($lockedOrder, $userId);

        // Total dihitung ulang: subtotal + ongkir final.
        $this->pricing->applyToOrder($lockedOrder);

        $this->statusService->transition($lockedOrder, OrderStatus::Diproses, $userId);
    }

    /**
     * Transisi status umum: dikirim / selesai / batal (ORD-04, BR-05).
     */
    public function updateStatus(OrderStatusRequest $request, Order $order): RedirectResponse
    {
        $target = OrderStatus::from($request->string('status')->toString());

        // Order yang sudah dibooking di Biteship wajib dibatalkan pengirimannya
        // dulu — kalau tidak, kurir tetap datang & saldo terpotong.
        if ($target === OrderStatus::Batal
            && $order->biteship_order_id !== null
            && $order->biteship_status !== 'cancelled') {
            Inertia::flash('toast', [
                'type' => 'error',
                'message' => 'Batalkan pengiriman Biteship terlebih dahulu.',
            ]);

            return back();
        }

        // Resi wajib sebelum order channel utama ditandai dikirim (mode
        // kirim) — dari input manual di "Proses Kirim".
        if ($target === OrderStatus::Dikirim
            && $order->isMainChannel()
            && ($order->metode_pengambilan ?? 'kirim') !== 'ambil'
            && $order->awb === null
            && trim($request->string('awb')->toString()) === '') {
            Inertia::flash('toast', [
                'type' => 'error',
                'message' => 'Masukkan No. Resi terlebih dahulu sebelum menandai dikirim.',
            ]);

            return back();
        }

        // Simpan resi & link tracking (booking Biteship atau manual).
        $awb = trim($request->string('awb')->toString());

        if ($target === OrderStatus::Dikirim && $awb !== '') {
            $order->update([
                'awb' => $awb,
                'biteship_courier_link' => $request->string('biteship_courier_link')->toString() ?: null,
            ]);
        }

        try {
            $this->statusService->transition($order, $target, (string) $request->user()->id);

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
     * Cari customer terdaftar dari nama/WhatsApp — hanya bila cocok TEPAT
     * satu user (non-admin); ambigu → null (order tetap bisa dibuat manual).
     */
    private function resolveCustomerId(string $name, ?string $whatsapp): ?string
    {
        if ($whatsapp !== null && trim($whatsapp) !== '') {
            $byWhatsapp = User::query()
                ->where('is_admin', false)
                ->where('whatsapp_number', trim($whatsapp))
                ->pluck('id');

            if ($byWhatsapp->count() === 1) {
                return (string) $byWhatsapp->first();
            }
        }

        $byName = User::query()
            ->where('is_admin', false)
            ->whereLike('name', trim($name))
            ->pluck('id');

        return $byName->count() === 1 ? (string) $byName->first() : null;
    }

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

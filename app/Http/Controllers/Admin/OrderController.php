<?php

namespace App\Http\Controllers\Admin;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\Warehouse;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\OrderProcessRequest;
use App\Http\Requests\Admin\OrderStatusRequest;
use App\Http\Requests\Admin\OrderStoreRequest;
use App\Models\Book;
use App\Models\Order;
use App\Models\TierDiscount;
use App\Models\User;
use App\Services\InventoryService;
use App\Services\OrderStatusService;
use App\Services\PricingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\UniqueConstraintViolationException;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;

class OrderController extends Controller
{
    public function __construct(
        private readonly PricingService $pricing,
        private readonly OrderStatusService $statusService,
        private readonly InventoryService $inventoryService,
    ) {
    }

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
            ->orderByDesc('created_at')
            ->paginate(10)
            ->withQueryString();

        return Inertia::render('admin/orders/Index', [
            'orders' => $orders,
            'filters' => $request->only(['search', 'status', 'dropship']),
            'statusOptions' => OrderStatus::options(),
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
            'paymentOptions' => PaymentMethod::options(),
            'couriers' => config('shipping.couriers'),
            'tierDiscounts' => TierDiscount::query()
                ->orderBy('tier')
                ->orderBy('min_qty')
                ->get(['tier', 'min_qty', 'max_qty', 'discount_percent']),
        ]);
    }

    /**
     * Search active books for the manual-order picker.
     */
    public function bookOptions(Request $request): JsonResponse
    {
        $search = trim($request->string('search')->toString());

        return response()->json(
            Book::query()
                ->where('aktif', true)
                ->when($search !== '', function ($query) use ($search): void {
                    $query->where(function ($query) use ($search): void {
                        $query->whereLike('judul', "%{$search}%")
                            ->orWhereLike('kode_sku', "%{$search}%");
                    });
                })
                ->orderBy('judul')
                ->limit(50)
                ->get(['id', 'judul', 'kode_sku', 'harga', 'stok']),
        );
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
     * Simpan order manual (ORD-03, ORD-08).
     */
    public function store(OrderStoreRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $isDropship = $request->boolean('is_dropship');
        $order = null;

        for ($attempt = 0; $attempt < 3; $attempt++) {
            try {
                $order = DB::transaction(function () use ($data, $isDropship): Order {
                    $order = new Order([
                        'no_order' => $this->generateOrderNumber(),
                        'user_id' => $data['user_id'] ?? null,
                        'nama_pembeli' => $data['nama_pembeli'],
                        'no_hp' => $data['whatsapp_pembeli'] ?? null,
                        'alamat' => $data['alamat'] ?? null,
                        'metode_bayar' => $data['metode_bayar'],
                        'total' => 0,
                        'ekspedisi' => $data['ekspedisi'] ?? null,
                        'ongkir_estimasi' => $data['ongkir_estimasi'] ?? null,
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
            'couriers' => config('shipping.couriers'),
            'warehouseOptions' => [
                Warehouse::Malang->value => Warehouse::Malang->label(),
                Warehouse::Sidoarjo->value => Warehouse::Sidoarjo->label(),
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
                    ->with('items.book', 'user')
                    ->findOrFail((int) $order->getKey());

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

                foreach ($lockedOrder->items as $item) {
                    $this->inventoryService->assertSufficientStock(
                        $item->book,
                        $lockedOrder->warehouse_origin,
                        $item->qty,
                    );
                }

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
     * Nomor order unik: ORD-YYYYMMDD-XXXX (urutan per hari).
     */
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

<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ActivityAction;
use App\Enums\FlowType;
use App\Enums\MovementType;
use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Models\CashFlow;
use App\Models\Order;
use App\Models\SalesReturn;
use App\Models\SalesReturnItem;
use App\Models\Setting;
use App\Models\Warehouse;
use App\Services\InventoryService;
use App\Support\ActivityLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;

/**
 * Retur penjualan — barang dikembalikan pembeli. Stok kembali ke gudang
 * (normal/defect) + refund dicatat di cash_flows.
 */
class SalesReturnController extends Controller
{
    public function __construct(private readonly InventoryService $inventory) {}

    /**
     * Daftar retur penjualan.
     */
    public function index(Request $request): Response
    {
        $returns = SalesReturn::query()
            ->with(['order:id,no_order,nama_pembeli', 'user:id,name', 'items:id,sales_return_id,qty,price_refund'])
            ->when($request->filled('search'), function ($query) use ($request): void {
                $search = $request->string('search')->toString();

                $query->whereHas('order', fn ($q) => $q->where('no_order', 'ilike', "%{$search}%"));
            })
            ->orderByDesc('return_date')
            ->orderByDesc('id')
            ->paginate(10)
            ->withQueryString();

        return Inertia::render('admin/sales-returns/Index', [
            'returns' => $returns,
            'filters' => $request->only(['search']),
        ]);
    }

    /**
     * Opsi order untuk form retur — order selesai yang masih punya sisa qty
     * yang bisa diretur.
     */
    public function orderOptions(Request $request): JsonResponse
    {
        $search = trim($request->string('search')->toString());

        return response()->json(
            Order::query()
                ->where('status', OrderStatus::Selesai->value)
                ->whereHas('items', function ($query): void {
                    $query->whereRaw(
                        'order_items.qty > COALESCE((SELECT SUM(qty) FROM sales_return_items WHERE sales_return_items.order_item_id = order_items.id), 0)',
                    );
                })
                ->when($search !== '', fn ($q) => $q->where('no_order', 'ilike', "%{$search}%"))
                ->orderByDesc('id')
                ->limit(20)
                ->get(['id', 'no_order', 'nama_pembeli', 'created_at'])
                ->map(fn (Order $order): array => [
                    'id' => $order->id,
                    'no_order' => $order->no_order,
                    'nama_pembeli' => $order->nama_pembeli,
                    'created_at' => $order->created_at->format('d/m/Y'),
                ]),
        );
    }

    /**
     * Detail order untuk form retur — item + qty yang bisa diretur.
     * Hanya order selesai yang bisa diretur.
     */
    public function orderDetail(Order $order): JsonResponse
    {
        if ($order->status !== OrderStatus::Selesai) {
            return response()->json(
                ['message' => 'Hanya order selesai yang dapat diretur.'],
                422,
            );
        }

        $alreadyReturned = SalesReturnItem::query()
            ->whereIn('order_item_id', $order->items()->pluck('id'))
            ->selectRaw('order_item_id, SUM(qty) as qty')
            ->groupBy('order_item_id')
            ->pluck('qty', 'order_item_id');

        $items = $order->items
            ->map(function ($item) use ($alreadyReturned): array {
                $ordered = (int) $item->qty;
                $returned = (int) ($alreadyReturned[$item->id] ?? 0);

                return [
                    'id' => $item->id,
                    'book_id' => $item->book_id,
                    'book_edition_id' => $item->book_edition_id,
                    'judul' => $item->judul_snapshot,
                    'edition_snapshot' => $item->edition_snapshot,
                    'ordered_qty' => $ordered,
                    'returnable_qty' => max(0, $ordered - $returned),
                    'price_refund' => (int) $item->price_final,
                ];
            })
            ->filter(fn (array $item): bool => $item['returnable_qty'] > 0)
            ->values()
            ->all();

        return response()->json([
            'id' => $order->id,
            'no_order' => $order->no_order,
            'nama_pembeli' => $order->nama_pembeli,
            'warehouse_origin' => $order->warehouse_origin ?? 'malang',
            'items' => $items,
        ]);
    }

    /**
     * Nota retur penjualan — halaman print (A4), tanpa layout admin.
     */
    public function invoice(SalesReturn $salesReturn): Response
    {
        $salesReturn->load([
            'order:id,no_order,nama_pembeli',
            'items:id,sales_return_id,order_item_id,qty,price_refund,condition,reason',
            'items.orderItem:id,judul_snapshot,edition_snapshot',
        ]);

        return Inertia::render('print/sales-returns/Invoice', [
            'retur' => $salesReturn,
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
     * Catat retur penjualan: stok kembali + refund.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'order_id' => ['required', 'string', 'exists:orders,id'],
            'return_date' => ['required', 'date'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.order_item_id' => ['required', 'string', 'distinct', 'exists:order_items,id'],
            'items.*.qty' => ['required', 'integer', 'min:1'],
            'items.*.condition' => ['required', 'string', 'in:baik,rusak'],
            'items.*.reason' => ['nullable', 'string', 'max:500'],
        ]);

        // Lock baris order supaya dua request retur paralel tidak bisa
        // melebihi sisa qty yang sama (race condition).
        $order = Order::query()
            ->lockForUpdate()
            ->with('items')
            ->findOrFail($validated['order_id']);

        if ($order->status !== OrderStatus::Selesai) {
            return back()->withErrors(['order_id' => 'Hanya order selesai yang dapat diretur.']);
        }

        $adminId = $request->user()->id;

        try {
            $salesReturn = DB::transaction(function () use ($order, $validated, $adminId): SalesReturn {
                $originWarehouse = Warehouse::query()
                    ->sellable()
                    ->where('kode', $order->warehouse_origin ?? 'malang')
                    ->first() ?? Warehouse::default();

                $defectWarehouse = Warehouse::defect();
                $orderItems = $order->items->keyBy('id');

                // Cek qty sisa per order_item (sudah pernah diretur?).
                $alreadyReturned = SalesReturnItem::query()
                    ->whereIn('order_item_id', $orderItems->pluck('id'))
                    ->selectRaw('order_item_id, SUM(qty) as qty')
                    ->groupBy('order_item_id')
                    ->pluck('qty', 'order_item_id');

                $totalRefund = 0;
                $rows = [];

                // Qty retur terakumulasi per item selama loop — selain validasi
                // `distinct`, ini mencegah dua baris item yang sama dalam satu
                // request melebihi sisa yang bisa diretur.
                $returnedByItem = $alreadyReturned->toArray();

                foreach ($validated['items'] as $row) {
                    $item = $orderItems->get((string) $row['order_item_id']);

                    if ($item === null) {
                        throw new RuntimeException('Item order tidak ditemukan.');
                    }

                    $returned = (int) ($returnedByItem[$item->id] ?? 0);
                    $returnable = max(0, (int) $item->qty - $returned);

                    if ((int) $row['qty'] > $returnable) {
                        throw new RuntimeException(
                            "Qty retur {$item->judul_snapshot} melebihi sisa yang bisa diretur (sisa {$returnable}).",
                        );
                    }

                    $returnedByItem[$item->id] = $returned + (int) $row['qty'];

                    $priceRefund = (int) $item->price_final;

                    $rows[] = [
                        'order_item_id' => $item->id,
                        'book_id' => $item->book_id,
                        'book_edition_id' => $item->book_edition_id,
                        'qty' => (int) $row['qty'],
                        'price_refund' => $priceRefund,
                        'condition' => $row['condition'],
                        'reason' => $row['reason'] ?? null,
                    ];

                    $totalRefund += $priceRefund * (int) $row['qty'];
                }

                $salesReturn = SalesReturn::create([
                    'order_id' => $order->id,
                    'return_date' => $validated['return_date'],
                    'total_refund' => $totalRefund,
                    'notes' => $validated['notes'] ?? null,
                    'user_id' => $adminId,
                ]);

                foreach ($rows as $row) {
                    SalesReturnItem::create($row + ['sales_return_id' => $salesReturn->id]);

                    $book = $orderItems->get($row['order_item_id'])->book;
                    $edition = $row['book_edition_id'] !== null
                        ? $book->editions()->find($row['book_edition_id'])
                        : null;

                    // Stok kembali ke gudang asal order.
                    $this->inventory->move(
                        book: $book,
                        type: MovementType::In,
                        qty: $row['qty'],
                        to: $originWarehouse,
                        reference: "RET-{$salesReturn->id}",
                        userId: $adminId,
                        notes: "Retur penjualan {$order->no_order}",
                        edition: $edition,
                    );

                    // Barang rusak → pindahkan ke gudang defect.
                    if ($row['condition'] === 'rusak') {
                        $this->inventory->move(
                            book: $book,
                            type: MovementType::Defect,
                            qty: $row['qty'],
                            from: $originWarehouse,
                            to: $defectWarehouse,
                            reference: "RET-{$salesReturn->id}",
                            userId: $adminId,
                            notes: "Retur rusak {$order->no_order}",
                            edition: $edition,
                        );
                    }
                }

                // Refund dicatat di arus kas.
                CashFlow::create([
                    'order_id' => $order->id,
                    'entry_date' => $validated['return_date'],
                    'flow_type' => FlowType::Refund->value,
                    'amount' => $totalRefund,
                    'description' => "Retur penjualan {$order->no_order}",
                ]);

                return $salesReturn;
            });
        } catch (RuntimeException $exception) {
            return back()->withErrors(['items' => $exception->getMessage()]);
        }

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Retur penjualan tercatat (refund Rp '.number_format($salesReturn->total_refund, 0, ',', '.').').',
        ]);

        ActivityLogger::log(
            ActivityAction::SalesReturnCreate,
            'Retur penjualan order '.($salesReturn->order->no_order ?? '-').' (refund Rp '.number_format($salesReturn->total_refund, 0, ',', '.').')',
            $salesReturn,
        );

        return back();
    }
}

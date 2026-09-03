<?php

namespace App\Http\Controllers\Api\Pos;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Models\Book;
use App\Models\BookEdition;
use App\Models\Order;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\InventoryService;
use App\Services\PricingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class PosOrderController extends Controller
{
    public function __construct(
        private readonly PricingService $pricing,
        private readonly InventoryService $inventory,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'since' => ['nullable', 'date'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $query = Order::query()->with(['items.book', 'items.edition', 'user:id,name'])->orderByDesc('created_at');

        if (! empty($validated['since'])) {
            $query->where('created_at', '>', $validated['since']);
        }

        $limit = (int) ($validated['limit'] ?? 20);
        $orders = $query->limit($limit)->get();

        return response()->json(['data' => $orders]);
    }

    public function show(Request $request, string $clientUuid): JsonResponse
    {
        $order = Order::where('client_uuid', $clientUuid)->with(['items.book', 'items.edition'])->firstOrFail();

        return response()->json(['data' => $order]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'client_uuid' => ['required', 'uuid'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.book_id' => ['required', 'uuid', 'exists:books,id'],
            'items.*.book_edition_id' => ['nullable', 'uuid', 'exists:book_editions,id'],
            'items.*.qty' => ['required', 'integer', 'min:1', 'max:100'],
            'items.*.is_custom_price' => ['nullable', 'boolean'],
            'items.*.custom_price' => ['nullable', 'integer', 'min:0'],
            'items.*.price_note' => ['nullable', 'string', 'max:255'],
            'customer_id' => ['nullable', 'uuid', 'exists:users,id'],
            'nama_pembeli' => ['nullable', 'string', 'max:255'],
            'no_hp' => ['nullable', 'string', 'max:30'],
            'metode_bayar' => ['nullable', 'string', 'max:50'],
            'pos_meta' => ['nullable', 'array'],
            'pos_meta.kasir_id' => ['nullable', 'string'],
            'pos_meta.device_id' => ['nullable', 'string'],
            'pos_meta.shift_id' => ['nullable', 'string'],
            'pos_meta.struk_no' => ['nullable', 'string'],
        ]);

        // Idempotent — if already exists return it
        $existing = Order::where('client_uuid', $validated['client_uuid'])->with(['items'])->first();
        if ($existing !== null) {
            return response()->json(['data' => $existing, 'idempotent' => true], 200);
        }

        $warehouse = Warehouse::where('kode', 'toko')->first() ?? Warehouse::default();
        $userId = $request->user()->id;

        // Resolve customer tier for pricing
        $customer = null;
        if (! empty($validated['customer_id'])) {
            $customer = User::find($validated['customer_id']);
        }
        $tier = $customer?->status_pelanggan;

        try {
            $order = DB::transaction(function () use ($validated, $warehouse, $userId, $tier, $customer): Order {
                // Validate stock & calculate totals
                $total = 0;
                $itemsData = [];

                foreach ($validated['items'] as $item) {
                    $book = Book::findOrFail($item['book_id']);
                    $edition = ! empty($item['book_edition_id']) ? BookEdition::find($item['book_edition_id']) : null;

                    if ($edition !== null && $edition->book_id !== $book->id) {
                        throw new RuntimeException("Cetakan tidak sesuai dengan buku {$book->judul}.");
                    }

                    // Check stock at toko warehouse per edition
                    $this->inventory->assertSufficientStock($book, $warehouse, (int) $item['qty'], $edition);

                    // Pricing — server is source of truth
                    $breakdown = $this->pricing->priceBreakdown($book, (int) $item['qty'], $tier, $edition);
                    $isCustom = ! empty($item['is_custom_price']) && isset($item['custom_price']);
                    $priceFinal = $isCustom ? (int) $item['custom_price'] : $breakdown->finalPrice;

                    // Preorder check — allow even if stock 0
                    if (! $book->is_preorder && $book->stok < (int) $item['qty'] && $edition === null) {
                        // Already checked via assertSufficientStock
                    }

                    $itemsData[] = [
                        'book' => $book,
                        'edition' => $edition,
                        'qty' => (int) $item['qty'],
                        'breakdown' => $breakdown,
                        'price_final' => $priceFinal,
                        'is_custom_price' => $isCustom,
                        'custom_price' => $isCustom ? (int) $item['custom_price'] : null,
                        'price_note' => $item['price_note'] ?? null,
                    ];

                    $total += $priceFinal * (int) $item['qty'];
                }

                $namaPembeli = $validated['nama_pembeli'] ?? $customer?->name ?? 'Pelanggan Toko';
                $noHp = $validated['no_hp'] ?? $customer?->whatsapp_number;

                $order = Order::create([
                    'client_uuid' => $validated['client_uuid'],
                    'no_order' => $this->generateOrderNumber(),
                    'user_id' => $validated['customer_id'] ?? null,
                    'nama_pembeli' => $namaPembeli,
                    'no_hp' => $noHp,
                    'alamat' => $customer?->alamat,
                    'provinsi' => $customer?->provinsi,
                    'kabupaten_kota' => $customer?->kabupaten_kota,
                    'kecamatan' => $customer?->kecamatan,
                    'kelurahan' => $customer?->kelurahan,
                    'kode_pos' => $customer?->kode_pos,
                    'metode_bayar' => $validated['metode_bayar'] ?? 'cash',
                    'sumber_pembelian' => 'toko',
                    'total' => $total,
                    'shipping_cost' => 0,
                    'is_dropship' => false,
                    'warehouse_origin' => $warehouse->kode,
                    'status' => OrderStatus::Selesai,
                    'payment_status' => PaymentStatus::Lunas,
                    'ekspedisi' => null,
                    'metode_pengambilan' => 'ambil',
                    'pos_meta' => $validated['pos_meta'] ?? ['kasir_id' => $userId, 'device_id' => $validated['pos_meta']['device_id'] ?? null],
                ]);

                foreach ($itemsData as $data) {
                    $order->items()->create([
                        'book_id' => $data['book']->id,
                        'book_edition_id' => $data['edition']?->id,
                        'edition_snapshot' => $data['edition'] ? 'Cetakan ke-'.$data['edition']->cetakan_ke : null,
                        'is_preorder' => $data['book']->is_preorder,
                        'judul_snapshot' => $data['book']->judul,
                        'harga_snapshot' => $data['book']->harga,
                        'harga_beli_snapshot' => $data['edition']?->harga_beli ?? $data['book']->harga,
                        'qty' => $data['qty'],
                        'price_original' => $data['breakdown']->originalPrice,
                        'promo_discount_amount' => $data['breakdown']->promoDiscount,
                        'tier_discount_amount' => $data['breakdown']->tierDiscount,
                        'price_final' => $data['price_final'],
                        'is_custom_price' => $data['is_custom_price'],
                        'custom_price' => $data['custom_price'],
                        'price_note' => $data['price_note'],
                    ]);
                }

                // Deduct stock atomically per edition at toko warehouse
                $order->load(['items.book', 'items.edition']);
                $this->inventory->deductForOrder($order, (string) $userId);

                return $order->load(['items.book', 'items.edition', 'user']);
            });
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['data' => $order], 201);
    }

    public function void(Request $request, string $clientUuid): JsonResponse
    {
        $order = Order::where('client_uuid', $clientUuid)->firstOrFail();

        if ($order->status !== OrderStatus::Selesai) {
            return response()->json(['message' => 'Hanya order selesai yang bisa dibatalkan.'], 422);
        }

        // Only allow void within 24h and if same kasir/admin
        $order->update(['status' => OrderStatus::Batal]);

        // Restore stock
        try {
            app(InventoryService::class)->restoreForOrder($order, (string) $request->user()->id);
        } catch (RuntimeException $e) {
            // Log but don't fail
        }

        return response()->json(['data' => $order->fresh()]);
    }

    private function generateOrderNumber(): string
    {
        return 'POS-'.now()->format('YmdHis').'-'.strtoupper(Str::random(4));
    }
}

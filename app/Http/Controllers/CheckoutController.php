<?php

namespace App\Http\Controllers;

use App\Enums\FulfillmentMethod;
use App\Enums\OrderStatus;
use App\Enums\PromotionType;
use App\Enums\VoucherScope;
use App\Http\Requests\Storefront\CheckoutRequest;
use App\Models\BankAccount;
use App\Models\Book;
use App\Models\BookEdition;
use App\Models\Order;
use App\Models\Promotion;
use App\Models\Voucher;
use App\Services\PricingService;
use App\Services\RajaOngkirCostService;
use App\Services\VoucherService;
use App\Support\StoreSettings;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;

/**
 * Checkout storefront — keranjang berbasis session + pembuatan order.
 */
class CheckoutController extends Controller
{
    public function __construct(
        private readonly PricingService $pricing,
        private readonly RajaOngkirCostService $shippingCost,
        private readonly VoucherService $vouchers,
    ) {}

    /**
     * Halaman checkout: item keranjang dikelompokkan per paket bundle,
     * item di luar bundle masuk grup terpisah "Item Lainnya".
     */
    public function index(): Response
    {
        $groups = $this->cartGroups();
        $user = auth()->user();

        // Pilihan grup dicentang — dikelola di session (default semua dicentang).
        $availableKeys = array_column($groups, 'key');
        $saved = session('checkout_selected_groups');

        if ($saved === null) {
            $selectedGroups = $availableKeys;
        } else {
            $selectedGroups = array_values(array_intersect((array) $saved, $availableKeys));
        }

        session(['checkout_selected_groups' => $selectedGroups]);

        // Voucher yang bisa dipakai: aktif & dalam periode berlaku, dengan
        // kuota (global & per user). Minimal belanja difilter di client
        // (subtotal berubah saat grup dicentang) — divalidasi ulang di store.
        $selectedSubtotal = 0;

        foreach ($groups as $group) {
            if (in_array($group['key'], $selectedGroups, true)) {
                $selectedSubtotal += (int) $group['total'];
            }
        }

        $vouchers = auth()->user() !== null
            ? $this->vouchers->availableFor(auth()->user(), $selectedSubtotal)
            : collect();

        return Inertia::render('storefront/Checkout', [
            'groups' => $groups,
            'selectedGroups' => $selectedGroups,
            'vouchers' => $vouchers,
            'paymentOptions' => StoreSettings::enabledPaymentMethods(),
            'bankAccounts' => BankAccount::query()
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->get(['id', 'bank_name', 'account_number', 'account_holder']),
            'user' => $user ? [
                'name' => $user->name,
                'whatsapp_number' => $user->whatsapp_number,
                'email' => $user->email,
                'alamat' => $user->alamat,
                'provinsi' => $user->provinsi,
                'kabupaten_kota' => $user->kabupaten_kota,
                'kecamatan' => $user->kecamatan,
                'kelurahan' => $user->kelurahan,
                'village_code' => $user->village_code,
                'kode_pos' => $user->kode_pos,
            ] : null,
        ]);
    }

    /**
     * Tambah buku ke keranjang (session). Cetakan bisa dipilih dari detail buku.
     */
    public function add(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'book_id' => ['required', 'exists:books,id'],
            'book_edition_id' => ['nullable', 'string', 'exists:book_editions,id'],
            'qty' => ['required', 'integer', 'min:1'],
        ]);

        $cart = $this->cart();
        $bookId = (string) $validated['book_id'];
        $book = Book::findOrFail($bookId);
        $editionId = ! empty($validated['book_edition_id']) ? (string) $validated['book_edition_id'] : null;

        if ($editionId !== null) {
            $edition = BookEdition::query()->findOrFail($editionId);

            if ($edition->book_id !== $book->id) {
                return back()->withErrors(['book_edition_id' => 'Cetakan tidak sesuai dengan buku.']);
            }
        }

        // Buku pre-order (new coming) boleh dipesan walau stok belum ada.
        $isPreorder = $book->is_preorder;
        $maxPreorderQty = (int) config('preorder.max_qty', 99);

        if (! $isPreorder && $book->stok <= 0) {
            return back()->withErrors(['qty' => "{$book->judul} sedang stok habis."]);
        }

        // Qty cap mengikuti stok cetakan terpilih (bukan stok seluruh buku).
        $edition = null;

        if ($editionId !== null) {
            $edition = BookEdition::query()->withSum(['stocks as sellable_total' => fn ($q) => $q->whereHas('warehouse', fn ($w) => $w->sellable())], 'qty')->find($editionId);
        }

        $capStock = $edition?->sellable_total ?? $book->stok;

        if (! $isPreorder && $capStock <= 0) {
            return back()->withErrors(['qty' => "{$book->judul} (cetakan terpilih) sedang stok habis."]);
        }

        // Satu buku boleh punya beberapa cetakan di keranjang: kunci = bookId:editionId.
        $key = $editionId !== null ? $bookId.':'.$editionId : (string) $bookId;
        $current = $this->cartEntry($cart, $bookId, $editionId);
        $current['qty'] = min(
            $current['qty'] + (int) $validated['qty'],
            $isPreorder ? $maxPreorderQty : (int) $capStock,
        );
        $current['edition_id'] = $editionId;
        $cart[$key] = $current;
        session(['cart' => $cart]);
        // Keranjang berubah — reset pilihan grup ke default.
        session()->forget('checkout_selected_groups');

        $label = $book?->judul ?? 'Buku';
        $label .= $editionId !== null ? " (Cetakan ke-{$edition->cetakan_ke})" : '';

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => "{$label} ditambahkan ke keranjang.",
        ]);

        return redirect()->route('checkout.index');
    }

    /**
     * Tambah beberapa buku sekaligus ke keranjang (qty 1 masing-masing).
     */
    public function addBulk(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'book_ids' => ['required', 'array', 'min:1'],
            'book_ids.*' => ['required', 'string', 'exists:books,id'],
        ]);

        $books = Book::whereKey($validated['book_ids'])->get()->keyBy('id');
        $cart = $this->cart();
        $added = 0;

        foreach ($validated['book_ids'] as $bookId) {
            $book = $books->get((string) $bookId);
            if ($book === null || (! $book->is_preorder && $book->stok <= 0)) {
                continue;
            }
            $key = (string) $bookId;
            $cart[$key] = $this->cartEntry($cart, (string) $bookId, null);
            $cart[$key]['qty'] = min(
                ($cart[$key]['qty'] ?? 0) + 1,
                $book->is_preorder ? (int) config('preorder.max_qty', 99) : $book->stok,
            );
            $added++;
        }

        session(['cart' => $cart]);
        // Keranjang berubah — reset pilihan grup ke default.
        session()->forget('checkout_selected_groups');

        return redirect()->route('checkout.index');
    }

    /**
     * Hapus item dari keranjang (semua cetakan untuk buku tsb).
     */
    public function remove(string $bookId): RedirectResponse
    {
        $cart = $this->cart();
        $book = Book::find($bookId);
        $label = $book?->judul ?? 'Item';

        // Hapus semua entri buku ini (bisa ada beberapa cetakan).
        $this->removeBookFromCart($cart, $bookId);

        session(['cart' => $cart]);
        // Keranjang berubah — reset pilihan grup ke default.
        session()->forget('checkout_selected_groups');

        Inertia::flash('toast', [
            'type' => 'info',
            'message' => "{$label} dihapus dari keranjang.",
        ]);

        return back();
    }

    /**
     * Toggle centang satu grup — dipanggil lewat AJAX dari halaman checkout.
     */
    public function toggleGroup(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'group_key' => ['required', 'string'],
            'checked' => ['required', 'boolean'],
        ]);

        $availableKeys = array_column($this->cartGroups(), 'key');

        if (! in_array($validated['group_key'], $availableKeys, true)) {
            return back()->withErrors(['group_key' => 'Grup tidak ditemukan di keranjang.']);
        }

        $selected = (array) session('checkout_selected_groups', $availableKeys);

        if ($validated['checked']) {
            $selected = array_values(array_unique(array_merge($selected, [$validated['group_key']])));
        } else {
            $selected = array_values(array_diff($selected, [$validated['group_key']]));
        }

        session(['checkout_selected_groups' => $selected]);

        return back();
    }

    /**
     * Hapus satu grup utuh dari keranjang (semua item paket bundle / item lainnya).
     */
    public function removeGroup(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'group_key' => ['required', 'string'],
        ]);

        $group = collect($this->cartGroups())->firstWhere('key', $validated['group_key']);

        if ($group === null) {
            return back()->withErrors(['group_key' => 'Grup tidak ditemukan di keranjang.']);
        }

        $cart = $this->cart();

        foreach ($group['items'] as $item) {
            $this->removeBookFromCart($cart, $item['book']->id);
        }

        session(['cart' => $cart]);
        // Keranjang berubah — reset pilihan grup ke default.
        session()->forget('checkout_selected_groups');

        Inertia::flash('toast', [
            'type' => 'info',
            'message' => "{$group['name']} dihapus dari keranjang.",
        ]);

        return back();
    }

    /**
     * Ubah qty item di keranjang.
     */
    public function updateQty(Request $request, string $bookId): RedirectResponse
    {
        $qty = (int) $request->input('qty');

        if ($qty < 1) {
            return $this->remove($bookId);
        }

        $cart = $this->cart();
        $book = Book::find($bookId);

        // Update semua entri buku ini (bisa beberapa cetakan) dengan qty sama.
        $capQty = $book?->is_preorder ? (int) config('preorder.max_qty', 99) : ($book?->stok ?? $qty);

        foreach (array_keys($cart) as $key) {
            if ($this->keyBookId($key) === $bookId) {
                $cart[$key]['qty'] = min($qty, $capQty);
            }
        }

        session(['cart' => $cart]);

        return back();
    }

    /**
     * Ganti cetakan item di keranjang (semua entri buku ini).
     */
    public function updateEdition(Request $request, string $bookId): RedirectResponse
    {
        $validated = $request->validate([
            'book_edition_id' => ['nullable', 'string', 'exists:book_editions,id'],
        ]);

        $editionId = ! empty($validated['book_edition_id']) ? (string) $validated['book_edition_id'] : null;

        if ($editionId !== null) {
            $edition = BookEdition::query()->findOrFail($editionId);

            if ($edition->book_id !== $bookId) {
                return back()->withErrors(['edition' => 'Cetakan tidak sesuai dengan buku.']);
            }
        }

        $cart = $this->cart();
        $book = Book::find($bookId);

        $capStock = $editionId !== null
            ? BookEdition::query()
                ->whereKey($editionId)
                ->withSum(['stocks as sellable_total' => fn ($q) => $q->whereHas('warehouse', fn ($w) => $w->sellable())], 'qty')
                ->first()?->sellable_total
            : $book?->stok;

        // Buku pre-order: batas qty tidak mengikuti stok.
        $capStock = $book?->is_preorder ? (int) config('preorder.max_qty', 99) : $capStock;

        foreach (array_keys($cart) as $key) {
            if ($this->keyBookId($key) === $bookId) {
                $cart[$key]['edition_id'] = $editionId;
                $cart[$key]['qty'] = min((int) $cart[$key]['qty'], max((int) $capStock, 1));
            }
        }

        session(['cart' => $cart]);

        return back();
    }

    public function store(CheckoutRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $cart = $this->cart();

        if ($cart === []) {
            return back()->withErrors(['items' => 'Keranjang belanja kosong.']);
        }

        // Hanya proses grup yang dipilih.
        // Browser: pilihan dari session (dikelola toggleGroup/index).
        // Tes lama: field form selected_groups[] → tetap dihormati.
        $selectedGroups = $request->has('selected_groups')
            ? array_values(array_filter((array) $request->input('selected_groups', [])))
            : session('checkout_selected_groups');

        if ($selectedGroups !== null) {
            if ($selectedGroups === []) {
                return back()->withErrors(['items' => 'Pilih minimal satu kelompok item untuk diproses.']);
            }

            $cart = $this->filterCartBySelectedGroups($cart, $selectedGroups);

            if ($cart === []) {
                return back()->withErrors(['items' => 'Pilih minimal satu kelompok item untuk diproses.']);
            }
        }

        // Ongkir dihitung ulang di server via RajaOngkir (jangan percaya
        // nilai dari client). Ambil sendiri → tanpa ongkir.
        $shippingCost = 0;
        $shippingEstimation = null;
        $courierCode = $data['ekspedisi'] ?? null;
        $fulfillment = $data['metode_pengambilan'] ?? FulfillmentMethod::Kirim->value;

        if ($fulfillment === FulfillmentMethod::Ambil->value) {
            $courierCode = null;
        }

        if ($courierCode !== null && $courierCode !== '') {
            $kodePos = $data['kode_pos'] ?? null;

            if (empty($kodePos)) {
                return back()->withErrors(['ekspedisi' => 'Pilih alamat lengkap (kode pos) untuk menghitung ongkir.']);
            }

            try {
                $items = $this->cartToBiteshipItems($cart);
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
                    return back()->withErrors(['ekspedisi' => 'Ekspedisi tidak valid — pilih ulang dari daftar ongkir.']);
                }

                $shippingCost = (int) $matched['price'];
                $shippingEstimation = $matched['estimation'] ?? null;
            } catch (RuntimeException $e) {
                return back()->withErrors(['ekspedisi' => $e->getMessage()]);
            }
        }

        // Fast-fail check (tanpa lock) untuk error yang ramah.
        $stockError = $this->validateCartStock($cart);

        if ($stockError !== null) {
            return back()->withErrors(['items' => $stockError]);
        }

        $order = null;
        $voucherId = $request->input('voucher_id');

        for ($attempt = 0; $attempt < 3; $attempt++) {
            try {
                $order = DB::transaction(function () use ($data, $cart, $courierCode, $shippingCost, $shippingEstimation, $fulfillment, $voucherId): Order {
                    // Lock baris buku + cetakan agar tidak oversell saat 2 checkout bersamaan.
                    $bookIds = $this->cartBookIds($cart);
                    $lockedBooks = Book::query()
                        ->whereKey($bookIds)
                        ->lockForUpdate()
                        ->get()
                        ->keyBy('id');

                    $editionIds = collect($cart)->pluck('edition_id')->filter()->unique()->values()->all();
                    $lockedEditions = BookEdition::query()
                        ->whereKey($editionIds)
                        ->withSum(['stocks as sellable_total' => fn ($q) => $q->whereHas('warehouse', fn ($w) => $w->sellable())], 'qty')
                        ->lockForUpdate()
                        ->get()
                        ->keyBy('id');

                    foreach ($cart as $key => $entry) {
                        $bookId = $this->keyBookId($key);
                        $qty = (int) $entry['qty'];
                        $book = $lockedBooks->get($bookId);

                        if ($book === null || ! $book->aktif) {
                            throw new RuntimeException('Buku di keranjang sudah tidak tersedia.');
                        }

                        // Buku pre-order menunggu stok — batas qty memakai config.
                        if ($book->is_preorder) {
                            if ($qty > (int) config('preorder.max_qty', 99)) {
                                throw new RuntimeException("Qty pre-order {$book->judul} melebihi batas.");
                            }

                            continue;
                        }

                        $editionId = $entry['edition_id'] ?? null;
                        $available = $editionId !== null
                            ? (int) ($lockedEditions->get($editionId)?->sellable_total ?? 0)
                            : (int) $book->stok;

                        if ($available < $qty) {
                            $label = $editionId !== null ? "{$book->judul} (cetakan terpilih)" : $book->judul;

                            throw new RuntimeException("Stok {$label} tidak mencukupi (tersisa {$available}).");
                        }
                    }

                    // Voucher (opsional) — dipilih customer di halaman checkout.
                    // Lock baris voucher agar kuota tidak overshoot saat 2
                    // checkout bersamaan.
                    $voucher = null;

                    if ($voucherId !== null) {
                        $voucher = Voucher::query()->whereKey($voucherId)->lockForUpdate()->first();

                        if ($voucher === null) {
                            throw new RuntimeException('Voucher tidak valid.');
                        }
                    }

                    $order = new Order([
                        'no_order' => $this->generateOrderNumber(),
                        'user_id' => auth()->id(),
                        'nama_pembeli' => $data['nama_pembeli'],
                        'no_hp' => $data['whatsapp_pembeli'] ?? null,
                        'email_pembeli' => $data['email_pembeli'] ?? null,
                        'alamat' => $data['alamat'] ?? null,
                        'provinsi' => $data['provinsi'] ?? null,
                        'kabupaten_kota' => $data['kabupaten_kota'] ?? null,
                        'kecamatan' => $data['kecamatan'] ?? null,
                        'kelurahan' => $data['kelurahan'] ?? null,
                        'kode_pos' => $data['kode_pos'] ?? null,
                        'metode_bayar' => $data['metode_bayar'],
                        'sumber_pembelian' => 'website',
                        'voucher_id' => $voucher?->getKey(),
                        'total' => 0,
                        'ekspedisi' => $courierCode,
                        'courier_service_code' => $data['courier_service_code'] ?? null,
                        'shipping_cost' => $shippingCost,
                        'ongkir_estimasi' => $shippingEstimation,
                        'is_dropship' => false,
                        'metode_pengambilan' => $fulfillment,
                        'status' => OrderStatus::MenungguKonfirmasi,
                    ]);

                    $this->pricing->storeOrderWithItems($order, $this->cartToItems($cart));

                    if ($voucher !== null) {
                        // Validasi ulang di server dengan subtotal nyata order
                        // (sudah termasuk promo/tier/bundle, belum dipotong voucher).
                        $this->vouchers->assertUsable($voucher, $order->itemsTotal(), $order->user);

                        // Voucher ongkir hanya berlaku bila order dikirim & ada ongkos kirim.
                        if ($voucher->discount_scope === VoucherScope::Ongkir && $order->shipping_cost <= 0) {
                            throw new RuntimeException('Voucher ongkir hanya berlaku untuk pengiriman berbayar.');
                        }

                        $this->vouchers->recordUsage($order, $voucher);
                    }

                    return $order->fresh();
                });

                break;
            } catch (UniqueConstraintViolationException $exception) {
                if ($attempt === 2) {
                    throw $exception;
                }
            } catch (RuntimeException $exception) {
                // Stok berubah antara fast-fail & transaksi (race) — kembalikan ke form.
                return back()->withErrors(['items' => $exception->getMessage()]);
            }
        }

        if ($order === null) {
            throw new RuntimeException('Order gagal dibuat.');
        }

        session()->forget('cart');
        session()->forget('checkout_selected_groups');

        // Simpan no_order ke session agar halaman sukses hanya bisa diakses pembuatnya.
        session()->push('checkout_orders', $order->no_order);

        return redirect()->route('checkout.success', ['no_order' => $order->no_order]);
    }

    /**
     * Halaman sukses checkout.
     */
    public function success(Request $request): Response
    {
        $noOrder = $request->string('no_order')->toString();

        // Hanya pembuat order (session checkout) atau pemilik akun yang boleh melihat.
        $ownedOrders = (array) session('checkout_orders', []);
        $isOwner = auth()->check()
            ? Order::where('no_order', $noOrder)->where('user_id', auth()->id())->exists()
            : in_array($noOrder, $ownedOrders, true);

        abort_unless($isOwner, 404);

        $order = Order::query()
            ->where('no_order', $noOrder)
            ->with(['items.book:id,judul', 'user:id,name'])
            ->firstOrFail();

        return Inertia::render('storefront/CheckoutSuccess', [
            'order' => $order,
            'bankAccounts' => BankAccount::query()
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->get(['id', 'bank_name', 'account_number', 'account_holder']),
        ]);
    }

    /**
     * Hitung opsi ongkir utk keranjang saat ini (AJAX). Client mengirim
     * kode pos tujuan — server bangun daftar item dari keranjang.
     */
    public function shippingCosts(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'postal_code' => ['required', 'string', 'max:10'],
            'selected_groups' => ['sometimes', 'array'],
            'selected_groups.*' => ['string', 'max:100'],
        ]);

        $cart = $this->cart();

        if ($cart === []) {
            return response()->json(['weight_kg' => null, 'costs' => []]);
        }

        // Ongkir hanya dihitung untuk grup yang dipilih proses.
        $selectedGroups = $request->input('selected_groups');

        if ($selectedGroups !== null) {
            $cart = $this->filterCartBySelectedGroups($cart, (array) $selectedGroups);

            if ($cart === []) {
                return response()->json(['weight_kg' => null, 'costs' => []]);
            }
        }

        $items = $this->cartToBiteshipItems($cart);
        $weightGrams = (int) collect($items)->sum(fn (array $item): int => $item['weight_grams'] * $item['quantity']);

        try {
            $costs = $this->shippingCost->costs($validated['postal_code'], $items);
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'weight_kg' => round($weightGrams / 1000, 2),
            'costs' => $costs,
        ]);
    }

    /**
     * Bangun items[] untuk payload Biteship dari keranjang session.
     *
     * @param  array<string, array{qty: int, edition_id: int|null}>  $cart
     * @return array<int, array{name: string, value: int, quantity: int, weight_grams: int}>
     */
    private function cartToBiteshipItems(array $cart): array
    {
        $books = Book::query()
            ->whereKey($this->cartBookIds($cart))
            ->get(['id', 'judul', 'harga', 'berat_gr'])
            ->keyBy('id');

        $items = [];

        foreach ($cart as $key => $entry) {
            $book = $books->get($this->keyBookId($key));

            if ($book === null) {
                continue;
            }

            $items[] = [
                'name' => $book->judul,
                'value' => $book->harga,
                'quantity' => (int) $entry['qty'],
                'weight_grams' => max(100, (int) ($book->berat_gr ?? 100)),
            ];
        }

        return $items;
    }

    /**
     * Fast-fail validasi stok (tanpa lock) — error ramah sebelum transaksi.
     * Bila item memilih cetakan, stok dicek per cetakan.
     */
    private function validateCartStock(array $cart): ?string
    {
        $books = Book::query()
            ->whereKey($this->cartBookIds($cart))
            ->get(['id', 'judul', 'stok', 'aktif', 'is_preorder'])
            ->keyBy('id');

        $editionIds = collect($cart)->pluck('edition_id')->filter()->unique()->values()->all();
        $editions = BookEdition::query()
            ->whereKey($editionIds)
            ->withSum(['stocks as sellable_total' => fn ($q) => $q->whereHas('warehouse', fn ($w) => $w->sellable())], 'qty')
            ->get()
            ->keyBy('id');

        foreach ($cart as $key => $entry) {
            $book = $books->get($this->keyBookId($key));

            if ($book === null || ! $book->aktif) {
                return 'Buku di keranjang sudah tidak tersedia.';
            }

            // Buku pre-order menunggu stok — tidak dicek stok.
            if ($book->is_preorder) {
                continue;
            }

            $editionId = $entry['edition_id'] ?? null;
            $available = $editionId !== null
                ? (int) ($editions->get($editionId)?->sellable_total ?? 0)
                : (int) $book->stok;

            if ($available < (int) $entry['qty']) {
                $label = $editionId !== null ? "{$book->judul} (cetakan terpilih)" : $book->judul;

                return "Stok {$label} tidak mencukupi (tersisa {$available}).";
            }
        }

        return null;
    }

    /**
     * @param  array<string, array{qty: int, edition_id: int|null}>  $cart
     * @return array<int, array{book_id: int, qty: int, book_edition_id: int|null, edition_snapshot: string|null, is_preorder: bool}>
     */
    private function cartToItems(array $cart): array
    {
        $items = [];

        $preorderIds = Book::query()
            ->whereKey($this->cartBookIds($cart))
            ->where('is_preorder', true)
            ->pluck('id')
            ->map(static fn (mixed $id): string => (string) $id)
            ->flip();

        foreach ($cart as $key => $entry) {
            $editionId = $entry['edition_id'] ?? null;
            $bookId = $this->keyBookId($key);

            $items[] = [
                'book_id' => $bookId,
                'qty' => (int) $entry['qty'],
                'book_edition_id' => $editionId,
                'edition_snapshot' => $editionId !== null
                    ? 'Cetakan ke-'.(BookEdition::query()->whereKey($editionId)->value('cetakan_ke') ?? '?')
                    : null,
                'is_preorder' => $preorderIds->has($bookId),
            ];
        }

        return $items;
    }

    /**
     * Ambil book_id dari kunci keranjang ('123' atau '123:45').
     */
    private function keyBookId(string $key): string
    {
        return explode(':', $key)[0];
    }

    /**
     * Kunci keranjang utk buku + cetakan.
     */
    private function cartKey(string $bookId, ?string $editionId): string
    {
        return $editionId !== null ? $bookId.':'.$editionId : (string) $bookId;
    }

    /**
     * Entri keranjang default (atau yang sudah ada) utk buku + cetakan.
     *
     * @param  array<string, array{qty: int, edition_id: int|null}>  $cart
     * @return array{qty: int, edition_id: int|null}
     */
    private function cartEntry(array $cart, string $bookId, ?string $editionId): array
    {
        $key = $this->cartKey($bookId, $editionId);
        $existing = $cart[$key] ?? null;

        return [
            'qty' => (int) ($existing['qty'] ?? 0),
            'edition_id' => $editionId,
        ];
    }

    /**
     * Semua book_id unik dari keranjang.
     *
     * @param  array<string, array{qty: int, edition_id: string|null}>  $cart
     * @return list<string>
     */
    private function cartBookIds(array $cart): array
    {
        return array_values(array_unique(array_map(
            fn (string $key): string => $this->keyBookId($key),
            array_keys($cart),
        )));
    }

    /**
     * Hapus semua entri (semua cetakan) sebuah buku dari keranjang.
     *
     * @param  array<string, array{qty: int, edition_id: int|null}>  $cart
     */
    private function removeBookFromCart(array &$cart, string $bookId): void
    {
        foreach (array_keys($cart) as $key) {
            if ($this->keyBookId($key) === $bookId) {
                unset($cart[$key]);
            }
        }
    }

    /**
     * @return array<string, array{qty: int, edition_id: string|null}>
     */
    private function cart(): array
    {
        $cart = session('cart', []);

        if (! is_array($cart)) {
            return [];
        }

        // Normalisasi legacy: [bookId => qty] → [key => entry], plus buang
        // entri ber-id integer (sisa sebelum konversi UUID) yang sudah tidak
        // valid — query ke kolom uuid akan error bila id lama dibiarkan.
        $changed = false;
        $dropped = 0;

        foreach ($cart as $key => $value) {
            if (! Str::isUuid($this->keyBookId($key))) {
                unset($cart[$key]);
                $changed = true;
                $dropped++;

                continue;
            }

            if (! is_array($value)) {
                $cart[$key] = ['qty' => (int) $value, 'edition_id' => null];
                $changed = true;
            } elseif (! isset($value['qty'])) {
                $cart[$key] = ['qty' => (int) ($value['qty'] ?? 0), 'edition_id' => $value['edition_id'] ?? null];
                $changed = true;
            }

            // Cetakan lama (id integer) → fallback ke cetakan default.
            if (($cart[$key]['edition_id'] ?? null) !== null && ! Str::isUuid((string) $cart[$key]['edition_id'])) {
                $cart[$key]['edition_id'] = null;
                $changed = true;
            }
        }

        if ($changed) {
            session(['cart' => $cart]);

            if ($dropped > 0) {
                Inertia::flash('toast', [
                    'type' => 'info',
                    'message' => $dropped === 1
                        ? '1 item lama di keranjang dihapus karena tidak tersedia lagi.'
                        : "{$dropped} item lama di keranjang dihapus karena tidak tersedia lagi.",
                ]);
            }
        }

        return $cart;
    }

    /**
     * Kelompokkan item keranjang per paket bundle; item di luar bundle masuk
     * grup "Item Lainnya" (harga normal + promo satuan). Buku bundle yang
     * paketnya TIDAK lengkap ikut grup regular.
     *
     * @return array<int, array<string, mixed>>
     */
    private function cartGroups(): array
    {
        $items = $this->cartItems();
        $tier = auth()->user()?->status_pelanggan;
        $bundleDiscounts = $this->pricing->cartBundleDiscounts($items);
        // Hanya bundle yang LENGKAP di keranjang yang membentuk grup promo.
        $bookToPromo = $this->completedBundlePromoMap($items);

        $groups = [];

        foreach ($items as $item) {
            $book = $item['book'];
            $promo = $bookToPromo[$book->id] ?? null;
            $key = $promo !== null ? 'bundle-'.$promo->id : 'regular';

            $breakdown = $this->pricing->priceBreakdown($book, $item['qty'], $tier);
            $bundleDiscount = $bundleDiscounts[$book->id] ?? 0;

            // Buku dalam bundle LENGKAP: HANYA diskon bundle — promo satuan
            // & tier tidak bertumpuk. Diskon berlaku utk 1 SET pertama saja.
            $inBundle = $promo !== null;
            $bundleQty = $inBundle ? min($item['qty'], 1) : 0;
            $regularFinal = max(0, $breakdown->finalPrice - $bundleDiscount);
            $bundleUnitFinal = max(0, $breakdown->originalPrice - $bundleDiscount);

            $enriched = [
                'book' => $book,
                'qty' => $item['qty'],
                'edition_label' => $item['edition_label'] ?? null,
                'editions' => $item['editions'] ?? [],
                'price_original' => $breakdown->originalPrice,
                'promo_discount' => $inBundle ? 0 : $breakdown->promoDiscount,
                'promo_name' => $inBundle ? null : $breakdown->promoName,
                'bundle_discount' => $bundleDiscount,
                'bundle_qty' => $bundleQty,
                'tier_discount' => $inBundle ? 0 : $breakdown->tierDiscount,
                'unit_final' => $inBundle ? $bundleUnitFinal : $regularFinal,
                'item_total' => $inBundle
                    ? $bundleQty * $bundleUnitFinal + ($item['qty'] - $bundleQty) * $breakdown->originalPrice
                    : $regularFinal * $item['qty'],
            ];

            $group = $groups[$key] ?? [
                'key' => $key,
                'name' => $promo?->promo_name ?? 'Item Lainnya',
                'discount_percent' => $promo?->discount_percentage ?? null,
                'items' => [],
                'subtotal' => 0,
                'discount_total' => 0,
                'total' => 0,
            ];

            $group['items'][] = $enriched;
            $group['subtotal'] += $enriched['price_original'] * $enriched['qty'];
            $group['discount_total'] += ($enriched['price_original'] * $enriched['qty']) - $enriched['item_total'];
            $group['total'] += $enriched['item_total'];
            $groups[$key] = $group;
        }

        // Grup bundle tampil lebih dulu, "Item Lainnya" di akhir.
        uksort($groups, fn ($a, $b): int => $a === 'regular'
            ? 1
            : ($b === 'regular' ? -1 : strcmp($a, $b)));

        return array_values($groups);
    }

    /**
     * Map book_id => promo bundle aktif yang LENGKAP ada di keranjang
     * (semua buku bundle ada), promo dengan diskon terbesar yang menang.
     *
     * @param  array<int, array{book: Book, qty: int}>  $items
     * @return array<int, Promotion|null>
     */
    private function completedBundlePromoMap(array $items): array
    {
        $cartBookIds = collect($items)->pluck('book.id')->all();

        $promos = Promotion::query()
            ->where('promo_type', PromotionType::Bundle->value)
            ->where('is_active', true)
            ->whereDate('start_date', '<=', now()->toDateString())
            ->whereDate('end_date', '>=', now()->toDateString())
            ->with('books:id')
            ->get();

        $map = [];

        foreach ($promos as $promo) {
            $bundleBookIds = $promo->books->pluck('id')->all();

            // Bundle harus punya buku spesifik & LENGKAP di keranjang.
            if (count($bundleBookIds) === 0 || count(array_diff($bundleBookIds, $cartBookIds)) > 0) {
                continue;
            }

            foreach ($bundleBookIds as $bookId) {
                $current = $map[$bookId] ?? null;

                if ($current === null || ($promo->discount_percentage ?? 0) > ($current->discount_percentage ?? 0)) {
                    $map[$bookId] = $promo;
                }
            }
        }

        return $map;
    }

    /**
     * Sisakan hanya item keranjang yang grupnya dipilih.
     *
     * @param  array<string, array{qty: int, edition_id: int|null}>  $cart
     * @param  array<int, string>  $selectedGroups
     * @return array<string, array{qty: int, edition_id: int|null}>
     */
    private function filterCartBySelectedGroups(array $cart, array $selectedGroups): array
    {
        $items = $this->cartItems();
        $bookToPromo = $this->completedBundlePromoMap($items);
        $filtered = [];

        foreach ($cart as $key => $entry) {
            $bookId = $this->keyBookId($key);
            $promo = $bookToPromo[$bookId] ?? null;
            $groupKey = $promo !== null ? 'bundle-'.$promo->id : 'regular';

            if (in_array($groupKey, $selectedGroups, true)) {
                $filtered[$key] = $entry;
            }
        }

        return $filtered;
    }

    /**
     * Item keranjang dengan data buku lengkap. Bila cetakan dipilih, harga
     * buku di-mutasi in-memory ke harga jual cetakan tsb sehingga seluruh
     * logika harga (promo, tier, bundle) memakai harga yang benar.
     *
     * @return array<int, array{book: Book, qty: int, edition: BookEdition|null, edition_label: string|null}>
     */
    private function cartItems(): array
    {
        $cart = $this->cart();

        if ($cart === []) {
            return [];
        }

        $books = Book::query()
            ->whereKey($this->cartBookIds($cart))
            ->where('aktif', true)
            ->whereNotNull('harga')
            ->get()
            ->keyBy('id');

        $editionIds = collect($cart)->pluck('edition_id')->filter()->unique()->values()->all();
        $editions = BookEdition::query()
            ->whereKey($editionIds)
            ->withSum(['stocks as sellable_total' => fn ($q) => $q->whereHas('warehouse', fn ($w) => $w->sellable())], 'qty')
            ->get()
            ->keyBy('id');

        // Semua cetakan utk tiap buku di keranjang — dipakai dropdown ganti cetakan.
        $allEditions = BookEdition::query()
            ->whereIn('book_id', $this->cartBookIds($cart))
            ->withSum(['stocks as sellable_total' => fn ($q) => $q->whereHas('warehouse', fn ($w) => $w->sellable())], 'qty')
            ->orderBy('cetakan_ke')
            ->get()
            ->groupBy('book_id');

        $items = [];

        foreach ($cart as $key => $entry) {
            $bookId = $this->keyBookId($key);
            $book = $books->get($bookId);

            if ($book === null) {
                continue;
            }

            $edition = null;
            $editionId = $entry['edition_id'] ?? null;

            if ($editionId !== null) {
                $edition = $editions->get($editionId);

                // Cetakan valid & milik buku ini — harga & stok dasar ikut edisi.
                // Klon Book agar dua cetakan dari buku yang sama tidak saling timpa.
                if ($edition !== null && $edition->book_id === $book->id) {
                    $clone = $book->replicate();
                    $clone->exists = true;
                    $clone->id = $book->id;
                    $clone->harga = $edition->harga_jual;
                    $clone->stok = (int) ($edition->sellable_total ?? $edition->stockTotal());
                    $book = $clone;
                } else {
                    $edition = null;
                }
            }

            $items[] = [
                'book' => $book,
                'qty' => (int) $entry['qty'],
                'edition' => $edition,
                'edition_label' => $edition !== null ? "Cetakan ke-{$edition->cetakan_ke}" : null,
                'editions' => ($allEditions->get($book->id) ?? collect())->map(
                    fn (BookEdition $e): array => [
                        'id' => $e->id,
                        'cetakan_ke' => $e->cetakan_ke,
                        'harga_jual' => $e->harga_jual,
                        'is_active' => $e->is_active,
                        'stok' => (int) ($e->sellable_total ?? 0),
                    ]
                )->values()->all(),
            ];
        }

        return $items;
    }

    private function generateOrderNumber(): string
    {
        return 'SF-'.now()->format('YmdHis').'-'.random_int(100, 999);
    }
}

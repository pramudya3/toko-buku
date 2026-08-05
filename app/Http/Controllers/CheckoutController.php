<?php

namespace App\Http\Controllers;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Http\Requests\Storefront\CheckoutRequest;
use App\Models\Book;
use App\Models\Order;
use App\Services\PricingService;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;

/**
 * Checkout storefront — keranjang berbasis session + pembuatan order.
 */
class CheckoutController extends Controller
{
    public function __construct(private readonly PricingService $pricing) {}

    /**
     * Halaman checkout: item keranjang + form data diri.
     */
    public function index(): Response
    {
        $items = $this->cartItems();
        $user = auth()->user();

        return Inertia::render('storefront/Checkout', [
            'items' => $items,
            'paymentOptions' => PaymentMethod::options(),
            'couriers' => config('shipping.couriers'),
            'user' => $user ? [
                'name' => $user->name,
                'whatsapp_number' => $user->whatsapp_number,
                'email' => $user->email,
                'alamat' => $user->alamat,
                'provinsi' => $user->provinsi,
                'kabupaten_kota' => $user->kabupaten_kota,
                'kecamatan' => $user->kecamatan,
                'kode_pos' => $user->kode_pos,
            ] : null,
        ]);
    }

    /**
     * Tambah buku ke keranjang (session).
     */
    public function add(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'book_id' => ['required', 'exists:books,id'],
            'qty' => ['required', 'integer', 'min:1'],
        ]);

        $cart = $this->cart();
        $bookId = (int) $validated['book_id'];
        $cart[$bookId] = ($cart[$bookId] ?? 0) + (int) $validated['qty'];
        session(['cart' => $cart]);

        $book = Book::find($bookId);
        $label = $book?->judul ?? 'Buku';

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => "{$label} ditambahkan ke keranjang.",
        ]);

        return redirect()->route('checkout.index');
    }

    /**
     * Hapus item dari keranjang.
     */
    public function remove(int $bookId): RedirectResponse
    {
        $cart = $this->cart();
        $book = Book::find($bookId);
        $label = $book?->judul ?? 'Item';

        unset($cart[$bookId]);
        session(['cart' => $cart]);

        Inertia::flash('toast', [
            'type' => 'info',
            'message' => "{$label} dihapus dari keranjang.",
        ]);

        return back();
    }

    /**
     * Ubah qty item di keranjang.
     */
    public function updateQty(Request $request, int $bookId): RedirectResponse
    {
        $qty = (int) $request->input('qty');

        if ($qty < 1) {
            return $this->remove($bookId);
        }

        $cart = $this->cart();
        $cart[$bookId] = $qty;
        session(['cart' => $cart]);

        return back();
    }

    /**
     * Proses pembelian — buat order dengan status menunggu konfirmasi.
     */
    public function store(CheckoutRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $cart = $this->cart();

        if ($cart === []) {
            return back()->withErrors(['items' => 'Keranjang belanja kosong.']);
        }

        // Fast-fail check (tanpa lock) untuk error yang ramah.
        $stockError = $this->validateCartStock($cart);

        if ($stockError !== null) {
            return back()->withErrors(['items' => $stockError]);
        }

        $order = null;

        for ($attempt = 0; $attempt < 3; $attempt++) {
            try {
                $order = DB::transaction(function () use ($data, $cart): Order {
                    // Lock baris buku agar tidak oversell saat 2 checkout bersamaan.
                    $lockedBooks = Book::query()
                        ->whereKey(array_keys($cart))
                        ->lockForUpdate()
                        ->get()
                        ->keyBy('id');

                    foreach ($cart as $bookId => $qty) {
                        $book = $lockedBooks->get($bookId);

                        if ($book === null || ! $book->aktif) {
                            throw new RuntimeException('Buku di keranjang sudah tidak tersedia.');
                        }

                        if ($book->stok < $qty) {
                            throw new RuntimeException("Stok {$book->judul} tidak mencukupi (tersisa {$book->stok}).");
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
                        'kode_pos' => $data['kode_pos'] ?? null,
                        'metode_bayar' => $data['metode_bayar'],
                        'total' => 0,
                        'ekspedisi' => $data['ekspedisi'] ?? null,
                        'ongkir_estimasi' => null,
                        'is_dropship' => false,
                        'status' => OrderStatus::MenungguKonfirmasi,
                    ]);

                    $this->pricing->storeOrderWithItems($order, $this->cartToItems($cart));

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
        ]);
    }

    /**
     * Fast-fail validasi stok (tanpa lock) — error ramah sebelum transaksi.
     */
    private function validateCartStock(array $cart): ?string
    {
        $books = Book::query()
            ->whereKey(array_keys($cart))
            ->get(['id', 'judul', 'stok', 'aktif'])
            ->keyBy('id');

        foreach ($cart as $bookId => $qty) {
            $book = $books->get($bookId);

            if ($book === null || ! $book->aktif) {
                return 'Buku di keranjang sudah tidak tersedia.';
            }

            if ($book->stok < $qty) {
                return "Stok {$book->judul} tidak mencukupi (tersisa {$book->stok}).";
            }
        }

        return null;
    }

    /**
     * @return array<int, array{book_id: int, qty: int}>
     */
    private function cartToItems(array $cart): array
    {
        $items = [];

        foreach ($cart as $bookId => $qty) {
            $items[] = ['book_id' => (int) $bookId, 'qty' => $qty];
        }

        return $items;
    }

    /**
     * @return array<int, int>
     */
    private function cart(): array
    {
        $cart = session('cart', []);

        return is_array($cart) ? $cart : [];
    }

    /**
     * Item keranjang dengan data buku lengkap.
     *
     * @return array<int, array{book: Book, qty: int}>
     */
    private function cartItems(): array
    {
        $cart = $this->cart();

        if ($cart === []) {
            return [];
        }

        $books = Book::query()
            ->whereKey(array_keys($cart))
            ->where('aktif', true)
            ->get()
            ->keyBy('id');

        $items = [];

        foreach ($cart as $bookId => $qty) {
            $book = $books->get($bookId);

            if ($book !== null) {
                $items[] = ['book' => $book, 'qty' => $qty];
            }
        }

        return $items;
    }

    private function generateOrderNumber(): string
    {
        return 'SF-'.now()->format('YmdHis').'-'.random_int(100, 999);
    }
}

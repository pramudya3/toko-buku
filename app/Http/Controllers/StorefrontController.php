<?php

namespace App\Http\Controllers;

use App\Enums\PromotionType;
use App\Models\BankAccount;
use App\Models\Book;
use App\Models\BookEdition;
use App\Models\BookEditionStock;
use App\Models\Category;
use App\Models\Promotion;
use App\Models\Setting;
use App\Services\PricingService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Halaman publik storefront (katalog & detail buku).
 */
class StorefrontController extends Controller
{
    public function __construct(
        private readonly PricingService $pricing,
    ) {}

    /**
     * Katalog buku — hanya buku aktif, dengan search & filter kategori.
     */
    public function catalog(Request $request): Response
    {
        $paginator = $this->filteredBooks($request)
            ->paginate(10)
            ->withQueryString();

        $bookIds = $paginator->getCollection()->pluck('id');
        $bookPromos = $this->eagerLoadPromotions($bookIds);

        return Inertia::render('storefront/Catalog', [
            'books' => $paginator->through(
                fn (Book $book) => $this->bookWithPricing($book, $bookPromos[$book->id] ?? null),
            ),
            'categories' => Category::orderBy('nama')->get(['id', 'nama']),
            'bundles' => $this->activeBundlesForStorefront(),
            'filters' => $request->only(['search', 'category_id']),
        ]);
    }

    /**
     * Load more — halaman berikutnya dalam bentuk JSON (load-more pagination).
     */
    public function loadMore(Request $request): JsonResponse
    {
        $paginator = $this->filteredBooks($request)->paginate(10);
        $bookIds = $paginator->getCollection()->pluck('id');
        $bookPromos = $this->eagerLoadPromotions($bookIds);

        return response()->json(
            $paginator->through(
                fn (Book $book) => $this->bookWithPricing($book, $bookPromos[$book->id] ?? null),
            ),
        );
    }

    /**
     * @return Builder<Book>
     */
    private function filteredBooks(Request $request)
    {
        return Book::query()
            ->where('aktif', true)
            ->whereNotNull('harga')
            ->with('category:id,nama')
            ->when($request->filled('search'), function ($query) use ($request): void {
                $search = $request->string('search')->toString();

                $query->where(function ($query) use ($search): void {
                    $query->whereLike('judul', "%{$search}%")
                        ->orWhereLike('penulis', "%{$search}%");
                });
            })
            ->when($request->filled('category_id'), fn ($query) => $query->where('category_id', $request->string('category_id')->toString()))
            ->orderBy('judul');
    }

    /**
     * Detail buku publik.
     *
     * Param {bookUrl} = {uuid}-{judul-bersih}; 36 karakter pertama adalah
     * uuid (identitas), sisanya judul yang dibersihkan — hanya hiasan URL.
     */
    public function show(string $bookUrl): Response
    {
        $book = Book::findOrFail(Str::substr($bookUrl, 0, 36));

        abort_unless($book->aktif && $book->harga !== null, 404);

        $book->load('category:id,nama', 'editions', 'images:id,book_id,image_url,urutan');

        // Stok sellable per cetakan — dipakai frontend untuk cap qty sesuai
        // cetakan terpilih (server juga memvalidasi ulang saat add ke keranjang).
        $editionStocks = BookEditionStock::query()
            ->whereIn('book_edition_id', $book->editions->pluck('id'))
            ->whereHas('warehouse', fn ($q) => $q->sellable())
            ->selectRaw('book_edition_id, SUM(qty) as total')
            ->groupBy('book_edition_id')
            ->pluck('total', 'book_edition_id');

        $book->setRelation('editions', $book->editions->map(function (BookEdition $edition) use ($editionStocks): BookEdition {
            $edition->stok_sellable = (int) ($editionStocks[$edition->id] ?? 0);

            return $edition;
        }));

        return Inertia::render('storefront/BookDetail', [
            'book' => $this->bookWithPricing($book, $this->pricing->activePromotion($book)),
        ]);
    }

    /**
     * Halaman tentang kami — konten dari pengaturan Lembaga.
     */
    public function about(): Response
    {
        return Inertia::render('storefront/About', [
            'nama_lembaga' => Setting::get('store_nama_lembaga', ''),
            'logo_url' => Setting::get('store_logo_url', ''),
            'tagline' => Setting::get('store_tagline', ''),
            'deskripsi' => Setting::get('store_deskripsi', ''),
            'visi' => Setting::get('store_visi', ''),
            'misi' => Setting::get('store_misi', ''),
            'keamanan' => Setting::get('store_keamanan', ''),
            'syarat' => Setting::get('store_syarat', ''),
            'telepon' => Setting::get('store_telepon', ''),
            'email' => Setting::get('store_email', ''),
            'jam_operasional' => Setting::get('store_jam_operasional', ''),
            'alamat' => Setting::get('store_alamat', ''),
            'bankAccounts' => BankAccount::query()
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->get(['id', 'bank_name', 'account_number', 'account_holder']),
        ]);
    }

    /**
     * Paket bundle aktif untuk section "Paket Hemat" di katalog.
     * Harga paket dihitung dari PricingService (satu sumber kebenaran, tanpa tier).
     *
     * @return array<int, array<string, mixed>>
     */
    private function activeBundlesForStorefront(): array
    {
        $today = now()->toDateString();

        $promos = Promotion::query()
            ->where('promo_type', PromotionType::Bundle->value)
            ->where('is_active', true)
            ->whereDate('start_date', '<=', $today)
            ->whereDate('end_date', '>=', $today)
            ->with('books:id,judul,cover_url,harga,stok')
            ->orderByDesc('id')
            ->get();

        return $promos->map(function (Promotion $promo): array {
            $breakdown = $this->pricing->bundleBreakdown($promo);

            return [
                'id' => $promo->id,
                'promo_name' => $promo->promo_name,
                'discount_percent' => $breakdown['discount_percent'],
                'books' => collect($breakdown['items'])
                    ->map(fn (array $item): array => [
                        'id' => $item['book']->id,
                        'judul' => $item['book']->judul,
                        'cover_url' => $item['book']->cover_url,
                        'price_original' => $item['book']->harga,
                        'unit_price' => $item['unit_price'],
                        'unit_discount' => $item['unit_discount'],
                        'unit_final' => $item['unit_final'],
                        'stok' => (int) $item['book']->stok,
                    ])
                    ->values()
                    ->all(),
                'total_original' => $breakdown['total_original'],
                'total_discount' => $breakdown['total_discount'],
                'total_final' => $breakdown['total_final'],
            ];
        })->values()->all();
    }

    /**
     * Eager-load promosi aktif untuk sekumpulan buku.
     * Return map book_id => Promotion|null.
     *
     * @param  Collection<int, int>  $bookIds
     * @return array<int, Promotion|null>
     */
    private function eagerLoadPromotions($bookIds): array
    {
        $today = now()->toDateString();

        $promotions = Promotion::query()
            ->where('is_active', true)
            // Bundle tidak berlaku untuk harga per unit di katalog/detail.
            ->where('promo_type', '!=', PromotionType::Bundle->value)
            ->whereDate('start_date', '<=', $today)
            ->whereDate('end_date', '>=', $today)
            ->where(function ($query) use ($bookIds): void {
                $query->whereDoesntHave('books')
                    ->orWhereHas('books', function ($q) use ($bookIds): void {
                        $q->whereIn('books.id', $bookIds);
                    });
            })
            ->with('books:id')
            ->orderByDesc('end_date')
            ->orderByDesc('id')
            ->get();

        // Build map: book_id => best Promotion (or null).
        $globalPromo = $promotions->first(fn ($p) => $p->books->isEmpty());
        $map = [];

        foreach ($bookIds as $bookId) {
            $targeted = $promotions->first(
                fn ($p) => $p->books->isNotEmpty() && $p->books->contains('id', $bookId),
            );
            $map[$bookId] = $targeted ?? $globalPromo;
        }

        return $map;
    }

    /**
     * Ubah model Book jadi array + tambahkan price_breakdown.
     *
     * @return array<string, mixed>
     */
    private function bookWithPricing(Book $book, ?Promotion $promo): array
    {
        $breakdown = $this->pricing->priceBreakdownWithPromo($book, $promo, 1);
        $data = $book->toArray();

        if ($breakdown->promoDiscount > 0) {
            $data['price_breakdown'] = [
                'original_price' => $breakdown->originalPrice,
                'promo_discount' => $breakdown->promoDiscount,
                'final_price' => $breakdown->finalPrice,
                'promo_name' => $breakdown->promoName,
            ];
        }

        return $data;
    }
}

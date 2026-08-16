<?php

namespace App\Http\Controllers;

use App\Enums\PromotionType;
use App\Models\Article;
use App\Models\BankAccount;
use App\Models\Book;
use App\Models\BookEdition;
use App\Models\BookEditionStock;
use App\Models\Category;
use App\Models\Promotion;
use App\Models\Setting;
use App\Models\StockRequest;
use App\Models\Voucher;
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
        protected readonly PricingService $pricing,
    ) {}

    /**
     * Nama komponen Inertia yang dirender — subclass storefront (mis. proto-d)
     * menimpa ini untuk memakai data yang sama dengan halaman berbeda.
     */
    protected function page(string $name): string
    {
        return "storefront/{$name}";
    }

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

        return Inertia::render($this->page('Catalog'), [
            'books' => $paginator->through(
                fn (Book $book) => $this->bookWithPricing($book, $bookPromos[$book->id] ?? null),
            ),
            'categories' => Category::orderBy('nama')->get(['id', 'nama']),
            'bundles' => $this->activeBundlesForStorefront(),
            'promos' => $this->activeUnitPromosForStorefront(),
            'filters' => $request->only(['search', 'category_id', 'stok', 'sort']),
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
     * Halaman Promo — semua promo aktif (paket hemat + per item) lengkap
     * dengan harga final per buku. Menu "Promo" mengarah ke sini.
     */
    public function promo(Request $request): Response
    {
        return Inertia::render($this->page('Promo'), [
            'bundles' => $this->activeBundlesForStorefront(),
            'promos' => $this->activeUnitPromosForStorefront(bookLimit: 50, withPricing: true),
            'vouchers' => $this->vouchersForStorefront(),
            'filters' => $request->only(['search']),
        ]);
    }

    /**
     * Voucher aktif (periode berlaku) untuk halaman Promo — kuota & syarat
     * ditampilkan informatif; pemakaian tetap dipilih & divalidasi di checkout.
     *
     * @return array<int, array<string, mixed>>
     */
    private function vouchersForStorefront(): array
    {
        $today = now()->toDateString();

        return Voucher::query()
            ->withCount('usages')
            ->where('is_active', true)
            ->whereDate('start_date', '<=', $today)
            ->whereDate('end_date', '>=', $today)
            ->orderBy('end_date')
            ->orderBy('created_at')
            ->get()
            ->map(fn (Voucher $voucher): array => [
                'id' => $voucher->id,
                'nama' => $voucher->nama,
                'kode' => $voucher->kode,
                'voucher_type' => $voucher->voucher_type->value,
                'discount_scope' => $voucher->discount_scope->value,
                'discount_percentage' => $voucher->discount_percentage,
                'discount_value' => $voucher->discount_value,
                'min_order_amount' => $voucher->min_order_amount,
                'max_uses' => $voucher->max_uses,
                'usages_count' => (int) $voucher->usages_count,
                'start_date' => $voucher->start_date->toDateString(),
                'end_date' => $voucher->end_date->toDateString(),
            ])
            ->values()
            ->all();
    }

    /**
     * @return Builder<Book>
     */
    protected function filteredBooks(Request $request)
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
            ->when($request->string('stok')->toString() === 'ready', fn ($query) => $query->where('stok', '>', 0))
            ->when($request->string('stok')->toString() === 'preorder', fn ($query) => $query->where('is_preorder', true))
            ->when($request->string('stok')->toString() === 'empty', fn ($query) => $query->where('stok', '<=', 0)->where('is_preorder', false))
            ->when(
                $request->filled('sort'),
                // Urutan eksplisit (sort) — dipakai storefront proto-d.
                function ($query) use ($request): void {
                    $sort = $request->string('sort')->toString();

                    $query->orderBy(match ($sort) {
                        'newest' => 'created_at',
                        'cheapest' => 'harga',
                        'expensive' => 'harga',
                        default => 'judul',
                    }, $sort === 'cheapest' ? 'asc' : 'desc');
                },
                // Urutan default: tersedia → pre-order → habis; abjad di dalam tiap grup.
                function ($query): void {
                    $query->orderByRaw('(NOT is_preorder AND stok <= 0)')
                        ->orderBy('judul');
                },
            );
    }

    /**
     * Detail buku publik.
     *
     * Param {bookUrl} = {uuid}-{judul-bersih}; 36 karakter pertama adalah
     * uuid (identitas), sisanya judul yang dibersihkan — hanya hiasan URL.
     */
    public function show(Request $request, string $bookUrl): Response
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

        return Inertia::render($this->page('BookDetail'), [
            'book' => $this->bookWithPricing($book, $this->pricing->activePromotion($book)),
            // Apakah user login sudah mengajukan stok untuk buku ini.
            'requested' => $request->user()
                ? StockRequest::where('book_id', $book->id)
                    ->where('user_id', $request->user()->id)
                    ->exists()
                : false,
        ]);
    }

    /**
     * Daftar artikel storefront — hanya yang aktif & sudah terbit.
     */
    public function articles(Request $request): Response
    {
        $articles = Article::published()
            ->with('category:id,nama')
            ->orderByDesc('published_at')
            ->orderByDesc('created_at')
            ->paginate(9)
            ->withQueryString()
            ->through(fn (Article $article): array => $this->articleCard($article));

        return Inertia::render($this->page('ArticleList'), [
            'articles' => $articles,
        ]);
    }

    /**
     * Detail artikel — 404 bila nonaktif atau belum terbit.
     */
    public function articleShow(Article $article): Response
    {
        abort_unless(Article::published()->whereKey($article->getKey())->exists(), 404);

        $article->load('category:id,nama');

        return Inertia::render($this->page('ArticleDetail'), [
            'article' => $this->articleDetail($article),
        ]);
    }

    /**
     * Kartu artikel untuk daftar — tanpa isi (badan artikel berat).
     *
     * @return array<string, mixed>
     */
    protected function articleCard(Article $article): array
    {
        return [
            'id' => $article->id,
            'judul' => $article->judul,
            'slug' => $article->slug,
            'kategori_id' => $article->article_category_id,
            'kategori_label' => $article->category->nama ?? 'Tanpa kategori',
            'penulis' => $article->penulis,
            'ringkasan' => $article->ringkasan,
            'published_at' => $article->published_at,
            'motif' => $article->motif,
            'cover_url' => $article->cover_url,
            'menit' => $this->readingMinutes($article->isi),
        ];
    }

    /**
     * Detail artikel lengkap (termasuk isi untuk halaman baca).
     *
     * @return array<string, mixed>
     */
    protected function articleDetail(Article $article): array
    {
        return $this->articleCard($article) + ['isi' => $article->isi];
    }

    /**
     * Estimasi lama baca (~200 kata per menit).
     */
    protected function readingMinutes(string $isi): int
    {
        $words = str_word_count(strip_tags($isi));

        return max(1, (int) ceil($words / 200));
    }

    /**
     * Halaman tentang kami — konten dari pengaturan Lembaga.
     */
    public function about(): Response
    {
        return Inertia::render($this->page('About'), [
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
    protected function activeBundlesForStorefront(): array
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
     * Promo per item (persentase/harga tetap) yang sedang aktif untuk
     * section "Promo" di katalog. Buku global (tanpa terlampir) diflag.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function activeUnitPromosForStorefront(int $bookLimit = 4, bool $withPricing = false): array
    {
        $today = now()->toDateString();

        $promos = Promotion::query()
            ->where('promo_type', '!=', PromotionType::Bundle->value)
            ->where('is_active', true)
            ->whereDate('start_date', '<=', $today)
            ->whereDate('end_date', '>=', $today)
            ->with('books:id,judul,cover_url,harga')
            ->orderByDesc('end_date')
            ->orderByDesc('id')
            ->get();

        return $promos->map(function (Promotion $promo) use ($bookLimit, $withPricing): array {
            return [
                'id' => $promo->id,
                'promo_name' => $promo->promo_name,
                'promo_type' => $promo->promo_type->value,
                'discount_percentage' => $promo->discount_percentage,
                'promo_value' => $promo->promo_value,
                'start_date' => $promo->start_date->toDateString(),
                'end_date' => $promo->end_date->toDateString(),
                'is_global' => $promo->is_global,
                'books' => $promo->books
                    ->take($bookLimit)
                    ->map(fn (Book $book): array => $withPricing
                        ? $this->promoBookWithPricing($book, $promo)
                        : [
                            'id' => $book->id,
                            'judul' => $book->judul,
                            'cover_url' => $book->cover_url,
                        ])
                    ->values()
                    ->all(),
            ];
        })->values()->all();
    }

    /**
     * Buku promo dengan harga final (untuk halaman Promo).
     *
     * @return array<string, mixed>
     */
    protected function promoBookWithPricing(Book $book, Promotion $promo): array
    {
        $breakdown = $this->pricing->priceBreakdownWithPromo($book, $promo, 1);

        return [
            'id' => $book->id,
            'judul' => $book->judul,
            'cover_url' => $book->cover_url,
            'harga' => $book->harga,
            'price_breakdown' => $breakdown->promoDiscount > 0 ? [
                'original_price' => $breakdown->originalPrice,
                'promo_discount' => $breakdown->promoDiscount,
                'final_price' => $breakdown->finalPrice,
                'promo_name' => $breakdown->promoName,
            ] : null,
        ];
    }

    /**
     * Eager-load promosi aktif untuk sekumpulan buku.
     * Return map book_id => Promotion|null.
     *
     * @param  Collection<int, int>  $bookIds
     * @return array<int, Promotion|null>
     */
    protected function eagerLoadPromotions($bookIds): array
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
    protected function bookWithPricing(Book $book, ?Promotion $promo): array
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

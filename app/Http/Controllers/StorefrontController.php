<?php

namespace App\Http\Controllers;

use App\Enums\PromotionType;
use App\Http\Requests\Storefront\ArticleHomeRequest;
use App\Models\Article;
use App\Models\ArticleCategory;
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
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
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

    protected function page(string $name): string
    {
        return "storefront/{$name}";
    }

    /**
     * Query artikel storefront dengan filter pencarian & facet.
     *
     * Filter dipakai bersama oleh storefront utama (limit 20) dan proto-d
     * (paginate + load-more). Pencarian mencakup judul, ringkasan, dan isi.
     * Kategori jamak memakai logika OR (whereIn) — artikel cocok bila masuk
     * salah satu kategori terpilih.
     *
     * @return Builder<Article>
     */
    protected function articleFeedQuery(Request $request): Builder
    {
        return Article::query()->published()
            ->with('category:id,nama')
            ->orderByDesc('published_at')
            ->orderByDesc('created_at')
            ->when($request->filled('search'), function (Builder $query) use ($request): void {
                $search = $request->string('search')->toString();
                $query->where(function (Builder $q) use ($search): void {
                    $q->whereLike('judul', "%{$search}%")
                        ->orWhereLike('ringkasan', "%{$search}%")
                        ->orWhereLike('isi', "%{$search}%");
                });
            })
            ->when($request->filled('penulis'), fn (Builder $query) => $query->where('penulis', $request->string('penulis')->toString()))
            ->when($request->filled('category_id'), fn (Builder $query) => $query->where('article_category_id', $request->string('category_id')->toString()))
            ->when(
                $request->filled('categories'),
                fn (Builder $query) => $query->whereIn('article_category_id', $request->input('categories')),
            )
            ->when($request->filled('month'), fn (Builder $query) => $query->whereMonth('published_at', $request->integer('month')))
            ->when($request->filled('year'), fn (Builder $query) => $query->whereYear('published_at', $request->integer('year')));
    }

    /**
     * Facet sidebar artikel: kategori (dengan jumlah terbit) + daftar
     * bulan/tahun (dengan jumlah artikel per bulan). Di-cache 1 jam dan
     * dihapus lewat observer Article/ArticleCategory saat konten berubah.
     *
     * @return array{articleCategories: array<int, array{id: string, nama: string, articles_count: int}>, dateFacets: array<int, array{year: int, month: int, count: int}>}
     */
    protected function articleFacets(): array
    {
        return Cache::flexible('storefront.article_facets', [3600, 7200], function (): array {
            $articleCategories = ArticleCategory::query()
                ->withCount(['articles' => fn (Builder $query) => $query->where('is_active', true)->where(function (Builder $q): void {
                    $q->whereNull('published_at')->orWhereDate('published_at', '<=', today());
                })])
                ->orderBy('nama')
                ->get(['id', 'nama'])
                ->map(fn (ArticleCategory $category): array => [
                    'id' => $category->id,
                    'nama' => $category->nama,
                    'articles_count' => (int) $category->articles_count,
                ])
                ->all();

            // DB-side aggregation — avoid pluck + collection countBy on large tables.
            $driver = DB::getDriverName();
            $isSqlite = $driver === 'sqlite';

            $dateFacetsQuery = Article::query()->published()
                ->whereNotNull('published_at')
                ->selectRaw($isSqlite
                    ? "CAST(strftime('%Y', published_at) AS INTEGER) as year, CAST(strftime('%m', published_at) AS INTEGER) as month, COUNT(*) as count"
                    : 'YEAR(published_at) as year, MONTH(published_at) as month, COUNT(*) as count'
                )
                ->groupByRaw($isSqlite ? "strftime('%Y-%m', published_at)" : 'YEAR(published_at), MONTH(published_at)')
                ->orderByDesc('year')
                ->orderByDesc('month');

            $dateFacets = $dateFacetsQuery->get()->map(function ($row): array {
                /** @var object{year: int, month: int, count: int} $row */
                return [
                    'year' => (int) $row->year,
                    'month' => (int) $row->month,
                    'count' => (int) $row->count,
                ];
            })->all();

            return compact('articleCategories', 'dateFacets');
        });
    }

    /**
     * Beranda editorial artikel — halaman utama "/".
     *
     * Layout: Artikel Unggulan (featured, pilihan admin) → Artikel Terbaru
     * (5 terbaru upload) → feed semua artikel (filter kategori + pencarian) →
     * Buku Pilihan (iklan kecil dari toko). Saat ada filter pencarian/kategori,
     * hero unggulan & section Terbaru disembunyikan — feed menampilkan semua
     * artikel yang cocok.
     */
    public function home(ArticleHomeRequest $request): Response
    {
        $filtered = $request->filled('search') || $request->filled('category_id');

        // Saat ada filter pencarian/kategori, hero unggulan disembunyikan
        // agar hasil pencarian fokus.
        $featured = $filtered ? null : $this->featuredArticle();

        // 5 artikel terbaru upload (created_at desc) — tanpa unggulan.
        // Saat ada filter pencarian/kategori, section "Terbaru" disembunyikan
        // dan feed menampilkan SEMUA artikel yang cocok (tanpa eksklusi terbaru).
        $recentModels = ! $filtered
            ? Article::query()->published()
                ->with('category:id,nama')
                ->when($featured, fn (Builder $query) => $query->whereKeyNot($featured['id']))
                ->orderByDesc('created_at')
                ->limit(5)
                ->get()
            : collect();
        $recent = $recentModels
            ->map(fn (Article $article): array => $this->articleCard($article))
            ->values()
            ->all();

        // Feed SEMUA artikel (created_at desc) — arsip lengkap. Tidak mengecualikan
        // unggulan/terbaru agar section "Semua Artikel" selalu menampilkan semua
        // artikel terbit (termasuk bila hanya ada 1 artikel). Difilter oleh
        // pencarian/kategori dari query bila ada.
        $articles = $this->homeFeedQuery($request)->paginate(6)->withQueryString();

        // Buku di beranda: saat pencarian aktif → hasil pencarian buku (judul/penulis)
        // agar filter menampilkan artikel DAN buku. Di toko (/buku) hanya buku —
        // sudah ditangani di Catalog. Tanpa pencarian → buku pilihan (iklan).
        $books = $request->filled('search')
            ? $this->searchBooksForHome($request->string('search')->toString())
            : $this->featuredBooksForStorefront();

        return Inertia::render($this->page('Home'), [
            'featured' => $featured,
            'recent' => $recent,
            'articles' => $articles->through(fn (Article $article): array => $this->articleCard($article)),
            'categories' => $this->articleCategoriesForStorefront(),
            'books' => $books,
            'filters' => $request->only(['search', 'category_id']),
            'tagline' => Setting::get('store_tagline') ?: 'Toko Buku',
        ]);
    }

    /**
     * Artikel unggulan untuk beranda — artikel terbit dengan is_featured=true.
     * Fallback: artikel terbit terbaru (created_at desc) bila belum ada yang
     * ditandai. Return null bila tidak ada artikel terbit sama sekali.
     *
     * @return array<string, mixed>|null
     */
    protected function featuredArticle(): ?array
    {
        $article = Article::query()->published()
            ->featured()
            ->orderByDesc('created_at')
            ->with('category:id,nama')
            ->first();

        if ($article === null) {
            $article = Article::query()->published()
                ->with('category:id,nama')
                ->orderByDesc('created_at')
                ->first();
        }

        return $article ? $this->articleCard($article) : null;
    }

    /**
     * Query feed artikel beranda — terbit, urut created_at desc, filter
     * pencarian & kategori (kategori dropdown on-page).
     *
     * @return Builder<Article>
     */
    protected function homeFeedQuery(Request $request): Builder
    {
        return Article::query()->published()
            ->with('category:id,nama')
            ->orderByDesc('created_at')
            ->when($request->filled('search'), function (Builder $query) use ($request): void {
                $search = $request->string('search')->toString();
                $query->where(function (Builder $q) use ($search): void {
                    $q->whereLike('judul', "%{$search}%")
                        ->orWhereLike('ringkasan', "%{$search}%")
                        ->orWhereLike('isi', "%{$search}%");
                });
            })
            ->when($request->filled('category_id'), fn (Builder $query) => $query->where('article_category_id', $request->string('category_id')->toString()));
    }

    /**
     * Muat halaman feed artikel beranda berikutnya (load-more) — JSON paginator.
     */
    public function homeLoadMore(ArticleHomeRequest $request): JsonResponse
    {
        // Konsisten dengan home(): feed arsip lengkap (semua artikel terbit),
        // tanpa mengecualikan unggulan/terbaru, difilter oleh query bila ada.
        $articles = $this->homeFeedQuery($request)
            ->paginate(6);

        return response()->json(
            $articles->through(fn (Article $article): array => $this->articleCard($article)),
        );
    }

    /**
     * Kategori konten dengan jumlah artikel terbit — cache 1 jam, dibersihkan
     * lewat observer Article/ArticleCategory.
     *
     * @return array<int, array{id: string, slug: string, nama: string, articles_count: int}>
     */
    protected function articleCategoriesForStorefront(): array
    {
        return Cache::flexible('storefront.article_categories', [3600, 7200], function (): array {
            return ArticleCategory::query()
                ->withCount(['articles' => fn (Builder $query) => $query->where('is_active', true)->where(function (Builder $q): void {
                    $q->whereNull('published_at')->orWhereDate('published_at', '<=', today());
                })])
                ->get(['id', 'slug', 'nama'])
                ->map(fn (ArticleCategory $category): array => [
                    'id' => $category->id,
                    'slug' => $category->slug,
                    'nama' => $category->nama,
                    'articles_count' => (int) $category->articles_count,
                ])
                ->all();
        });
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
            ->with(['category:id,nama', 'activeEdition'])
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
     * Detail artikel — 404 bila nonaktif atau belum terbit.
     */
    public function articleShow(Article $article): Response
    {
        abort_unless(Article::query()->published()->whereKey($article->getKey())->exists(), 404);

        $article->load('category:id,nama');

        return Inertia::render($this->page('ArticleDetail'), $this->articleDetailProps($article));
    }

    /**
     * Properti Inertia untuk halaman detail artikel — subclass proto-d
     * menambah data (mis. buku unggulan untuk slot iklan) di sini.
     *
     * @return array<string, mixed>
     */
    protected function articleDetailProps(Article $article): array
    {
        return [
            'article' => $this->articleDetail($article),
        ];
    }

    /**
     * Buku unggulan untuk storefront (4 aktif terbaru) dengan harga/promo.
     * Dipakai di beranda & slot iklan artikel proto-d.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function featuredBooksForStorefront(): array
    {
        $books = Book::query()
            ->where('aktif', true)
            ->whereNotNull('harga')
            ->with(['category:id,nama', 'activeEdition'])
            ->orderByDesc('created_at')
            ->limit(10)
            ->get();

        $bookPromos = $this->eagerLoadPromotions($books->pluck('id'));

        return $books->map(
            fn (Book $book) => $this->bookWithPricing($book, $bookPromos[$book->id] ?? null),
        )->values()->all();
    }

    /**
     * Buku untuk beranda saat pencarian aktif — filter judul/penulis.
     * Dipakai agar pencarian di beranda menampilkan artikel DAN buku,
     * sedangkan di /buku (toko) hanya buku.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function searchBooksForHome(string $search, int $limit = 10): array
    {
        $books = Book::query()
            ->where('aktif', true)
            ->whereNotNull('harga')
            ->with(['category:id,nama', 'activeEdition'])
            ->where(function (Builder $query) use ($search): void {
                $query->whereLike('judul', "%{$search}%")
                    ->orWhereLike('penulis', "%{$search}%");
            })
            ->orderBy('judul')
            ->limit($limit)
            ->get();

        $bookIds = $books->pluck('id');
        $bookPromos = $this->eagerLoadPromotions($bookIds);

        return $books->map(
            fn (Book $book) => $this->bookWithPricing($book, $bookPromos[$book->id] ?? null),
        )->values()->all();
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
            ->with('books:id,judul,cover_url,harga,stok,is_preorder')
            ->orderByDesc('id')
            ->get();

        return $promos->map(function (Promotion $promo): array {
            $breakdown = $this->pricing->bundleBreakdown($promo);

            return [
                'id' => $promo->id,
                'promo_name' => $promo->promo_name,
                'discount_percent' => $breakdown['discount_percent'],
                'books' => collect($breakdown['items'])
                    ->map(function (array $item): array {
                        $stok = (int) $item['book']->stok;
                        $isPreorder = (bool) ($item['book']->is_preorder ?? false);
                        $status = $this->resolveStockStatus($stok, $isPreorder);

                        return [
                            'id' => $item['book']->id,
                            'judul' => $item['book']->judul,
                            'cover_url' => $item['book']->cover_url,
                            'price_original' => $item['book']->harga,
                            'unit_price' => $item['unit_price'],
                            'unit_discount' => $item['unit_discount'],
                            'unit_final' => $item['unit_final'],
                            'stok' => $stok,
                            'stock_status' => $status,
                            'stock_label' => $this->stockLabel($status),
                        ];
                    })
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
        $tier = auth()->user()?->status_pelanggan;
        $edition = $book->relationLoaded('activeEdition') ? $book->getRelation('activeEdition') : $book->activeEdition;
        if ($edition instanceof HasOne) {
            $edition = $edition->first();
        }
        $breakdown = $this->pricing->priceBreakdownWithPromo($book, $promo, 1, $tier, $edition);

        $hasDiscount = $breakdown->finalPrice < $breakdown->originalPrice;

        return [
            'id' => $book->id,
            'judul' => $book->judul,
            'cover_url' => $book->cover_url,
            'harga' => $book->harga,
            'price_breakdown' => $hasDiscount ? [
                'original_price' => $breakdown->originalPrice,
                'promo_discount' => $breakdown->promoDiscount,
                'tier_discount' => $breakdown->tierDiscount,
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
     * Ubah model Book jadi array + tambahkan price_breakdown & status stok.
     *
     * Status stok dihitung server-side dari config/pricing.php agar frontend
     * tidak menduplikasi threshold & tidak perlu menampilkan angka stok mentah.
     * Mendukung tier Guru: harga guru (per cetakan) + promo di atasnya, dengan
     * harga asli coret.
     *
     * @return array<string, mixed>
     */
    protected function bookWithPricing(Book $book, ?Promotion $promo): array
    {
        $tier = auth()->user()?->status_pelanggan;
        $edition = null;
        if ($book->relationLoaded('activeEdition')) {
            $edition = $book->getRelation('activeEdition');
        } elseif ($book->relationLoaded('editions')) {
            $edition = $book->getRelation('editions')->firstWhere('is_active', true) ?? $book->getRelation('editions')->first();
        } else {
            $edition = $book->activeEdition()->first();
        }
        $breakdown = $this->pricing->priceBreakdownWithPromo($book, $promo, 1, $tier, $edition);
        $data = $book->toArray();

        $hasDiscount = $breakdown->finalPrice < $breakdown->originalPrice;
        if ($hasDiscount) {
            $data['price_breakdown'] = [
                'original_price' => $breakdown->originalPrice,
                'promo_discount' => $breakdown->promoDiscount,
                'tier_discount' => $breakdown->tierDiscount,
                'final_price' => $breakdown->finalPrice,
                'promo_name' => $breakdown->promoName,
            ];
        }

        $data['stock_status'] = $this->resolveStockStatus((int) ($book->stok ?? 0), (bool) $book->is_preorder);
        $data['stock_label'] = $this->stockLabel($data['stock_status']);

        return $data;
    }

    /**
     * Tentukan status stok customer-facing: tersedia / menipis / habis / preorder.
     * Threshold menipis mengikuti config/pricing.php low_stock_threshold.
     */
    protected function resolveStockStatus(int $stok, bool $isPreorder): string
    {
        if ($isPreorder) {
            return 'preorder';
        }

        if ($stok <= 0) {
            return 'habis';
        }

        if ($stok <= (int) config('pricing.low_stock_threshold', 5)) {
            return 'menipis';
        }

        return 'tersedia';
    }

    protected function stockLabel(string $status): string
    {
        return match ($status) {
            'preorder' => 'Pre-Order',
            'habis' => 'Stok habis',
            'menipis' => 'Stok menipis',
            default => 'Stok tersedia',
        };
    }
}

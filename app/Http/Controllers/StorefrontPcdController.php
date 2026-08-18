<?php

namespace App\Http\Controllers;

use App\Enums\PromotionType;
use App\Http\Requests\Storefront\ArticleHomeRequest;
use App\Models\Article;
use App\Models\Category;
use App\Models\Promotion;
use Illuminate\Http\JsonResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Storefront paralel "Pustaka Cahaya Peradaban" (desain proto-d) — /pcd/**.
 *
 * Seluruh logika query (katalog, detail, promo, tentang) diwarisi dari
 * StorefrontController; kelas ini hanya mengganti komponen Inertia yang
 * dirender dan menambah halaman khas proto-d: home & detail paket.
 */
class StorefrontPcdController extends StorefrontController
{
    protected function page(string $name): string
    {
        return "storefront-pcd/{$name}";
    }

    /**
     * Beranda editorial proto-d: artikel nyata (aktif & terbit) + buku
     * unggulan + promo + paket aktif.
     */
    public function home(ArticleHomeRequest $request): Response
    {
        $filtered = $request->filled('search') || $request->filled('category_id')
            || $request->filled('categories') || $request->filled('month')
            || $request->filled('year');

        $featured = $filtered ? null : $this->featuredArticle();

        $articles = $this->articleFeedQuery($request)
            ->paginate(12)
            ->withQueryString()
            ->through(fn (Article $article): array => $this->articleCard($article));

        return Inertia::render($this->page('Home'), [
            'featured' => $featured,
            'books' => $this->featuredBooksForStorefront(),
            'categories' => Category::orderBy('nama')->get(['id', 'nama']),
            'bundles' => $this->activeBundlesForStorefront(),
            'promos' => $this->activeUnitPromosForStorefront(),
            'articles' => $articles,
            'filters' => [
                'search' => $request->filled('search') ? $request->string('search')->toString() : null,
                'category_id' => $request->filled('category_id') ? $request->string('category_id')->toString() : null,
                'categories' => $request->filled('categories') ? array_values($request->input('categories')) : null,
                'month' => $request->filled('month') ? $request->integer('month') : null,
                'year' => $request->filled('year') ? $request->integer('year') : null,
            ],
            ...$this->articleFacets(),
        ]);
    }

    /**
     * Muat halaman artikel berikutnya (load-more) — respons JSON paginator,
     * konsisten dengan load-more katalog buku proto-d.
     */
    public function articlesLoadMore(ArticleHomeRequest $request): JsonResponse
    {
        $articles = $this->articleFeedQuery($request)
            ->paginate(12)
            ->through(fn (Article $article): array => $this->articleCard($article));

        return response()->json($articles);
    }

    /**
     * Detail artikel proto-d — menambah buku unggulan untuk slot iklan di
     * samping & bawah artikel.
     */
    protected function articleDetailProps(Article $article): array
    {
        return array_merge(parent::articleDetailProps($article), [
            'books' => $this->featuredBooksForStorefront(),
        ]);
    }

    /**
     * Detail paket (bundle) — hanya promo bundle yang sedang aktif.
     */
    public function bundle(string $bundle): Response
    {
        $promo = Promotion::query()
            ->where('promo_type', PromotionType::Bundle->value)
            ->where('is_active', true)
            ->whereDate('start_date', '<=', today())
            ->whereDate('end_date', '>=', today())
            ->with('books:id,judul,penulis,cover_url,harga,stok,is_preorder')
            ->findOrFail($bundle);

        $breakdown = $this->pricing->bundleBreakdown($promo);

        return Inertia::render($this->page('Bundle'), [
            'bundle' => [
                'id' => $promo->id,
                'promo_name' => $promo->promo_name,
                'discount_percent' => $breakdown['discount_percent'],
                'books' => collect($breakdown['items'])
                    ->map(fn (array $item): array => [
                        'id' => $item['book']->id,
                        'judul' => $item['book']->judul,
                        'penulis' => $item['book']->penulis,
                        'cover_url' => $item['book']->cover_url,
                        'price_original' => $item['book']->harga,
                        'unit_price' => $item['unit_price'],
                        'unit_discount' => $item['unit_discount'],
                        'unit_final' => $item['unit_final'],
                        'stok' => (int) $item['book']->stok,
                        'is_preorder' => $item['book']->is_preorder,
                    ])
                    ->values()
                    ->all(),
                'total_original' => $breakdown['total_original'],
                'total_discount' => $breakdown['total_discount'],
                'total_final' => $breakdown['total_final'],
            ],
        ]);
    }
}

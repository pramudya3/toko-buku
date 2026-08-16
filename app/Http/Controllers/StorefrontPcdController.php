<?php

namespace App\Http\Controllers;

use App\Enums\PromotionType;
use App\Models\Article;
use App\Models\Book;
use App\Models\Category;
use App\Models\Promotion;
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
    public function home(): Response
    {
        $books = Book::query()
            ->where('aktif', true)
            ->whereNotNull('harga')
            ->with('category:id,nama')
            ->orderByDesc('created_at')
            ->limit(4)
            ->get();

        $bookPromos = $this->eagerLoadPromotions($books->pluck('id'));

        $articles = Article::published()
            ->with('category:id,nama')
            ->orderByDesc('published_at')
            ->orderByDesc('created_at')
            ->limit(20)
            ->get()
            ->map(fn (Article $article): array => $this->articleCard($article))
            ->values()
            ->all();

        return Inertia::render($this->page('Home'), [
            'books' => $books->map(
                fn (Book $book) => $this->bookWithPricing($book, $bookPromos[$book->id] ?? null),
            ),
            'categories' => Category::orderBy('nama')->get(['id', 'nama']),
            'bundles' => $this->activeBundlesForStorefront(),
            'promos' => $this->activeUnitPromosForStorefront(),
            'articles' => $articles,
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

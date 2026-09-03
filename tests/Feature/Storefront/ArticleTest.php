<?php

use App\Models\Article;
use App\Models\ArticleCategory;
use App\Models\Book;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * Menu Artikel di storefront — beranda editorial ("/") & detail (/artikel/{slug}).
 */
it('shows only published articles in the home feed and recent section', function (): void {
    Article::factory()->create(['judul' => 'Artikel Terbit', 'published_at' => today()->toDateString()]);
    Article::factory()->create(['judul' => 'Artikel Draft', 'is_active' => false]);
    Article::factory()->create(['judul' => 'Artikel Masa Depan', 'published_at' => today()->addDay()->toDateString()]);

    $this->get(route('home'))
        ->assertOk()
        ->assertSee('Artikel Terbit')
        ->assertDontSee('Artikel Draft')
        ->assertDontSee('Artikel Masa Depan');
});

it('shows an article detail page by slug', function (): void {
    $article = Article::factory()->create([
        'judul' => 'Membaca di Tengah Gempuran Layar',
        'published_at' => '2026-08-16',
    ]);

    $this->get(route('articles.show', $article->slug))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('storefront/ArticleDetail')
            ->where('article.judul', 'Membaca di Tengah Gempuran Layar'));
});

it('404s for inactive or unpublished articles', function (): void {
    $inactive = Article::factory()->draft()->create(['judul' => 'Tersembunyi']);
    $future = Article::factory()->create(['published_at' => today()->addDay()->toDateString()]);

    $this->get(route('articles.show', $inactive->slug))->assertNotFound();
    $this->get(route('articles.show', $future->slug))->assertNotFound();
});

it('catalog page still works at /buku', function (): void {
    Book::factory()->create(['judul' => 'Buku Katalog', 'aktif' => true]);

    $this->get(route('books.catalog'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('storefront/Catalog')
            ->has('books'));
});

// ── Beranda editorial "/" ──

it('renders featured, recent and paginated feed on the editorial home', function (): void {
    Article::factory()->count(13)->create();

    $this->get(route('home'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('storefront/Home')
            ->has('featured')
            ->count('recent', 5)
            ->has('articles.data')
            ->has('categories'));
});

it('uses the is_featured article as the home hero', function (): void {
    $featured = Article::factory()->create([
        'judul' => 'Unggulan Pilihan',
        'is_featured' => true,
        'published_at' => today()->toDateString(),
    ]);
    Article::factory()->count(3)->create();

    $this->get(route('home'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('featured.id', $featured->id)
            ->where('featured.judul', 'Unggulan Pilihan'));
});

it('falls back to the newest article as featured when none is flagged', function (): void {
    $newest = Article::factory()->create([
        'judul' => 'Terbaru Tanpa Flag',
        'published_at' => today()->toDateString(),
    ]);

    $this->get(route('home'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('featured.id', $newest->id));
});

it('excludes unpublished articles from the editorial home', function (): void {
    Article::factory()->create(['judul' => 'Artikel Tampil', 'published_at' => today()->toDateString()]);
    Article::factory()->draft()->create(['judul' => 'Artikel Draft']);

    $this->get(route('home'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('storefront/Home')
            ->where('featured.judul', 'Artikel Tampil'));
});

it('orders recent section by created_at desc and limits to five', function (): void {
    $a = Article::factory()->create(['judul' => 'Pertama', 'created_at' => now()->subDays(5)]);
    $b = Article::factory()->create(['judul' => 'Kedua', 'created_at' => now()->subDays(4)]);
    $c = Article::factory()->create(['judul' => 'Ketiga', 'created_at' => now()->subDays(3)]);
    $d = Article::factory()->create(['judul' => 'Keempat', 'created_at' => now()->subDays(2)]);
    $e = Article::factory()->create(['judul' => 'Kelima', 'created_at' => now()->subDay()]);
    Article::factory()->create(['judul' => 'Keenam', 'created_at' => now()]);
    // featured = newest ("Keenam"), recent = 5 sisanya diurut created_at desc.
    $this->get(route('home'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->count('recent', 5)
            ->where('recent.0.judul', $e->judul)
            ->where('recent.4.judul', $a->judul));
});

it('filters the home feed by category via the dropdown', function (): void {
    $cat = ArticleCategory::factory()->create(['nama' => 'Akidah', 'slug' => 'akidah']);
    $other = ArticleCategory::factory()->create(['nama' => 'Akhlak', 'slug' => 'akhlak']);
    Article::factory()->create(['judul' => 'Artikel Akidah', 'article_category_id' => $cat->id, 'published_at' => today()->toDateString()]);
    Article::factory()->create(['judul' => 'Artikel Akhlak', 'article_category_id' => $other->id, 'published_at' => today()->toDateString()]);

    $this->get(route('home', ['category_id' => $cat->id]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('filters.category_id', $cat->id)
            ->where('articles.total', 1)
            ->where('articles.data.0.judul', 'Artikel Akidah'));
});

it('loads more home feed articles via the JSON endpoint with stable params', function (): void {
    // created_at berbeda tiap artikel agar paginasi (orderBy created_at desc)
    // deterministik dan count per halaman stabil.
    foreach (range(1, 14) as $i) {
        Article::factory()->create([
            'created_at' => now()->subMinutes($i),
        ]);
    }

    // Feed = semua artikel terbit (tanpa eksklusi unggulan/terbaru), 6/halaman.
    // Halaman-1 = 6, sisa halaman-2 = 14 - 6 = 8.
    // Feed = semua artikel terbit (tanpa eksklusi unggulan/terbaru), 6/halaman.
    // 14 artikel -> 3 halaman (6+6+2). Halaman-2 = 6 item.
    $this->get(route('articles.load-more', ['page' => 2]))
        ->assertOk()
        ->assertJsonFragment(['current_page' => 2, 'per_page' => 6])
        ->assertJsonPath('data', function (mixed $data): bool {
            return is_array($data) && count($data) === 6;
        })
        ->assertJsonStructure([
            'data' => ['*' => ['id', 'judul', 'slug']],
        ]);
});

it('404s for the removed legacy /artikel listing route', function (): void {
    Article::factory()->create(['judul' => 'Artikel', 'published_at' => today()->toDateString()]);

    $this->get('/artikel')->assertNotFound();
});

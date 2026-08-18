<?php

use App\Models\Article;
use App\Models\ArticleCategory;
use App\Models\Book;
use App\Models\Category;
use App\Models\Order;
use App\Models\Promotion;
use App\Models\Setting;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * Storefront paralel proto-d (/pcd/**) — memakai backend yang sama dengan
 * storefront utama, hanya komponen Inertia yang berbeda.
 */
beforeEach(function (): void {
    $this->customer = User::factory()->create();
    Setting::set('origin_postal_code', '65144');
});

it('renders the pcd home with real books, bundles and promos', function (): void {
    $category = Category::factory()->create(['nama' => 'Fiksi']);
    Book::factory()->create(['judul' => 'Buku Unggulan', 'category_id' => $category->id, 'aktif' => true]);
    $bookA = Book::factory()->create(['judul' => 'Buku Paket A', 'harga' => 100000, 'aktif' => true]);
    $bookB = Book::factory()->create(['judul' => 'Buku Paket B', 'harga' => 50000, 'aktif' => true]);
    $promo = Promotion::factory()->bundle(percent: 15)->create();
    $promo->books()->sync([$bookA->id, $bookB->id]);

    $this->get(route('pcd.home'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('storefront-pcd/Home')
            ->has('books')
            ->has('bundles', 1)
            ->has('categories'));
});

it('renders the pcd catalog with category filter and sort', function (): void {
    $category = Category::factory()->create(['nama' => 'Religi']);
    Book::factory()->create(['judul' => 'Buku Mahal', 'harga' => 200000, 'aktif' => true, 'category_id' => $category->id]);
    Book::factory()->create(['judul' => 'Buku Murah', 'harga' => 10000, 'aktif' => true, 'category_id' => $category->id]);

    $this->get(route('pcd.books.catalog', ['category_id' => $category->id, 'sort' => 'cheapest']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('storefront-pcd/Catalog')
            ->has('books.data', 2));

    $props = inertiaProps($this->get(route('pcd.books.catalog', ['sort' => 'cheapest'])));
    $juduls = collect($props['books']['data'])->pluck('judul')->all();

    expect(array_search('Buku Murah', $juduls, true))
        ->toBeLessThan(array_search('Buku Mahal', $juduls, true));
});

it('shows the pcd book detail and 404s for inactive books', function (): void {
    $book = Book::factory()->withStock(malang: 5)->create(['judul' => 'Buku Pcd', 'aktif' => true]);
    $inactive = Book::factory()->create(['judul' => 'Buku Mati', 'aktif' => false]);

    $this->get(route('pcd.books.show', $book->id.'-buku-pcd'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('storefront-pcd/BookDetail')
            ->where('book.judul', 'Buku Pcd'));

    $this->get(route('pcd.books.show', $inactive->id.'-buku-mati'))->assertNotFound();
});

it('renders the pcd bundle page for an active bundle', function (): void {
    $bookA = Book::factory()->create(['judul' => 'Buku Paket A', 'harga' => 100000, 'aktif' => true]);
    $bookB = Book::factory()->create(['judul' => 'Buku Paket B', 'harga' => 50000, 'aktif' => true]);
    $promo = Promotion::factory()->bundle(percent: 20)->create();
    $promo->books()->sync([$bookA->id, $bookB->id]);

    $this->get(route('pcd.bundles.show', $promo->id))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('storefront-pcd/Bundle')
            ->where('bundle.id', $promo->id)
            ->where('bundle.total_final', 120000));
});

it('404s the pcd bundle page for expired bundles', function (): void {
    $book = Book::factory()->create(['judul' => 'Buku Paket', 'harga' => 50000, 'aktif' => true]);
    $promo = Promotion::factory()->bundle(percent: 10)->create([
        'start_date' => now()->subDays(10)->toDateString(),
        'end_date' => now()->subDay()->toDateString(),
    ]);
    $promo->books()->sync([$book->id]);

    $this->get(route('pcd.bundles.show', $promo->id))->assertNotFound();
});

it('renders the pcd about page from settings', function (): void {
    Setting::set('store_nama_lembaga', 'Pustaka Cahaya Peradaban');
    Setting::set('store_tagline', 'Membaca, menulis, menerbitkan.');

    $this->get(route('pcd.about'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('storefront-pcd/About')
            ->where('nama_lembaga', 'Pustaka Cahaya Peradaban'));
});

it('requires login for the pcd checkout', function (): void {
    $this->get(route('pcd.checkout.index'))->assertRedirect(route('login'));
});

it('runs the pcd checkout flow and lands on the pcd success page', function (): void {
    $book = Book::factory()->withStock(malang: 10)->create(['aktif' => true, 'harga' => 50000, 'berat_gr' => 500]);

    $this->post(route('cart.add'), ['book_id' => $book->id, 'qty' => 1])->assertRedirect();

    // Halaman checkout memakai komponen proto-d.
    $this->actingAs($this->customer)->get(route('pcd.checkout.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('storefront-pcd/Checkout'));

    // Submit → order dibuat & diarahkan ke halaman sukses proto-d.
    $response = $this->actingAs($this->customer)->post(route('pcd.checkout.store'), [
        'nama_pembeli' => 'Pembeli Pcd',
        'whatsapp_pembeli' => '08123456789',
        'metode_bayar' => 'transfer',
        'metode_pengambilan' => 'ambil',
        'selected_groups' => ['regular'],
    ]);

    $response->assertRedirect();
    expect($response->headers->get('Location'))->toContain('/pcd/checkout/sukses');

    $order = Order::latest('id')->firstOrFail();

    expect($order->nama_pembeli)->toBe('Pembeli Pcd')
        ->and($order->items()->count())->toBe(1);

    $this->actingAs($this->customer)
        ->get(route('pcd.checkout.success', ['no_order' => $order->no_order]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('storefront-pcd/CheckoutSuccess'));
});

it('keeps the legacy storefront pages rendering legacy components', function (): void {
    Book::factory()->create(['judul' => 'Buku Lama', 'aktif' => true]);

    $this->get(route('books.catalog'))
        ->assertInertia(fn (Assert $page) => $page->component('storefront/Catalog'));
});

it('renders the article editorial home at the "/" route', function (): void {
    Article::factory()->create(['judul' => 'Artikel Beranda', 'published_at' => '2026-08-16']);

    $this->get(route('home'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('storefront/Home')
            ->has('recent')
            ->has('categories'));
});

// ── Filter artikel proto-d: pencarian konten, kategori jamak (OR), bulan & tahun ──

test('pcd home searches article content (isi), not just title or summary', function (): void {
    Article::factory()->create([
        'judul' => 'Catatan Redaksi',
        'isi' => '<p>Membahas istilah langka: skolastik.</p>',
        'published_at' => today()->toDateString(),
    ]);
    Article::factory()->create([
        'judul' => 'Wawancara Penulis',
        'isi' => '<p>Cerita tentang proses kreatif.</p>',
        'published_at' => today()->toDateString(),
    ]);

    $this->get(route('pcd.home', ['search' => 'skolastik']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('storefront-pcd/Home')
            ->where('articles.total', 1)
            ->where('articles.data.0.judul', 'Catatan Redaksi'));

    $this->get(route('pcd.home'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('storefront-pcd/Home')
            ->where('articles.total', 2));
});

test('pcd home filters by multiple categories with OR logic', function (): void {
    $resensi = ArticleCategory::factory()->create(['nama' => 'Resensi']);
    $esai = ArticleCategory::factory()->create(['nama' => 'Esai']);
    $berita = ArticleCategory::factory()->create(['nama' => 'Berita']);

    Article::factory()->create(['judul' => 'Resensi Novel', 'article_category_id' => $resensi->id, 'published_at' => today()->toDateString()]);
    Article::factory()->create(['judul' => 'Esai Budaya', 'article_category_id' => $esai->id, 'published_at' => today()->toDateString()]);
    Article::factory()->create(['judul' => 'Berita Sekolah', 'article_category_id' => $berita->id, 'published_at' => today()->toDateString()]);

    // Dua kategori sekaligus → keduanya cocok (OR).
    $this->get(route('pcd.home', ['categories' => [$resensi->id, $esai->id]]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('storefront-pcd/Home')
            ->where('articles.total', 2)
            ->where('filters.categories', [$resensi->id, $esai->id]));

    // Satu kategori → hanya anggota kategori itu.
    $this->get(route('pcd.home', ['categories' => [$resensi->id]]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('storefront-pcd/Home')
            ->where('articles.total', 1)
            ->where('articles.data.0.judul', 'Resensi Novel'));
});

test('pcd home combines category, month, and year filters', function (): void {
    $kategori = ArticleCategory::factory()->create(['nama' => 'Esai']);

    Article::factory()->create([
        'judul' => 'Esai Juni 2025',
        'article_category_id' => $kategori->id,
        'published_at' => '2025-06-10',
    ]);
    Article::factory()->create([
        'judul' => 'Esai Juli 2025',
        'article_category_id' => $kategori->id,
        'published_at' => '2025-07-10',
    ]);
    Article::factory()->create([
        'judul' => 'Esai Juni 2026',
        'article_category_id' => $kategori->id,
        'published_at' => '2026-06-10',
    ]);

    $this->get(route('pcd.home', [
        'categories' => [$kategori->id],
        'month' => 6,
        'year' => 2025,
    ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('storefront-pcd/Home')
            ->where('articles.total', 1)
            ->where('articles.data.0.judul', 'Esai Juni 2025')
            ->where('filters.month', 6)
            ->where('filters.year', 2025));
});

test('pcd home returns article categories and date facets for the sidebar', function (): void {
    $kategori = ArticleCategory::factory()->create(['nama' => 'Resensi']);
    Article::factory()->create([
        'article_category_id' => $kategori->id,
        'published_at' => '2026-08-16',
    ]);
    Article::factory()->create([
        'article_category_id' => $kategori->id,
        'published_at' => '2026-08-10',
    ]);
    Article::factory()->create([
        'article_category_id' => null,
        'published_at' => '2025-12-01',
    ]);

    $this->get(route('pcd.home'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('storefront-pcd/Home')
            ->has('articleCategories', 1)
            ->where('articleCategories.0.articles_count', 2)
            ->has('dateFacets', 2)
            ->where('dateFacets.0.year', 2026)
            ->where('dateFacets.0.month', 8)
            ->where('dateFacets.0.count', 2));
});

test('pcd article feed paginates and load-more preserves filters', function (): void {
    Article::factory()->count(15)->create(['published_at' => today()->toDateString()]);

    $props = inertiaProps($this->get(route('pcd.home'))->assertOk());

    expect($props['articles']['per_page'])->toBe(12)
        ->and($props['articles']['current_page'])->toBe(1)
        ->and($props['articles']['total'])->toBe(15)
        ->and($props['articles']['last_page'])->toBe(2)
        ->and($props['articles']['data'])->toHaveCount(12);

    $json = $this->get(route('pcd.articles.load-more', ['page' => 2]))
        ->assertOk()
        ->json();

    expect($json['current_page'])->toBe(2)
        ->and($json['data'])->toHaveCount(3);
});

test('pcd article facets are cached and invalidated on article changes', function (): void {
    Cache::forget('storefront.article_facets');

    Article::factory()->create(['published_at' => today()->toDateString()]);

    $this->get(route('pcd.home'))->assertOk();
    expect(Cache::has('storefront.article_facets'))->toBeTrue();

    // Perubahan artikel membatalkan cache facet.
    $article = Article::factory()->create(['published_at' => today()->toDateString()]);
    $article->update(['judul' => 'Judul Baru']);

    expect(Cache::has('storefront.article_facets'))->toBeFalse();
});

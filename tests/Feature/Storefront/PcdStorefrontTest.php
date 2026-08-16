<?php

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

    $this->get(route('home'))
        ->assertInertia(fn (Assert $page) => $page->component('storefront/Catalog'));
});

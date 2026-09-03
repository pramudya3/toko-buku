<?php

use App\Enums\OrderStatus;
use App\Models\BankAccount;
use App\Models\Book;
use App\Models\BookEdition;
use App\Models\BookImage;
use App\Models\Category;
use App\Models\District;
use App\Models\Order;
use App\Models\Promotion;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    $this->customer = User::factory()->create();

    // Origin toko (cek ongkir/booking membaca dari tabel settings saja).
    Setting::set('origin_postal_code', '65144');
});

it('lists only active books in the catalog', function (): void {
    Book::factory()->create(['judul' => 'Buku Aktif', 'aktif' => true]);
    Book::factory()->create(['judul' => 'Buku Nonaktif', 'aktif' => false]);

    $this->get(route('books.catalog'))
        ->assertOk()
        ->assertSee('Buku Aktif')
        ->assertDontSee('Buku Nonaktif');
});

it('includes sellable stock per edition in the book detail', function (): void {
    $book = Book::factory()->withStock(malang: 5)->create(['judul' => 'Buku Stok Cetakan']);
    $edition = $book->editions()->orderBy('cetakan_ke')->first();

    $props = inertiaProps($this->get(route('books.show', $book->id.'-buku-stok-cetakan')));

    expect($props['book']['editions'][0]['id'])->toBe($edition->id)
        ->and($props['book']['editions'][0]['stok_sellable'])->toBe(5);
});

it('includes stock availability in bundle books', function (): void {
    $bookA = Book::factory()->withStock(malang: 2)->create(['judul' => 'Buku Paket A', 'harga' => 100000, 'aktif' => true]);
    $bookB = Book::factory()->create(['judul' => 'Buku Paket B', 'harga' => 50000, 'aktif' => true]); // stok 0

    $promo = Promotion::factory()->bundle(percent: 15)->create();
    $promo->books()->sync([$bookA->id, $bookB->id]);

    $props = inertiaProps($this->get(route('books.catalog')));

    $stoks = collect($props['bundles'][0]['books'])->pluck('stok');

    expect($stoks)->toContain(2)->toContain(0);
});

it('filters catalog by search and category', function (): void {
    $category = Category::factory()->create(['nama' => 'Fiksi']);
    Book::factory()->create(['judul' => 'Novel Senja', 'category_id' => $category->id, 'aktif' => true]);
    Book::factory()->create(['judul' => 'Buku Masak', 'aktif' => true]);

    $this->get(route('books.catalog', ['search' => 'senja']))
        ->assertOk()
        ->assertSee('Novel Senja')
        ->assertDontSee('Buku Masak');
});

it('lists in-stock books before out-of-stock books by default', function (): void {
    Book::factory()->create(['judul' => 'Buku Habis', 'stok' => 0, 'aktif' => true]);
    Book::factory()->create(['judul' => 'Buku Tersedia', 'stok' => 5, 'aktif' => true]);

    $props = inertiaProps($this->get(route('books.catalog')));

    $juduls = collect($props['books']['data'])->pluck('judul')->all();

    expect(array_search('Buku Tersedia', $juduls, true))
        ->toBeLessThan(array_search('Buku Habis', $juduls, true));
});

it('filters catalog to in-stock books only', function (): void {
    Book::factory()->create(['judul' => 'Buku Habis', 'stok' => 0, 'aktif' => true]);
    Book::factory()->create(['judul' => 'Buku Tersedia', 'stok' => 5, 'aktif' => true]);

    $props = inertiaProps($this->get(route('books.catalog', ['stok' => 'ready'])));

    expect(collect($props['books']['data'])->pluck('judul'))->toContain('Buku Tersedia')
        ->not->toContain('Buku Habis');
});

it('filters catalog to out-of-stock books only', function (): void {
    Book::factory()->create(['judul' => 'Buku Habis', 'stok' => 0, 'aktif' => true]);
    Book::factory()->create(['judul' => 'Buku Tersedia', 'stok' => 5, 'aktif' => true]);

    $props = inertiaProps($this->get(route('books.catalog', ['stok' => 'empty'])));

    expect(collect($props['books']['data'])->pluck('judul'))->toContain('Buku Habis')
        ->not->toContain('Buku Tersedia');
});

it('shows active bundle promotions in the catalog (Paket Hemat)', function (): void {
    $bookA = Book::factory()->create(['judul' => 'Buku Paket A', 'harga' => 100000, 'aktif' => true]);
    $bookB = Book::factory()->create(['judul' => 'Buku Paket B', 'harga' => 50000, 'aktif' => true]);

    $promo = Promotion::factory()->bundle(percent: 15)->create();
    $promo->books()->sync([$bookA->id, $bookB->id]);

    $props = inertiaProps($this->get(route('books.catalog')));

    expect($props['bundles'])->toHaveCount(1)
        ->and($props['bundles'][0]['promo_name'])->toBe($promo->promo_name)
        ->and($props['bundles'][0]['discount_percent'])->toBe(15)
        ->and($props['bundles'][0]['total_original'])->toBe(150000)
        ->and($props['bundles'][0]['total_final'])->toBe(127500)
        ->and($props['bundles'][0]['books'])->toHaveCount(2)
        ->and($props['bundles'][0]['books'][0]['unit_final'])->toBe(85000)
        ->and($props['bundles'][0]['books'][1]['unit_final'])->toBe(42500);
});

it('lists active unit promotions in the catalog (Promo section)', function (): void {
    $book = Book::factory()->create(['judul' => 'Buku Promo A', 'harga' => 100000, 'aktif' => true]);
    $promo = Promotion::factory()->percentage(percent: 20)->create(['promo_name' => 'Diskon Akhir Tahun']);
    $promo->books()->attach($book);

    $props = inertiaProps($this->get(route('books.catalog')));

    expect($props['promos'])->toHaveCount(1)
        ->and($props['promos'][0]['promo_name'])->toBe('Diskon Akhir Tahun')
        ->and($props['promos'][0]['promo_type'])->toBe('percentage')
        ->and($props['promos'][0]['discount_percentage'])->toBe(20)
        ->and($props['promos'][0]['is_global'])->toBeFalse()
        ->and($props['promos'][0]['books'])->toHaveCount(1)
        ->and($props['promos'][0]['books'][0]['judul'])->toBe('Buku Promo A');
});

it('excludes inactive and expired unit promotions from the catalog', function (): void {
    Promotion::factory()->percentage()->inactive()->create(['promo_name' => 'Promo Nonaktif']);
    Promotion::factory()->percentage()->expired()->create(['promo_name' => 'Promo Kadaluarsa']);
    Promotion::factory()->percentage()->upcoming()->create(['promo_name' => 'Promo Belum Mulai']);

    $props = inertiaProps($this->get(route('books.catalog')));

    expect(collect($props['promos'])->pluck('promo_name'))->not->toContain('Promo Nonaktif')
        ->not->toContain('Promo Kadaluarsa')
        ->not->toContain('Promo Belum Mulai');
});

it('flags global unit promotions in the catalog', function (): void {
    Promotion::factory()->fixed(value: 99000)->create(['promo_name' => 'Promo Global']);

    $props = inertiaProps($this->get(route('books.catalog')));

    expect($props['promos'])->toHaveCount(1)
        ->and($props['promos'][0]['is_global'])->toBeTrue()
        ->and($props['promos'][0]['promo_type'])->toBe('fixed')
        ->and($props['promos'][0]['promo_value'])->toBe(99000);
});

it('renders the promo page with bundles, unit promos and final prices', function (): void {
    $bookA = Book::factory()->create(['judul' => 'Buku Promo A', 'harga' => 100000, 'aktif' => true]);
    $bookB = Book::factory()->create(['judul' => 'Buku Promo B', 'harga' => 50000, 'aktif' => true]);

    $bundle = Promotion::factory()->bundle(percent: 15)->create(['promo_name' => 'Paket Hemat Page']);
    $bundle->books()->sync([$bookA->id, $bookB->id]);

    $unit = Promotion::factory()->percentage(percent: 20)->create(['promo_name' => 'Diskon 20 Persen']);
    $unit->books()->attach($bookA);

    $props = inertiaProps($this->get(route('books.promo', ['search' => 'paket'])));

    expect($props['bundles'])->toHaveCount(1)
        ->and($props['bundles'][0]['promo_name'])->toBe('Paket Hemat Page')
        ->and($props['promos'])->toHaveCount(1)
        ->and($props['promos'][0]['promo_name'])->toBe('Diskon 20 Persen')
        ->and($props['promos'][0]['books'][0]['price_breakdown']['original_price'])->toBe(100000)
        ->and($props['promos'][0]['books'][0]['price_breakdown']['final_price'])->toBe(80000)
        ->and($props['promos'][0]['books'][0]['harga'])->toBe(100000)
        ->and($props['filters']['search'])->toBe('paket');
});

it('shows the book detail via id+judul URL', function (): void {
    $bookA = Book::factory()->create(['judul' => 'Bumi Manusia', 'aktif' => true]);
    $bookB = Book::factory()->create(['judul' => 'Bumi Manusia', 'aktif' => true]);

    // URL publik: /buku/{uuid}-{judul} — lookup tetap uuid, judul hiasan.
    $this->get(route('books.show', $bookA->id.'-bumi-manusia'))
        ->assertOk()
        ->assertSee('Bumi Manusia');

    // UUID telanjang juga tetap valid.
    $this->get(route('books.show', $bookA->id))
        ->assertOk()
        ->assertSee('Bumi Manusia');

    // Judul sama tidak ambigu — masing-masing resolve ke bukunya sendiri.
    $this->get(route('books.show', $bookB->id.'-bumi-manusia'))
        ->assertOk()
        ->assertSee('Bumi Manusia');
});

it('shows the gallery images on the book detail page', function (): void {
    $book = Book::factory()->create(['judul' => 'Buku Galeri', 'aktif' => true]);
    BookImage::factory()->create(['book_id' => $book->id, 'image_url' => 'https://cdn.example.com/foto1.jpg', 'urutan' => 1]);
    BookImage::factory()->create(['book_id' => $book->id, 'image_url' => 'https://cdn.example.com/foto2.jpg', 'urutan' => 2]);

    $props = inertiaProps($this->get(route('books.show', $book))->assertOk());

    expect(collect($props['book']['images'])->pluck('image_url')->all())
        ->toBe(['https://cdn.example.com/foto1.jpg', 'https://cdn.example.com/foto2.jpg']);
});

it('shares cart count using uuid book ids without integer casting', function (): void {
    $book = Book::factory()->create(['judul' => 'Buku Cart', 'aktif' => true]);
    $edition = $book->editions()->firstOrCreate(
        ['cetakan_ke' => 1],
        ['harga_beli' => 1000, 'harga_jual' => 5000],
    );

    // Kunci keranjang: {uuid}:{edition_id} — uuid v7 diawali angka (019f...),
    // cast (int) akan merusaknya (mis. jadi 19) → query whereIn gagal di pgsql.
    $props = inertiaProps($this->withSession([
        'cart' => [$book->id.':'.$edition->id => ['qty' => 2]],
    ])->get(route('home'))->assertSuccessful());

    expect($props['cartCount'])->toBe(1);
});

it('ignores stale integer cart keys from the pre-uuid era', function (): void {
    $props = inertiaProps($this->withSession([
        'cart' => ['19:5' => ['qty' => 3]],
    ])->get(route('home'))->assertSuccessful());

    expect($props['cartCount'])->toBe(0);
});

it('shows book detail with specs', function (): void {
    $book = Book::factory()->create([
        'judul' => 'Buku Detail',
        'sinopsis' => 'Sinopsis panjang',
        'aktif' => true,
    ]);

    $this->get(route('books.show', $book))
        ->assertOk()
        ->assertSee('Buku Detail')
        ->assertSee('Sinopsis panjang');
});

it('applies unit promo price even when a bundle promo is active (BR-02)', function (): void {
    $book = Book::factory()->create(['judul' => 'Buku Promo', 'harga' => 100000, 'aktif' => true]);

    // Bundle promo berakhir lebih lama — dulu menang & membuat harga tidak berubah.
    $bundle = Promotion::factory()->bundle(percent: 15)->create([
        'start_date' => now()->subDay()->toDateString(),
        'end_date' => now()->addDays(30)->toDateString(),
    ]);
    $bundle->books()->attach($book);

    $book->promotions()->attach(Promotion::factory()->fixed(70000)->create());

    $this->get(route('books.catalog'))
        ->assertOk()
        ->assertSee('70000') // final_price dari promo fixed
        ->assertSee('Buku Promo');
});

it('adds multiple books to cart via bulk endpoint', function (): void {
    $bookA = Book::factory()->withStock(malang: 3)->create(['aktif' => true]);
    $bookB = Book::factory()->withStock(malang: 3)->create(['aktif' => true]);

    $this->post(route('cart.add-bulk'), ['book_ids' => [$bookA->id, $bookB->id]])
        ->assertRedirect(route('checkout.index'));

    expect(session('cart'))->toBe([
        (string) $bookA->id => ['qty' => 1, 'edition_id' => null],
        (string) $bookB->id => ['qty' => 1, 'edition_id' => null],
    ]);
});

it('skips out-of-stock books in bulk add', function (): void {
    $bookA = Book::factory()->withStock(malang: 2)->create(['aktif' => true]);
    $bookB = Book::factory()->create(['aktif' => true, 'stok' => 0]);

    $this->post(route('cart.add-bulk'), ['book_ids' => [$bookA->id, $bookB->id]])
        ->assertRedirect(route('checkout.index'));

    expect(session('cart'))->toBe([(string) $bookA->id => ['qty' => 1, 'edition_id' => null]]);
});

it('caps cart quantity at available stock when adding to cart', function (): void {
    $book = Book::factory()->withStock(malang: 3)->create(['aktif' => true]);

    $this->post(route('cart.add'), ['book_id' => $book->id, 'qty' => 99])
        ->assertRedirect();

    expect(session('cart'))->toBe([(string) $book->id => ['qty' => 3, 'edition_id' => null]]);
});

it('keeps separate cart lines per edition and prices checkout by edition', function (): void {
    $book = Book::factory()->withStock(malang: 10)->create(['aktif' => true, 'harga' => 40000]);

    // Factory sudah membuat cetakan ke-1 — pakai itu sebagai edition1.
    $edition1 = $book->editions()->firstOrFail();
    $edition2 = $book->editions()->create([
        'cetakan_ke' => 2, 'harga_beli' => 33000, 'harga_jual' => 43000, 'is_active' => false,
    ]);

    // Cetakan ke-2 juga perlu stok agar lolos validasi checkout.
    $edition2->stocks()->create([
        'warehouse_id' => $book->inventoryStocks()->firstOrFail()->warehouse_id,
        'qty' => 5,
    ]);

    // Tambah cetakan ke-1 dan ke-2 — dua baris terpisah di keranjang.
    $this->post(route('cart.add'), ['book_id' => $book->id, 'book_edition_id' => $edition1->id, 'qty' => 1])
        ->assertRedirect();
    $this->post(route('cart.add'), ['book_id' => $book->id, 'book_edition_id' => $edition2->id, 'qty' => 1])
        ->assertRedirect();

    expect(session('cart'))->toBe([
        $book->id.':'.$edition1->id => ['qty' => 1, 'edition_id' => $edition1->id],
        $book->id.':'.$edition2->id => ['qty' => 1, 'edition_id' => $edition2->id],
    ]);

    // Checkout dengan kedua cetakan: harga per baris mengikuti cetakan.
    $this->actingAs($this->customer)->post(route('checkout.store'), [
        'nama_pembeli' => 'Budi',
        'whatsapp_pembeli' => '08123456789',
        'metode_bayar' => 'transfer',
        'metode_pengambilan' => 'ambil',
        'selected_groups' => ['regular'],
    ])->assertRedirect();

    $order = Order::latest('id')->firstOrFail();

    expect($order->items()->count())->toBe(2)
        ->and($order->items()->where('book_edition_id', $edition1->id)->first()->price_final)->toBe(40000)
        ->and($order->items()->where('book_edition_id', $edition2->id)->first()->price_final)->toBe(43000)
        ->and($order->items()->where('book_edition_id', $edition1->id)->first()->edition_snapshot)->toBe('Cetakan ke-1')
        ->and($order->total)->toBe(83000);
});

it('rejects adding an edition that belongs to another book', function (): void {
    $bookA = Book::factory()->withStock(malang: 5)->create(['aktif' => true]);
    $bookB = Book::factory()->withStock(malang: 5)->create(['aktif' => true]);

    $this->post(route('cart.add'), [
        'book_id' => $bookA->id,
        'book_edition_id' => $bookB->editions()->first()->id,
        'qty' => 1,
    ])->assertSessionHasErrors('book_edition_id');
});

it('caps cart quantity at available stock when updating qty', function (): void {
    $book = Book::factory()->withStock(malang: 3)->create(['aktif' => true]);
    session(['cart' => [$book->id => 1]]);

    $this->post(route('cart.qty', $book), ['qty' => 99])->assertRedirect();

    expect(session('cart'))->toBe([(string) $book->id => ['qty' => 3, 'edition_id' => null]]);
});

it('returns 404 for inactive book detail', function (): void {
    $book = Book::factory()->create(['aktif' => false]);

    $this->get(route('books.show', $book))->assertNotFound();
});

it('includes the full address (incl. kelurahan) of the logged-in user on checkout', function (): void {
    $user = User::factory()->create([
        'name' => 'Pembeli Lengkap',
        'provinsi' => 'JAWA TIMUR',
        'kabupaten_kota' => 'KOTA MALANG',
        'kecamatan' => 'KLOJEN',
        'kelurahan' => 'BARENG',
        'village_code' => '3573010001',
        'kode_pos' => '65116',
        'alamat' => 'Jl. Bareng Raya 12',
    ]);
    $book = Book::factory()->withStock(malang: 10)->create(['judul' => 'Buku Checkout', 'aktif' => true]);

    session(['cart' => [$book->id => 1]]);

    $props = inertiaProps($this->actingAs($user)->get(route('checkout.index')));

    expect($props['user']['kelurahan'])->toBe('BARENG')
        ->and($props['user']['village_code'])->toBe('3573010001')
        ->and($props['user']['provinsi'])->toBe('JAWA TIMUR')
        ->and($props['user']['kode_pos'])->toBe('65116');
});

it('groups checkout items by bundle promo with per-group pricing', function (): void {
    $bookA = Book::factory()->withStock(malang: 10)->create(['judul' => 'Paket A', 'harga' => 100000, 'aktif' => true]);
    $bookB = Book::factory()->withStock(malang: 10)->create(['judul' => 'Paket B', 'harga' => 50000, 'aktif' => true]);
    $bookC = Book::factory()->withStock(malang: 10)->create(['judul' => 'Bebas C', 'harga' => 20000, 'aktif' => true]);

    $promo = Promotion::factory()->bundle(percent: 15)->create();
    $promo->books()->sync([$bookA->id, $bookB->id]);

    // Paket A+B + buku bebas C (bukan bundle).
    session(['cart' => [$bookA->id => 1, $bookB->id => 1, $bookC->id => 1]]);

    $props = inertiaProps($this->actingAs($this->customer)->get(route('checkout.index')));

    expect($props['groups'])->toHaveCount(2)
        ->and($props['selectedGroups'])->toBe(['bundle-'.$promo->id, 'regular'])
        ->and($props['groups'][0]['key'])->toBe('bundle-'.$promo->id)
        ->and($props['groups'][0]['name'])->toBe($promo->promo_name)
        ->and($props['groups'][0]['discount_percent'])->toBe(15)
        ->and($props['groups'][0]['items'])->toHaveCount(2)
        ->and($props['groups'][0]['items'][0]['bundle_discount'])->toBe(15000)
        ->and($props['groups'][0]['items'][1]['bundle_discount'])->toBe(7500)
        ->and($props['groups'][0]['total'])->toBe(127500)
        ->and($props['groups'][1]['key'])->toBe('regular')
        ->and($props['groups'][1]['name'])->toBe('Item Lainnya')
        ->and($props['groups'][1]['items'])->toHaveCount(1)
        ->and($props['groups'][1]['items'][0]['bundle_discount'])->toBe(0);
});

it('only processes selected groups when placing the order', function (): void {
    $bookA = Book::factory()->withStock(malang: 10)->create(['judul' => 'Paket A', 'harga' => 100000, 'aktif' => true]);
    $bookB = Book::factory()->withStock(malang: 10)->create(['judul' => 'Paket B', 'harga' => 50000, 'aktif' => true]);
    $bookC = Book::factory()->withStock(malang: 10)->create(['judul' => 'Bebas C', 'harga' => 20000, 'aktif' => true]);

    $promo = Promotion::factory()->bundle(percent: 15)->create();
    $promo->books()->sync([$bookA->id, $bookB->id]);

    session(['cart' => [$bookA->id => 1, $bookB->id => 1, $bookC->id => 1]]);

    // Hanya grup bundle yang diproses — buku bebas C tidak ikut.
    $this->actingAs($this->customer)->post(route('checkout.store'), [
        'nama_pembeli' => 'Pembeli Grup',
        'metode_bayar' => 'transfer',
        'metode_pengambilan' => 'ambil',
        'selected_groups' => ['bundle-'.$promo->id],
    ])->assertRedirect();

    $order = Order::firstOrFail();

    expect($order->items()->pluck('book_id')->all())->toBe([$bookA->id, $bookB->id])
        ->and($order->total)->toBe(127500)
        ->and(session('cart'))->toBeEmpty();
});

it('rejects checkout when no group is selected', function (): void {
    $book = Book::factory()->withStock(malang: 5)->create(['aktif' => true]);
    session(['cart' => [$book->id => 1]]);

    $this->actingAs($this->customer)->post(route('checkout.store'), [
        'nama_pembeli' => 'Pembeli',
        'metode_bayar' => 'transfer',
        'metode_pengambilan' => 'ambil',
        'selected_groups' => [],
    ])->assertSessionHasErrors('items');

    expect(Order::count())->toBe(0);
});

it('includes the per-unit promo name on checkout items', function (): void {
    $promo = Promotion::factory()->percentage(10)->create(['promo_name' => 'Promo Tes']);
    $book = Book::factory()->withStock(malang: 5)->create(['aktif' => true, 'harga' => 100000]);
    $promo->books()->sync([$book->id]);

    session(['cart' => [$book->id => 1]]);

    $props = inertiaProps($this->actingAs($this->customer)->get(route('checkout.index')));

    expect($props['groups'][0]['items'][0]['promo_name'])->toBe('Promo Tes')
        ->and($props['groups'][0]['items'][0]['promo_discount'])->toBe(10000);
});

it('gives bundle books only the bundle discount in checkout', function (): void {
    $bookA = Book::factory()->withStock(malang: 10)->create(['aktif' => true, 'harga' => 100000]);
    $bookB = Book::factory()->withStock(malang: 10)->create(['aktif' => true, 'harga' => 50000]);

    // Buku A punya promo satuan 20% — tidak boleh bertumpuk dengan bundle.
    $bookA->promotions()->attach(Promotion::factory()->percentage(20)->create());

    $promo = Promotion::factory()->bundle(percent: 15)->create();
    $promo->books()->sync([$bookA->id, $bookB->id]);

    session(['cart' => [$bookA->id => 1, $bookB->id => 1]]);

    $props = inertiaProps($this->actingAs($this->customer)->get(route('checkout.index')));
    $items = $props['groups'][0]['items'];

    // Hanya diskon bundle 15% dari harga dasar — promo 20% diabaikan.
    expect($items[0]['promo_discount'])->toBe(0)
        ->and($items[0]['promo_name'])->toBeNull()
        ->and($items[0]['bundle_discount'])->toBe(15000)
        ->and($items[0]['unit_final'])->toBe(85000)
        ->and($items[1]['bundle_discount'])->toBe(7500)
        ->and($items[1]['unit_final'])->toBe(42500);
});

it('caps bundle discount at one set in checkout', function (): void {
    $bookA = Book::factory()->withStock(malang: 10)->create(['aktif' => true, 'harga' => 100000]);
    $bookB = Book::factory()->withStock(malang: 10)->create(['aktif' => true, 'harga' => 50000]);

    $promo = Promotion::factory()->bundle(percent: 15)->create();
    $promo->books()->sync([$bookA->id, $bookB->id]);

    // bookA qty 2 → hanya 1 eksemplar mendapat diskon, sisanya harga normal.
    session(['cart' => [$bookA->id => 2, $bookB->id => 1]]);

    $props = inertiaProps($this->actingAs($this->customer)->get(route('checkout.index')));
    $itemA = collect($props['groups'][0]['items'])->firstWhere('book.id', $bookA->id);
    $itemB = collect($props['groups'][0]['items'])->firstWhere('book.id', $bookB->id);

    expect($itemA['bundle_qty'])->toBe(1)
        ->and($itemA['bundle_discount'])->toBe(15000)
        ->and($itemA['unit_final'])->toBe(85000)
        ->and($itemA['item_total'])->toBe(185000) // 1×85.000 + 1×100.000
        ->and($itemB['bundle_qty'])->toBe(1)
        ->and($itemB['item_total'])->toBe(42500)
        ->and($props['groups'][0]['total'])->toBe(227500);
});

it('moves incomplete bundle books to regular group with normal price', function (): void {
    $bookA = Book::factory()->withStock(malang: 10)->create(['judul' => 'Paket A', 'harga' => 100000, 'aktif' => true]);
    $bookB = Book::factory()->withStock(malang: 10)->create(['judul' => 'Paket B', 'harga' => 50000, 'aktif' => true]);
    $bookC = Book::factory()->withStock(malang: 10)->create(['judul' => 'Bebas C', 'harga' => 20000, 'aktif' => true]);

    $promo = Promotion::factory()->bundle(percent: 15)->create();
    $promo->books()->sync([$bookA->id, $bookB->id]);

    // Buku paket A dihapus — tersisa B (bundle tidak lengkap) + C.
    session(['cart' => [$bookB->id => 1, $bookC->id => 1]]);

    $props = inertiaProps($this->actingAs($this->customer)->get(route('checkout.index')));

    // Hanya 1 grup "Item Lainnya" — nama promo tidak muncul, harga normal.
    expect($props['groups'])->toHaveCount(1)
        ->and($props['groups'][0]['key'])->toBe('regular')
        ->and($props['groups'][0]['name'])->toBe('Item Lainnya')
        ->and($props['groups'][0]['items'])->toHaveCount(2)
        ->and($props['groups'][0]['items'][0]['bundle_discount'])->toBe(0)
        ->and($props['groups'][0]['items'][0]['unit_final'])->toBe(50000)
        ->and($props['groups'][0]['total'])->toBe(70000);
});

it('returns shipping costs for the cart destination', function (): void {
    createLocalVillages();
    fakeRajaOngkirApi();

    $book = Book::factory()->withStock(malang: 3)->create(['aktif' => true, 'berat_gr' => 500]);
    session(['cart' => [$book->id => 2]]);

    $this->actingAs($this->customer)->post(route('checkout.shipping-cost'), [
        'postal_code' => '65144',
    ])
        ->assertOk()
        ->assertJsonPath('weight_kg', 1)
        ->assertJsonPath('costs.0.courier_code', 'jne')
        ->assertJsonPath('costs.0.price', 12000);
});

it('calculates shipping costs only for the selected groups', function (): void {
    createLocalVillages();
    fakeRajaOngkirApi();

    $bookA = Book::factory()->withStock(malang: 10)->create(['aktif' => true, 'berat_gr' => 1000, 'harga' => 100000]);
    $bookB = Book::factory()->withStock(malang: 10)->create(['aktif' => true, 'berat_gr' => 1000, 'harga' => 50000]);
    $bookC = Book::factory()->withStock(malang: 10)->create(['aktif' => true, 'berat_gr' => 1000, 'harga' => 20000]);

    $promo = Promotion::factory()->bundle(percent: 15)->create();
    $promo->books()->sync([$bookA->id, $bookB->id]);

    // Paket A+B (bundle) + buku bebas C (bukan promo).
    session(['cart' => [$bookA->id => 1, $bookB->id => 1, $bookC->id => 1]]);

    // Tanpa filter: berat semua item (3 kg).
    $this->actingAs($this->customer)->post(route('checkout.shipping-cost'), [
        'postal_code' => '65144',
    ])->assertJsonPath('weight_kg', 3);

    // Hanya grup bundle yang dipilih: buku bebas C tidak ikut dihitung.
    $this->actingAs($this->customer)->post(route('checkout.shipping-cost'), [
        'postal_code' => '65144',
        'selected_groups' => ['bundle-'.$promo->id],
    ])
        ->assertOk()
        ->assertJsonPath('weight_kg', 2)
        ->assertJsonPath('costs.0.courier_code', 'jne');

    // Tidak ada grup dipilih: tidak ada ongkir yang dihitung.
    $this->actingAs($this->customer)->post(route('checkout.shipping-cost'), [
        'postal_code' => '65144',
        'selected_groups' => [],
    ])
        ->assertOk()
        ->assertJsonPath('weight_kg', null)
        ->assertJsonPath('costs', []);
});

it('removes an entire bundle group from the cart', function (): void {
    $bookA = Book::factory()->withStock(malang: 10)->create(['aktif' => true, 'harga' => 100000]);
    $bookB = Book::factory()->withStock(malang: 10)->create(['aktif' => true, 'harga' => 50000]);
    $bookC = Book::factory()->withStock(malang: 10)->create(['aktif' => true, 'harga' => 20000]);

    $promo = Promotion::factory()->bundle(percent: 15)->create();
    $promo->books()->sync([$bookA->id, $bookB->id]);

    session(['cart' => [$bookA->id => 1, $bookB->id => 1, $bookC->id => 1]]);

    // Hapus seluruh grup bundle — buku bebas C tetap di keranjang.
    $this->post(route('cart.remove-group'), ['group_key' => 'bundle-'.$promo->id])
        ->assertRedirect();

    expect(session('cart'))->toBe([(string) $bookC->id => ['qty' => 1, 'edition_id' => null]]);

    // Hapus grup regular — keranjang kosong.
    $this->post(route('cart.remove-group'), ['group_key' => 'regular'])
        ->assertRedirect();

    expect(session('cart'))->toBe([]);
});

it('rejects removing an unknown cart group', function (): void {
    $book = Book::factory()->withStock(malang: 5)->create(['aktif' => true]);
    session(['cart' => [$book->id => 1]]);

    $this->post(route('cart.remove-group'), ['group_key' => 'bundle-999'])
        ->assertSessionHasErrors('group_key');

    expect(session('cart'))->toBe([(string) $book->id => ['qty' => 1, 'edition_id' => null]]);
});

it('saves shipping cost and courier when placing the order', function (): void {
    createLocalVillages();
    fakeRajaOngkirApi();

    $book = Book::factory()->withStock(malang: 5)->create(['aktif' => true, 'berat_gr' => 1000, 'harga' => 50000]);
    session(['cart' => [$book->id => 1]]);

    $this->actingAs($this->customer)->post(route('checkout.store'), [
        'nama_pembeli' => 'Pembeli Ongkir',
        'metode_bayar' => 'transfer',
        'kode_pos' => '65144',
        'ekspedisi' => 'jne',
    ])->assertRedirect();

    $order = Order::firstOrFail();

    expect($order->shipping_cost)->toBe(12000)
        ->and($order->ekspedisi)->toBe('jne')
        ->and($order->ongkir_estimasi)->toBe('1-2')
        ->and($order->kode_pos)->toBe('65144')
        ->and($order->total)->toBe(62000); // 50.000 + ongkir 12.000
});

it('rejects invalid courier when placing the order', function (): void {
    createLocalVillages();
    fakeRajaOngkirApi();

    $book = Book::factory()->withStock(malang: 5)->create(['aktif' => true, 'berat_gr' => 500]);
    session(['cart' => [$book->id => 1]]);

    $this->actingAs($this->customer)->post(route('checkout.store'), [
        'nama_pembeli' => 'Pembeli',
        'metode_bayar' => 'transfer',
        'kelurahan' => 'Merjosari',
        'kecamatan' => 'LOWOKWARU',
        'ekspedisi' => 'COURIER-PALSU',
    ])->assertSessionHasErrors('ekspedisi');

    expect(Order::count())->toBe(0);
});

it('keeps the group selection empty when the user unchecks all groups', function (): void {
    $book = Book::factory()->withStock(malang: 5)->create(['aktif' => true]);
    session(['cart' => [$book->id => 1]]);
    session(['checkout_selected_groups' => []]);

    $props = inertiaProps($this->actingAs($this->customer)->get(route('checkout.index')));

    // User boleh uncheck semua grup — pilihan kosong tetap dihormati
    // (submit dinonaktifkan, bukan dipaksa centang ulang).
    expect($props['selectedGroups'])->toBe([]);
});

it('creates a checkout order linked to the logged-in user', function (): void {
    $book = Book::factory()->withStock(malang: 10)->create(['aktif' => true, 'harga' => 50000]);

    session(['cart' => [$book->id => 2]]);

    $this->actingAs($this->customer)->post(route('checkout.store'), [
        'nama_pembeli' => 'Pembeli Baru',
        'whatsapp_pembeli' => '08123456789',
        'alamat' => 'Jl. Merdeka 1',
        'provinsi' => 'JAWA TIMUR',
        'kabupaten_kota' => 'KOTA MALANG',
        'kecamatan' => 'KLOJEN',
        'kode_pos' => '65144',
        'metode_bayar' => 'transfer',
        'metode_pengambilan' => 'ambil',
    ])
        ->assertRedirect();

    $order = Order::firstOrFail();

    expect($order->user_id)->toBe($this->customer->id)
        ->and($order->status)->toBe(OrderStatus::MenungguKonfirmasi)
        ->and($order->nama_pembeli)->toBe('Pembeli Baru')
        ->and($order->provinsi)->toBe('JAWA TIMUR')
        ->and($order->items()->first()->qty)->toBe(2)
        ->and(session('cart'))->toBeEmpty();
});

it('links checkout order to the logged-in user', function (): void {
    $user = User::factory()->create();
    $book = Book::factory()->withStock(malang: 5)->create(['aktif' => true]);

    session(['cart' => [$book->id => 1]]);

    $this->actingAs($user)->post(route('checkout.store'), [
        'nama_pembeli' => $user->name,
        'metode_bayar' => 'transfer',
        'metode_pengambilan' => 'ambil',
    ])->assertRedirect();

    expect(Order::firstOrFail()->user_id)->toBe($user->id);
});

it('rejects checkout when stock is insufficient', function (): void {
    $book = Book::factory()->withStock(malang: 1)->create(['aktif' => true]);

    session(['cart' => [$book->id => 5]]);

    $this->actingAs($this->customer)->post(route('checkout.store'), [
        'nama_pembeli' => 'Pembeli',
        'metode_bayar' => 'transfer',
        'metode_pengambilan' => 'ambil',
    ])->assertSessionHasErrors('items');

    expect(Order::count())->toBe(0);
});

it('exposes wilayah endpoints publicly', function (): void {
    $this->getJson(route('wilayah.provinces'))->assertOk();

    $district = District::first();

    if ($district !== null) {
        $this->getJson(route('wilayah.villages', ['district_code' => $district->code]))->assertOk();
    }
});

it('shows checkout success page for the order owner', function (): void {
    $order = Order::factory()->create([
        'no_order' => 'SF-123',
        'nama_pembeli' => 'Budi',
        'user_id' => $this->customer->id,
    ]);

    session(['checkout_orders' => ['SF-123']]);

    $this->actingAs($this->customer)
        ->get(route('checkout.success', ['no_order' => 'SF-123']))
        ->assertOk()
        ->assertSee('SF-123');
});

it('exposes payment props and active bank accounts on checkout success page', function (): void {
    $order = Order::factory()->create([
        'no_order' => 'SF-BAYAR',
        'user_id' => $this->customer->id,
        'payment_status' => 'menunggu',
    ]);

    BankAccount::factory()->create(['is_active' => true]);

    $props = inertiaProps($this->actingAs($this->customer)
        ->get(route('checkout.success', ['no_order' => 'SF-BAYAR']))
        ->assertOk());

    expect($props['order']['id'])->toBe($order->id)
        ->and($props['order']['payment_status'])->toBe('menunggu')
        ->and($props['order']['bukti_transfer_path'])->toBeNull()
        ->and($props['bankAccounts'])->toHaveCount(1);
});

it('reflects uploaded bukti transfer on the checkout success page', function (): void {
    Storage::fake('public');

    $order = Order::factory()->create([
        'no_order' => 'SF-BUKTI',
        'user_id' => $this->customer->id,
        'payment_status' => 'menunggu',
    ]);

    $this->actingAs($this->customer)
        ->post(route('my-orders.upload-bukti', $order), [
            'bukti' => UploadedFile::fake()->image('bukti.jpg', 800, 600),
        ])
        ->assertRedirect();

    // actingAs bertahan antar-request — halaman sukses merefleksikan bukti.
    $props = inertiaProps($this->get(route('checkout.success', ['no_order' => 'SF-BUKTI']))->assertOk());

    expect($props['order']['bukti_transfer_path'])->not->toBeNull();
});

it('hides checkout success page from strangers', function (): void {
    Order::factory()->create([
        'no_order' => 'SF-RAHASIA',
        'nama_pembeli' => 'Budi',
        'user_id' => $this->customer->id,
    ]);

    $stranger = User::factory()->create();

    $this->actingAs($stranger)
        ->get(route('checkout.success', ['no_order' => 'SF-RAHASIA']))
        ->assertNotFound();
});

it('saves whatsapp number when admin creates an order', function (): void {
    createLocalVillages();
    fakeRajaOngkirApi();

    $admin = User::factory()->create(['is_admin' => true]);
    $book = Book::factory()->withStock(malang: 10)->create(['aktif' => true]);

    $this->actingAs($admin)
        ->post(route('admin.orders.store'), [
            'nama_pembeli' => 'Pembeli WA',
            'whatsapp_pembeli' => '08111111111',
            'metode_bayar' => 'transfer',
            'sumber_pembelian' => 'website',
            'kode_pos' => '65144',
            'ekspedisi' => 'jne',
            'items' => [['book_id' => $book->id, 'qty' => 1]],
        ])
        ->assertRedirect();

    expect(Order::firstOrFail()->no_hp)->toBe('08111111111');
});

it('rate limits checkout submissions', function (): void {
    $book = Book::factory()->withStock(malang: 50)->create(['aktif' => true]);

    session(['cart' => [$book->id => 1]]);

    for ($i = 0; $i < 5; $i++) {
        $this->actingAs($this->customer)->post(route('checkout.store'), [
            'nama_pembeli' => 'Pembeli',
            'metode_bayar' => 'transfer',
        ]);
    }

    $this->actingAs($this->customer)->post(route('checkout.store'), [
        'nama_pembeli' => 'Pembeli',
        'metode_bayar' => 'transfer',
    ])->assertStatus(429);
});

it('shows only own orders on my orders page', function (): void {
    $user = User::factory()->create();
    $other = User::factory()->create();

    Order::factory()->create(['user_id' => $user->id, 'no_order' => 'SF-PUNYA']);
    Order::factory()->create(['user_id' => $other->id, 'no_order' => 'SF-ORANG']);

    $this->actingAs($user)
        ->get(route('my-orders.index'))
        ->assertOk()
        ->assertSee('SF-PUNYA')
        ->assertDontSee('SF-ORANG');
});

it('requires login to view my orders', function (): void {
    $this->get(route('my-orders.index'))->assertRedirect(route('login'));
});

it('filters my orders by transaction status', function (): void {
    $user = User::factory()->create();

    Order::factory()->create([
        'user_id' => $user->id,
        'no_order' => 'SF-DIPROSES',
        'status' => 'diproses',
    ]);
    Order::factory()->create([
        'user_id' => $user->id,
        'no_order' => 'SF-SELESAI',
        'status' => 'selesai',
    ]);

    $props = inertiaProps($this->actingAs($user)
        ->get(route('my-orders.index', ['status' => 'diproses']))
        ->assertOk());

    $noOrders = collect($props['orders']['data'])->pluck('no_order');

    expect($noOrders)->toContain('SF-DIPROSES')
        ->not->toContain('SF-SELESAI')
        ->and($props['filters']['status'])->toBe('diproses');
});

it('ignores invalid status filter values on my orders', function (): void {
    $user = User::factory()->create();

    Order::factory()->create(['user_id' => $user->id, 'no_order' => 'SF-NORMAL']);

    // Nilai tidak valid diabaikan (bukan error) — semua order tetap tampil.
    $props = inertiaProps($this->actingAs($user)
        ->get(route('my-orders.index', ['status' => 'hack-999']))
        ->assertOk());

    expect(collect($props['orders']['data'])->pluck('no_order'))
        ->toContain('SF-NORMAL');
});

it('filters my orders to unpaid ones only', function (): void {
    $user = User::factory()->create();

    Order::factory()->create([
        'user_id' => $user->id,
        'no_order' => 'SF-BELUMBAYAR',
        'payment_status' => 'menunggu',
    ]);
    Order::factory()->create([
        'user_id' => $user->id,
        'no_order' => 'SF-LUNAS',
        'payment_status' => 'lunas',
    ]);

    $props = inertiaProps($this->actingAs($user)
        ->get(route('my-orders.index', ['belum_dibayar' => '1']))
        ->assertOk());

    $noOrders = collect($props['orders']['data'])->pluck('no_order');

    expect($noOrders)->toContain('SF-BELUMBAYAR')
        ->not->toContain('SF-LUNAS');
});

it('renders the about page from lembaga settings', function (): void {
    Setting::set('store_nama_lembaga', 'Toko Buku Nusantara');
    Setting::set('store_deskripsi', 'Cerita kami yang hangat.');
    Setting::set('store_visi', 'Menjadi toko buku pilihan.');
    Setting::set('store_misi', "Menyediakan buku berkualitas\nMelayani dengan ramah");
    Setting::set('store_keamanan', 'Transaksi aman.');
    Setting::set('store_syarat', 'Retur dalam 7 hari');
    Setting::set('store_telepon', '081234567890');
    Setting::set('store_email', 'halo@tokobuku.test');

    $this->get(route('about'))
        ->assertOk()
        ->assertSee('Toko Buku Nusantara')
        ->assertSee('Cerita kami yang hangat.')
        ->assertSee('Menjadi toko buku pilihan.')
        ->assertSee('Menyediakan buku berkualitas')
        ->assertSee('Melayani dengan ramah')
        ->assertSee('Transaksi aman.')
        ->assertSee('Retur dalam 7 hari')
        ->assertSee('081234567890')
        ->assertSee('halo@tokobuku.test');
});

it('shows active bank accounts on the about page', function (): void {
    BankAccount::factory()->create([
        'bank_name' => 'BCA',
        'account_number' => '1234567890',
        'account_holder' => 'Toko Buku Nusantara',
    ]);

    $this->get(route('about'))
        ->assertOk()
        ->assertSee('BCA')
        ->assertSee('1234567890');
});

it('hides active books without a price from the catalog', function (): void {
    $noPrice = Book::factory()->create();
    $noPrice->editions()->first()->update(['harga_jual' => 0]);
    $noPrice->updateQuietly(['harga' => null]);
    $normal = Book::factory()->create(['aktif' => true, 'harga' => 50000]);

    $this->get(route('home'))
        ->assertOk()
        ->assertDontSee($noPrice->judul)
        ->assertSee($normal->judul);
});

it('changes the edition of a cart item from checkout', function (): void {
    $book = Book::factory()->withStock(malang: 10)->create(['judul' => 'Buku Cetakan', 'harga' => 10000, 'aktif' => true]);
    $edition2 = BookEdition::factory()->create([
        'book_id' => $book->id,
        'cetakan_ke' => 2,
        'harga_jual' => 12000,
    ]);

    // Masukkan cetakan 1 ke keranjang.
    $edition1 = $book->editions()->first();
    session(['cart' => [$book->id.':'.$edition1->id => ['qty' => 1, 'edition_id' => $edition1->id]]]);

    // Ganti ke cetakan 2.
    $this->post(route('cart.edition', $book->id), ['book_edition_id' => $edition2->id])
        ->assertRedirect();

    $cart = session('cart');
    $entry = $cart[$book->id.':'.$edition1->id];

    expect($entry['edition_id'])->toBe($edition2->id);

    // Checkout menampilkan cetakan 2 dengan harga baru.
    $props = inertiaProps($this->actingAs($this->customer)->get(route('checkout.index')));
    $item = $props['groups'][0]['items'][0];

    expect($item['edition_label'])->toBe('Cetakan ke-2')
        ->and($item['price_original'])->toBe(12000);
});

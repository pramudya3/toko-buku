<?php

use App\Enums\OrderStatus;
use App\Models\Book;
use App\Models\Category;
use App\Models\Order;
use App\Models\User;

it('lists only active books in the catalog', function (): void {
    Book::factory()->create(['judul' => 'Buku Aktif', 'aktif' => true]);
    Book::factory()->create(['judul' => 'Buku Nonaktif', 'aktif' => false]);

    $this->get(route('books.catalog'))
        ->assertOk()
        ->assertSee('Buku Aktif')
        ->assertDontSee('Buku Nonaktif');
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

it('returns 404 for inactive book detail', function (): void {
    $book = Book::factory()->create(['aktif' => false]);

    $this->get(route('books.show', $book))->assertNotFound();
});

it('creates a guest checkout order with waiting status', function (): void {
    $book = Book::factory()->withStock(malang: 10)->create(['aktif' => true, 'harga' => 50000]);

    session(['cart' => [$book->id => 2]]);

    $this->post(route('checkout.store'), [
        'nama_pembeli' => 'Pembeli Baru',
        'whatsapp_pembeli' => '08123456789',
        'alamat' => 'Jl. Merdeka 1',
        'provinsi' => 'JAWA TIMUR',
        'kabupaten_kota' => 'KOTA MALANG',
        'kecamatan' => 'KLOJEN',
        'kode_pos' => '65144',
        'metode_bayar' => 'transfer',
    ])
        ->assertRedirect();

    $order = Order::firstOrFail();

    expect($order->user_id)->toBeNull()
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
    ])->assertRedirect();

    expect(Order::firstOrFail()->user_id)->toBe($user->id);
});

it('rejects checkout when stock is insufficient', function (): void {
    $book = Book::factory()->withStock(malang: 1)->create(['aktif' => true]);

    session(['cart' => [$book->id => 5]]);

    $this->post(route('checkout.store'), [
        'nama_pembeli' => 'Pembeli',
        'metode_bayar' => 'transfer',
    ])->assertSessionHasErrors('items');

    expect(Order::count())->toBe(0);
});

it('exposes wilayah endpoints publicly', function (): void {
    $this->getJson(route('wilayah.provinces'))->assertOk();
});

it('shows checkout success page for the order owner', function (): void {
    $order = Order::factory()->create(['no_order' => 'SF-123', 'nama_pembeli' => 'Budi']);

    session(['checkout_orders' => ['SF-123']]);

    $this->get(route('checkout.success', ['no_order' => 'SF-123']))
        ->assertOk()
        ->assertSee('SF-123');
});

it('hides checkout success page from strangers', function (): void {
    $order = Order::factory()->create(['no_order' => 'SF-RAHASIA', 'nama_pembeli' => 'Budi']);

    $this->get(route('checkout.success', ['no_order' => 'SF-RAHASIA']))
        ->assertNotFound();
});

it('saves whatsapp number when admin creates an order', function (): void {
    $admin = User::factory()->create(['is_admin' => true]);
    $book = Book::factory()->withStock(malang: 10)->create(['aktif' => true]);

    $this->actingAs($admin)
        ->post(route('admin.orders.store'), [
            'nama_pembeli' => 'Pembeli WA',
            'whatsapp_pembeli' => '08111111111',
            'metode_bayar' => 'transfer',
            'items' => [['book_id' => $book->id, 'qty' => 1]],
        ])
        ->assertRedirect();

    expect(Order::firstOrFail()->no_hp)->toBe('08111111111');
});

it('rate limits checkout submissions', function (): void {
    $book = Book::factory()->withStock(malang: 50)->create(['aktif' => true]);

    session(['cart' => [$book->id => 1]]);

    for ($i = 0; $i < 5; $i++) {
        $this->post(route('checkout.store'), [
            'nama_pembeli' => 'Pembeli',
            'metode_bayar' => 'transfer',
        ]);
    }

    $this->post(route('checkout.store'), [
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

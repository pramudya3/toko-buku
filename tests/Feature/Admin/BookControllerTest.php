<?php

use App\Models\Book;
use App\Models\Category;
use App\Models\User;

beforeEach(function (): void {
    $this->admin = User::factory()->admin()->create();
});

it('creates a book with auto-generated SKU when empty', function (): void {
    $this->actingAs($this->admin)
        ->post(route('admin.books.store'), [
            'judul' => 'Buku Auto SKU',
            'penulis' => 'Penulis Auto',
            'harga' => 75000,
            'stok' => 5,
        ])
        ->assertRedirect(route('admin.books.index'));

    $book = Book::where('judul', 'Buku Auto SKU')->first();

    expect($book)->not->toBeNull()
        ->and($book->kode_sku)->toMatch('/^SKU-\d{4}$/')
        ->and($book->inventoryStock->stock_malang)->toBe(5)
        ->and($book->stok)->toBe(5);
});

it('keeps a provided SKU', function (): void {
    $this->actingAs($this->admin)
        ->post(route('admin.books.store'), [
            'judul' => 'Buku SKU Manual',
            'penulis' => 'Penulis Manual',
            'harga' => 10000,
            'kode_sku' => 'SKU-9999',
        ]);

    expect(Book::where('kode_sku', 'SKU-9999')->exists())->toBeTrue();
});

it('generates sequential SKUs', function (): void {
    foreach (['Buku A', 'Buku B'] as $judul) {
        $this->actingAs($this->admin)
            ->post(route('admin.books.store'), ['judul' => $judul, 'penulis' => 'Penulis', 'harga' => 10000]);
    }

    $skus = Book::orderBy('id')->pluck('kode_sku');

    expect($skus[0])->toBe('SKU-0001')
        ->and($skus[1])->toBe('SKU-0002');
});

it('validates required fields', function (): void {
    $this->actingAs($this->admin)
        ->post(route('admin.books.store'), [])
        ->assertSessionHasErrors(['judul', 'penulis', 'harga']);

    $this->actingAs($this->admin)
        ->post(route('admin.books.store'), ['judul' => 'X', 'harga' => -5])
        ->assertSessionHasErrors('harga');
});

it('updates a book', function (): void {
    $book = Book::factory()->withStock()->create(['judul' => 'Lama']);

    $this->actingAs($this->admin)
        ->put(route('admin.books.update', $book), [
            'judul' => 'Baru',
            'penulis' => $book->penulis,
            'harga' => 90000,
            'aktif' => true,
            'is_preorder' => false,
        ])
        ->assertRedirect(route('admin.books.index'));

    expect($book->fresh()->judul)->toBe('Baru');
});

it('keeps warehouse stock authoritative when updating book metadata', function (): void {
    $book = Book::factory()->withStock(malang: 7, sidoarjo: 3)->create();

    $this->actingAs($this->admin)
        ->put(route('admin.books.update', $book), [
            'judul' => 'Buku Nonaktif',
            'penulis' => $book->penulis,
            'harga' => 90000,
            'aktif' => false,
            'is_preorder' => false,
        ])
        ->assertRedirect(route('admin.books.index'));

    $book->refresh();

    expect($book->aktif)->toBeFalse()
        ->and($book->stok)->toBe(10)
        ->and($book->inventoryStock->stock_malang)->toBe(7)
        ->and($book->inventoryStock->stock_sidoarjo)->toBe(3);
});

it('prevents duplicate SKUs', function (): void {
    $existing = Book::factory()->create(['kode_sku' => 'SKU-0001']);

    $this->actingAs($this->admin)
        ->post(route('admin.books.store'), [
            'judul' => 'Buku Duplikat',
            'penulis' => 'Penulis Duplikat',
            'harga' => 10000,
            'kode_sku' => $existing->kode_sku,
        ])
        ->assertSessionHasErrors('kode_sku');
});

it('prevents deleting a book with order history', function (): void {
    $book = Book::factory()->withStock()->create();
    \App\Models\OrderItem::factory()->create(['book_id' => $book->id]);

    $this->actingAs($this->admin)
        ->delete(route('admin.books.destroy', $book))
        ->assertRedirect();

    expect(Book::find($book->id))->not->toBeNull();
});

it('deletes a book without order history', function (): void {
    $book = Book::factory()->withStock()->create();

    $this->actingAs($this->admin)
        ->delete(route('admin.books.destroy', $book))
        ->assertRedirect(route('admin.books.index'));

    expect(Book::find($book->id))->toBeNull();
});

it('searches books by judul, penulis, isbn and sku', function (): void {
    Book::factory()->create(['judul' => 'Laskar Pelangi', 'penulis' => 'Andrea Hirata']);
    Book::factory()->create(['judul' => 'Bumi Manusia', 'penulis' => 'Pramoedya']);

    $this->actingAs($this->admin)
        ->get(route('admin.books.index', ['search' => 'laskar']))
        ->assertSuccessful()
        ->assertSee('Laskar Pelangi')
        ->assertDontSee('Bumi Manusia');

    $this->actingAs($this->admin)
        ->get(route('admin.books.index', ['search' => 'pramoedya']))
        ->assertSuccessful()
        ->assertSee('Bumi Manusia');
});

it('filters books by category and status', function (): void {
    $categoryA = Category::factory()->create(['nama' => 'Fiksi']);
    $categoryB = Category::factory()->create(['nama' => 'Nonfiksi']);

    Book::factory()->create(['judul' => 'Buku A', 'category_id' => $categoryA->id, 'aktif' => true]);
    Book::factory()->create(['judul' => 'Buku B', 'category_id' => $categoryB->id, 'aktif' => false]);

    $byCategory = inertiaProps($this->actingAs($this->admin)
        ->get(route('admin.books.index', ['category_id' => $categoryA->id])));

    expect(collect($byCategory['books']['data'])->pluck('judul')->all())->toBe(['Buku A']);

    $byStatus = inertiaProps($this->actingAs($this->admin)
        ->get(route('admin.books.index', ['status' => 'nonaktif'])));

    expect(collect($byStatus['books']['data'])->pluck('judul')->all())->toBe(['Buku B']);
});

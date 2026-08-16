<?php

use App\Models\Book;
use App\Models\BookImage;
use App\Models\Category;
use App\Models\InventoryMovement;
use App\Models\OrderItem;
use App\Models\User;
use App\Services\InventoryService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    $this->admin = User::factory()->admin()->create();
});

/**
 * Payload valid untuk create/update — kategori + minimal 1 cetakan.
 */
function bookPayload(array $overrides = []): array
{
    return [
        'category_id' => Category::factory()->create()->id,
        'editions' => [
            array_merge([
                'cetakan_ke' => 1,
                'harga_beli' => 50000,
                'harga_jual' => 75000,
                'is_active' => true,
            ], $overrides),
        ],
    ];
}

it('stores a pre-order flag and ETA', function (): void {
    $this->actingAs($this->admin)
        ->post(route('admin.books.store'), array_merge([
            'judul' => 'Buku New Coming',
            'is_preorder' => '1',
            'preorder_eta' => '2026-10-15',
        ], bookPayload()));

    $book = Book::where('judul', 'Buku New Coming')->firstOrFail();

    expect($book->is_preorder)->toBeTrue()
        ->and($book->preorder_eta)->toBe('2026-10-15');
});

it('serializes preorder_eta as Y-m-d so date inputs work', function (): void {
    $book = Book::factory()->create([
        'aktif' => true,
        'harga' => 50000,
        'is_preorder' => true,
        'preorder_eta' => '2026-10-15',
    ]);

    $this->actingAs($this->admin)
        ->get(route('admin.books.edit', $book))
        ->assertInertia(fn ($page) => $page
            ->where('book.is_preorder', true)
            ->where('book.preorder_eta', '2026-10-15'));
});

it('keeps the ETA when editing without touching it', function (): void {
    $book = Book::factory()->create([
        'aktif' => true,
        'harga' => 50000,
        'is_preorder' => true,
        'preorder_eta' => '2026-10-15',
    ]);

    $this->actingAs($this->admin)
        ->put(route('admin.books.update', $book), array_merge([
            'judul' => 'Judul Diubah',
            'penulis' => $book->penulis,
            'aktif' => true,
        ], bookPayload()))
        ->assertRedirect(route('admin.books.index'));

    expect($book->fresh()->preorder_eta)->toBe('2026-10-15');
});

it('creates a book with auto-generated SKU when empty', function (): void {
    $this->actingAs($this->admin)
        ->post(route('admin.books.store'), [
            'judul' => 'Buku Auto SKU',
            'penulis' => 'Penulis Auto',
            'category_id' => Category::factory()->create()->id,
            'editions' => [
                [
                    'cetakan_ke' => 1,
                    'harga_beli' => 50000,
                    'harga_jual' => 75000,
                    'is_active' => true,
                ],
            ],
        ])
        ->assertRedirect(route('admin.books.index'));

    $book = Book::where('judul', 'Buku Auto SKU')->first();

    expect($book)->not->toBeNull()
        ->and($book->kode_sku)->toMatch('/^SKU-\d{4}$/')
        // Stok TIDAK dicatat saat pembuatan — lewat menu Barang Masuk.
        ->and($book->editions()->first()->stocks()->sum('qty'))->toBe(0)
        ->and(app(InventoryService::class)->availableStock($book->fresh()))->toBe(0)
        ->and($book->stok)->toBe(0)
        ->and(InventoryMovement::where('book_id', $book->id)->count())->toBe(0);
});

it('keeps a provided SKU', function (): void {
    $this->actingAs($this->admin)
        ->post(route('admin.books.store'), array_merge([
            'judul' => 'Buku SKU Manual',
            'penulis' => 'Penulis Manual',
            'harga' => 10000,
            'kode_sku' => 'SKU-9999',
        ], bookPayload()));

    expect(Book::where('kode_sku', 'SKU-9999')->exists())->toBeTrue();
});

it('stores book detail fields (bahasa, jenis cover, dll.)', function (): void {
    $this->actingAs($this->admin)
        ->post(route('admin.books.store'), array_merge([
            'judul' => 'Buku Detail Lengkap',
            'penulis' => 'Penulis Detail',
            'penterjemah' => 'Penterjemah Detail',
            'penerbit' => 'Penerbit X',
            'tahun' => 2024,
            'isbn' => '9780000000000',
            'dimensi' => '20 x 13 cm',
            'kemasan' => 'Dus',
            'berat_gr' => 300,
            'jumlah_halaman' => 250,
            'jenis_kertas' => 'Bookpaper',
            'cetakan' => 'Ke-1, 2024',
            'bahasa' => 'Indonesia',
            'jenis_cover' => 'Soft cover',
            'rating_umur' => '13+',
        ], bookPayload()))
        ->assertRedirect(route('admin.books.index'));

    $book = Book::where('judul', 'Buku Detail Lengkap')->firstOrFail();

    expect($book->bahasa)->toBe('Indonesia')
        ->and($book->penterjemah)->toBe('Penterjemah Detail')
        ->and($book->jenis_cover)->toBe('Soft cover')
        ->and($book->dimensi)->toBe('20 x 13 cm')
        ->and($book->jenis_kertas)->toBe('Bookpaper')
        ->and($book->rating_umur)->toBe('13+');
});

it('generates sequential SKUs', function (): void {
    foreach (['Buku A', 'Buku B'] as $judul) {
        $this->actingAs($this->admin)
            ->post(route('admin.books.store'), array_merge([
                'judul' => $judul,
                'penulis' => 'Penulis',
                'harga' => 10000,
            ], bookPayload()));
    }

    $skus = Book::orderBy('id')->pluck('kode_sku');

    expect($skus[0])->toBe('SKU-0001')
        ->and($skus[1])->toBe('SKU-0002');
});

it('validates required fields', function (): void {
    $this->actingAs($this->admin)
        ->post(route('admin.books.store'), [])
        ->assertSessionHasErrors(['judul', 'editions']);

    $this->actingAs($this->admin)
        ->post(route('admin.books.store'), array_merge([
            'judul' => 'X',
            'penulis' => 'Penulis',
            'editions' => [],
        ]))
        ->assertSessionHasErrors('editions');
});

it('rejects zero-priced editions', function (): void {
    $this->actingAs($this->admin)
        ->post(route('admin.books.store'), array_merge(
            bookPayload(['harga_jual' => 0]),
            ['judul' => 'Buku Gratis', 'penulis' => 'Penulis'],
        ))
        ->assertSessionHasErrors('editions.0.harga_jual');

    $this->actingAs($this->admin)
        ->post(route('admin.books.store'), array_merge(
            bookPayload(['harga_beli' => 0]),
            ['judul' => 'Buku Gratis', 'penulis' => 'Penulis'],
        ))
        ->assertSessionHasErrors('editions.0.harga_beli');
});

it('syncs a null price book from its edition price on update', function (): void {
    $book = Book::factory()->create();
    $book->editions()->first()->update(['harga_jual' => 0]);
    $book->updateQuietly(['harga' => null]);

    // Legacy data (harga null + harga_jual 0) — update apa pun harus menyinkronkan harga.
    $this->actingAs($this->admin)
        ->put(route('admin.books.update', $book), array_merge(
            bookPayload(['harga_beli' => 30000, 'harga_jual' => 45000]),
            ['judul' => $book->judul, 'penulis' => $book->penulis],
        ))
        ->assertRedirect();

    expect($book->fresh()->harga)->toBe(45000);
});

it('updates a book', function (): void {
    $book = Book::factory()->withStock()->create(['judul' => 'Lama']);

    $this->actingAs($this->admin)
        ->put(route('admin.books.update', $book), array_merge([
            'judul' => 'Baru',
            'penulis' => $book->penulis,
            'aktif' => true,
        ], bookPayload()))
        ->assertRedirect(route('admin.books.index'));

    expect($book->fresh()->judul)->toBe('Baru');
});

it('keeps warehouse stock authoritative when updating book metadata', function (): void {
    $book = Book::factory()->withStock(malang: 7, sidoarjo: 3)->create();

    $this->actingAs($this->admin)
        ->put(route('admin.books.update', $book), array_merge([
            'judul' => 'Buku Nonaktif',
            'penulis' => $book->penulis,
            'aktif' => false,
        ], bookPayload()))
        ->assertRedirect(route('admin.books.index'));

    $book->refresh();

    expect($book->aktif)->toBeFalse()
        ->and($book->stok)->toBe(10)
        ->and(app(InventoryService::class)->availableStock($book->fresh()))->toBe(10);
});

it('prevents duplicate SKUs', function (): void {
    $existing = Book::factory()->create(['kode_sku' => 'SKU-0001']);

    $this->actingAs($this->admin)
        ->post(route('admin.books.store'), array_merge([
            'judul' => 'Buku Duplikat',
            'penulis' => 'Penulis Duplikat',
            'harga' => 10000,
            'kode_sku' => $existing->kode_sku,
        ], bookPayload()))
        ->assertSessionHasErrors('kode_sku');
});

it('prevents deleting a book with order history', function (): void {
    $book = Book::factory()->withStock()->create();
    OrderItem::factory()->create(['book_id' => $book->id]);

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

it('deletes gallery files when deleting a book without order history', function (): void {
    Storage::fake('r2');
    config()->set('filesystems.disks.r2.url', 'http://cdn.test');

    $book = Book::factory()->withStock()->create();
    Storage::disk('r2')->put('covers/galeri1.jpg', 'gambar');
    Storage::disk('r2')->put('covers/galeri2.jpg', 'gambar');
    $book->images()->create(['image_url' => 'http://cdn.test/covers/galeri1.jpg', 'urutan' => 1]);
    $book->images()->create(['image_url' => 'http://cdn.test/covers/galeri2.jpg', 'urutan' => 2]);

    $this->actingAs($this->admin)
        ->delete(route('admin.books.destroy', $book))
        ->assertRedirect(route('admin.books.index'));

    Storage::disk('r2')->assertMissing('covers/galeri1.jpg');
    Storage::disk('r2')->assertMissing('covers/galeri2.jpg');
});

it('orders gallery images by urutan on the edit form', function (): void {
    $book = Book::factory()->withStock()->create();
    $book->images()->create(['image_url' => 'http://cdn.test/covers/kedua.jpg', 'urutan' => 2]);
    $book->images()->create(['image_url' => 'http://cdn.test/covers/pertama.jpg', 'urutan' => 1]);

    $props = inertiaProps($this->actingAs($this->admin)
        ->get(route('admin.books.edit', $book)));

    expect($props['images'])->toHaveCount(2)
        ->and($props['images'][0]['image_url'])->toBe('http://cdn.test/covers/pertama.jpg')
        ->and($props['images'][1]['image_url'])->toBe('http://cdn.test/covers/kedua.jpg');
});

it('filters books by low stock on the index page', function (): void {
    Book::factory()->withStock(malang: 2)->create(['judul' => 'Buku Stok Menipis']);
    Book::factory()->withStock(malang: 0)->create(['judul' => 'Buku Stok Habis']);
    Book::factory()->withStock(malang: 50)->create(['judul' => 'Buku Stok Aman']);

    $this->actingAs($this->admin)
        ->get(route('admin.books.index', ['low_stock' => '1']))
        ->assertSuccessful()
        ->assertSee('Buku Stok Menipis')
        ->assertDontSee('Buku Stok Habis')
        ->assertDontSee('Buku Stok Aman');
});

it('filters books with empty stock on the index page', function (): void {
    Book::factory()->withStock(malang: 0)->create(['judul' => 'Buku Stok Habis']);
    Book::factory()->withStock(malang: 2)->create(['judul' => 'Buku Stok Menipis']);
    Book::factory()->withStock(malang: 50)->create(['judul' => 'Buku Stok Aman']);

    $this->actingAs($this->admin)
        ->get(route('admin.books.index', ['low_stock' => 'kosong']))
        ->assertSuccessful()
        ->assertSee('Buku Stok Habis')
        ->assertDontSee('Buku Stok Menipis')
        ->assertDontSee('Buku Stok Aman');
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

it('stores editions with editable names and separate buy/sell prices per cetakan', function (): void {
    $this->actingAs($this->admin)
        ->post(route('admin.books.store'), [
            'judul' => 'Buku Multi Cetakan',
            'penulis' => 'Penulis Multi',
            'harga' => 40000,
            'category_id' => Category::factory()->create()->id,
            'editions' => [
                ['nama' => 'Cetakan pertama', 'cetakan_ke' => 1, 'harga_beli' => 30000, 'harga_jual' => 40000, 'is_active' => true],
                ['nama' => 'Edisi Revisi', 'cetakan_ke' => 2, 'harga_beli' => 33000, 'harga_jual' => 43000, 'is_active' => false],
            ],
        ])
        ->assertRedirect(route('admin.books.index'));

    $book = Book::where('judul', 'Buku Multi Cetakan')->firstOrFail();

    expect($book->editions()->count())->toBe(2)
        ->and($book->editions()->where('cetakan_ke', 1)->value('nama'))->toBe('Cetakan pertama')
        ->and($book->editions()->where('cetakan_ke', 1)->value('harga_beli'))->toBe(30000)
        ->and($book->editions()->where('cetakan_ke', 2)->value('nama'))->toBe('Edisi Revisi')
        ->and($book->editions()->where('cetakan_ke', 2)->value('harga_jual'))->toBe(43000)
        // books.harga sinkron dari cetakan aktif
        ->and($book->harga)->toBe(40000);
});

it('syncs book price from the active edition on update', function (): void {
    $book = Book::factory()->withStock()->create(['judul' => 'Editable']);

    $this->actingAs($this->admin)
        ->put(route('admin.books.update', $book), [
            'judul' => 'Editable',
            'penulis' => $book->penulis,
            'harga' => 40000,
            'category_id' => Category::factory()->create()->id,
            'editions' => [
                ['nama' => 'Cetakan ke-1 (Revisi)', 'cetakan_ke' => 1, 'harga_beli' => 30000, 'harga_jual' => 40000, 'is_active' => false],
                ['nama' => 'Cetakan ke-2', 'cetakan_ke' => 2, 'harga_beli' => 33000, 'harga_jual' => 43000, 'is_active' => true],
            ],
        ])
        ->assertRedirect(route('admin.books.index'));

    $book->refresh();

    expect($book->harga)->toBe(43000)
        ->and($book->editions()->count())->toBe(2)
        ->and($book->editions()->where('is_active', true)->value('cetakan_ke'))->toBe(2)
        ->and($book->editions()->where('cetakan_ke', 1)->value('nama'))->toBe('Cetakan ke-1 (Revisi)');
});

it('requires a category when creating a book', function (): void {
    $this->actingAs($this->admin)
        ->post(route('admin.books.store'), [
            'judul' => 'Tanpa Kategori',
            'penulis' => 'Penulis',
            'harga' => 10000,
            'editions' => [
                ['cetakan_ke' => 1, 'harga_beli' => 30000, 'harga_jual' => 40000, 'is_active' => true],
            ],
        ])
        ->assertSessionHasErrors('category_id');
});

it('requires at least one edition', function (): void {
    $this->actingAs($this->admin)
        ->post(route('admin.books.store'), [
            'judul' => 'Tanpa Cetakan',
            'penulis' => 'Penulis',
            'harga' => 10000,
        ])
        ->assertSessionHasErrors('editions');
});

it('stores gallery images with ordered urutan', function (): void {
    Storage::fake('r2');

    $this->actingAs($this->admin)
        ->post(route('admin.books.store'), array_merge([
            'judul' => 'Buku Galeri',
            'penulis' => 'Penulis Galeri',
            'images' => [
                UploadedFile::fake()->image('foto1.jpg', 400, 600),
                UploadedFile::fake()->image('foto2.jpg', 400, 600),
            ],
        ], bookPayload()))
        ->assertRedirect(route('admin.books.index'));

    $book = Book::where('judul', 'Buku Galeri')->firstOrFail();

    expect($book->images()->count())->toBe(2)
        ->and($book->images()->pluck('urutan')->all())->toBe([1, 2])
        ->and(Storage::disk('r2')->allFiles('covers'))->toHaveCount(2);

    // File yang tersimpan sudah dinormalisasi jadi JPEG (magic bytes FFD8FF).
    foreach (Storage::disk('r2')->allFiles('covers') as $storedPath) {
        expect(Storage::disk('r2')->get($storedPath))->toStartWith("\xFF\xD8\xFF");
    }
});

it('removes gallery images marked for deletion and adds new ones on update', function (): void {
    Storage::fake('r2');

    $book = Book::factory()->create(['judul' => 'Galeri Update']);
    $oldImage = BookImage::factory()->create([
        'book_id' => $book->id,
        'image_url' => 'https://cdn.example.com/covers/lama.jpg',
        'urutan' => 1,
    ]);

    $this->actingAs($this->admin)
        ->put(route('admin.books.update', $book), array_merge([
            'judul' => 'Galeri Update',
            'penulis' => $book->penulis,
            'harga' => 40000,
            'removed_images' => [$oldImage->id],
            'images' => [UploadedFile::fake()->image('baru.jpg', 400, 600)],
        ], bookPayload()))
        ->assertRedirect(route('admin.books.index'));

    $book->refresh();

    expect($book->images()->count())->toBe(1)
        ->and($book->images()->first()->image_url)->not->toBe('https://cdn.example.com/covers/lama.jpg')
        ->and(BookImage::find($oldImage->id))->toBeNull()
        ->and(Storage::disk('r2')->allFiles('covers'))->toHaveCount(1);
});

it('validates gallery images must be images under 2 MB', function (): void {
    Storage::fake('r2');

    $this->actingAs($this->admin)
        ->post(route('admin.books.store'), array_merge([
            'judul' => 'Buku Galeri Salah',
            'penulis' => 'Penulis',
            'images' => [UploadedFile::fake()->create('dokumen.pdf', 100)],
        ], bookPayload()))
        ->assertSessionHasErrors('images.0');
});

it('requires gallery images as an array (client must send images[])', function (): void {
    Storage::fake('r2');

    // Browser dengan <input name="images"> (tanpa []) + 1 file mengirim bentuk
    // ini — PHP tidak menormalisasinya jadi array → rule `array` menolak.
    // Inilah kenapa input di Form.vue wajib name="images[]".
    $this->actingAs($this->admin)
        ->post(route('admin.books.store'), array_merge([
            'judul' => 'Buku Satu Gambar',
            'penulis' => 'Penulis Satu',
            'images' => UploadedFile::fake()->image('satu.jpg', 400, 600),
        ], bookPayload()))
        ->assertSessionHasErrors('images');

    expect(Book::where('judul', 'Buku Satu Gambar')->exists())->toBeFalse()
        ->and(Storage::disk('r2')->allFiles('covers'))->toBeEmpty();
});

it('removes the stored cover when remove_cover is set', function (): void {
    $book = Book::factory()->create([
        'judul' => 'Hapus Cover',
        'cover_url' => 'https://cdn.example.com/covers/lama.jpg',
    ]);

    $this->actingAs($this->admin)
        ->put(route('admin.books.update', $book), array_merge([
            'judul' => 'Hapus Cover',
            'penulis' => $book->penulis,
            'harga' => 40000,
            'remove_cover' => 1,
        ], bookPayload()))
        ->assertRedirect(route('admin.books.index'));

    expect($book->fresh()->cover_url)->toBeNull();
});

it('keeps the new cover when remove_cover and a new file are sent together', function (): void {
    Storage::fake('r2');

    $book = Book::factory()->create([
        'judul' => 'Cover Baru',
        'cover_url' => 'https://cdn.example.com/covers/lama.jpg',
    ]);

    $this->actingAs($this->admin)
        ->put(route('admin.books.update', $book), array_merge([
            'judul' => 'Cover Baru',
            'penulis' => $book->penulis,
            'harga' => 40000,
            'remove_cover' => 1,
            'cover' => UploadedFile::fake()->image('baru.jpg', 400, 600),
        ], bookPayload()))
        ->assertRedirect(route('admin.books.index'));

    expect($book->fresh()->cover_url)->not->toBeNull()
        ->and($book->fresh()->cover_url)->not->toBe('https://cdn.example.com/covers/lama.jpg');
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

it('toggles book active state and hides it from the storefront catalog', function (): void {
    $book = Book::factory()->create(['judul' => 'Buku Toggle', 'aktif' => true]);

    $this->actingAs($this->admin)
        ->patch(route('admin.books.toggle-active', $book))
        ->assertRedirect();

    expect($book->refresh()->aktif)->toBeFalse();

    // Tidak tampil di katalog storefront
    $catalog = inertiaProps($this->get(route('books.catalog'))->assertSuccessful());
    expect(collect($catalog['books']['data'])->pluck('judul'))->not->toContain('Buku Toggle');

    // Reactivate
    $this->actingAs($this->admin)
        ->patch(route('admin.books.toggle-active', $book))
        ->assertRedirect();

    expect($book->refresh()->aktif)->toBeTrue();

    $catalog = inertiaProps($this->get(route('books.catalog'))->assertSuccessful());
    expect(collect($catalog['books']['data'])->pluck('judul'))->toContain('Buku Toggle');
});

it('blocks customers from toggling book status', function (): void {
    $customer = User::factory()->create();
    $book = Book::factory()->create(['aktif' => true]);

    $this->actingAs($customer)
        ->patch(route('admin.books.toggle-active', $book))
        ->assertForbidden();

    expect($book->refresh()->aktif)->toBeTrue();
});

it('restores a soft-deleted book', function (): void {
    $book = Book::factory()->create();

    $this->actingAs($this->admin)
        ->delete(route('admin.books.destroy', $book))
        ->assertRedirect();

    expect(Book::find($book->id))->toBeNull();

    $this->actingAs($this->admin)
        ->post(route('admin.books.restore', $book))
        ->assertRedirect(route('admin.books.index'));

    expect(Book::find($book->id))->not->toBeNull();
});

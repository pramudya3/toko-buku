<?php

use App\Enums\ActivityAction;
use App\Models\ActivityLog;
use App\Models\Book;
use App\Models\BookEdition;
use App\Models\Category;
use App\Models\InventoryStock;
use App\Models\User;
use Illuminate\Http\UploadedFile;

beforeEach(function (): void {
    $this->admin = User::factory()->admin()->create();
});

/**
 * Buat file CSV untuk di-upload dalam test (helper unik per file test).
 */
function bookCsvUpload(string $content): UploadedFile
{
    $path = tempnam(sys_get_temp_dir(), 'csv-');
    file_put_contents($path, $content);

    return new UploadedFile($path, 'buku.csv', 'text/csv', null, true);
}

it('imports books from invoice-style csv with categories, codes and editions', function (): void {
    // Layout 16 kolom seperti INVOICE - PRICELIST.csv (kolom buku di 9–15).
    $csv = "No,Judul Buku,Harga Normal,Harga Promo/Guru ,%,Potongan reseller/bazaf saat promo,,,,Kategori,Kode Brg,Nama Barang,Penulis,Hrg Jual,Harga Promo,Harga Guru\n"
        ."1,40 Hadist,30.000,28.500,5,10%,,,,Parenting,PRN000001,Smart Islamic Parenting,Dr Jasim Al Muthawwa',100.000,,\n"
        ."2,Lainnya,50.000,45.000,10,10%,,,,Tafsir dan Tadabbur,ALQ000001,Fami Bi Syauqin,Dr. Raghib As-Sirjani,80.000,,\n";

    $this->actingAs($this->admin)
        ->post(route('admin.books.import'), ['file' => bookCsvUpload($csv)])
        ->assertRedirect();

    $book = Book::where('kode_sku', 'PRN000001')->first();

    expect($book)->not->toBeNull()
        ->and($book->judul)->toBe('Smart Islamic Parenting')
        ->and($book->penulis)->toBe("Dr Jasim Al Muthawwa'")
        ->and($book->aktif)->toBeTrue()
        ->and($book->harga)->toBe(100_000)
        ->and($book->category->nama)->toBe('Parenting')
        ->and($book->category->kode)->toBe('PRN');

    $edition = $book->editions()->where('cetakan_ke', 1)->first();

    expect($edition)->not->toBeNull()
        ->and($edition->harga_jual)->toBe(100_000)
        ->and($edition->harga_beli)->toBe(0)
        ->and($edition->is_active)->toBeTrue();

    expect(Book::where('kode_sku', 'ALQ000001')->exists())->toBeTrue()
        ->and(Category::where('nama', 'Tafsir dan Tadabbur')->first()->kode)->toBe('ALQ')
        ->and(InventoryStock::where('book_id', $book->id)->exists())->toBeTrue();
});

it('auto-generates sku from category abbreviation avoiding reserved codes', function (): void {
    $csv = "Kategori,Kode Brg,Nama Barang,Penulis,Hrg Jual\n"
        ."Parenting,1,Smart Islamic Parenting,Penulis A,100.000\n"
        ."Parenting,2,Wasiat Parenting,Penulis B,70.000\n"
        ."Parenting,PRN000001,Rumahku Sekolahku,Penulis C,40.000\n";

    $this->actingAs($this->admin)
        ->post(route('admin.books.import'), ['file' => bookCsvUpload($csv)])
        ->assertRedirect();

    // PRN000001 sudah dipesan baris ketiga → baris pertama/kedua maju ke 002/003.
    expect(Book::where('judul', 'Smart Islamic Parenting')->value('kode_sku'))->toBe('PRN000002')
        ->and(Book::where('judul', 'Wasiat Parenting')->value('kode_sku'))->toBe('PRN000003')
        ->and(Book::where('judul', 'Rumahku Sekolahku')->value('kode_sku'))->toBe('PRN000001');
});

it('skips numeric-only and code-like values without digits for category kode', function (): void {
    $csv = "Kategori,Kode Brg,Nama Barang,Penulis,Hrg Jual\n"
        ."Anak-anak,BAI,Bundling Ku Tahu,Ibu,50.000\n"
        ."Anak-anak,ANK000001,Tadabbur Kisah Hudhud,Penulis,45.000\n";

    $this->actingAs($this->admin)
        ->post(route('admin.books.import'), ['file' => bookCsvUpload($csv)])
        ->assertRedirect();

    // Kode kategori diambil dari ANK000001 (huruf+digit), bukan BAI; SKU auto
    // untuk Bundling tidak menabrak ANK000001 yang sudah dipesan baris kedua.
    expect(Category::where('nama', 'Anak-anak')->first()->kode)->toBe('ANK')
        ->and(Book::where('judul', 'Bundling Ku Tahu')->value('kode_sku'))->toBe('ANK000002')
        ->and(Book::where('judul', 'Tadabbur Kisah Hudhud')->value('kode_sku'))->toBe('ANK000001')
        ->and(Book::count())->toBe(2);
});

it('reuses existing category case-insensitively and keeps its kode', function (): void {
    Category::create(['nama' => 'Parenting', 'kode' => 'PDR']);

    $csv = "Kategori,Kode Brg,Nama Barang,Penulis,Hrg Jual\n"
        ."parenting,PRN000001,Smart Islamic Parenting,A,100.000\n";

    $this->actingAs($this->admin)
        ->post(route('admin.books.import'), ['file' => bookCsvUpload($csv)])
        ->assertRedirect();

    expect(Category::count())->toBe(1)
        ->and(Category::first()->kode)->toBe('PDR');
});

it('updates existing book by kode_sku and preserves harga_beli', function (): void {
    $book = Book::create(['judul' => 'Lama', 'penulis' => 'Lama', 'kode_sku' => 'ALQ000001', 'aktif' => true, 'stok' => 0]);
    BookEdition::create(['book_id' => $book->id, 'cetakan_ke' => 1, 'harga_beli' => 5_000, 'harga_jual' => 50_000]);

    $csv = "Kategori,Kode Brg,Nama Barang,Penulis,Hrg Jual\n"
        ."Tafsir dan Tadabbur,ALQ000001,Judul Baru,Penulis Baru,60.000\n";

    $this->actingAs($this->admin)
        ->post(route('admin.books.import'), ['file' => bookCsvUpload($csv)])
        ->assertRedirect();

    expect(Book::count())->toBe(1);

    $book->refresh();
    $edition = $book->editions()->first();

    expect($book->judul)->toBe('Judul Baru')
        ->and($book->penulis)->toBe('Penulis Baru')
        ->and($book->harga)->toBe(60_000)
        ->and($edition->harga_jual)->toBe(60_000)
        ->and($edition->harga_beli)->toBe(5_000); // tidak ditimpa
});

it('skips identical rows', function (): void {
    $row = 'Parenting,PRN000001,Smart Islamic Parenting,A,100.000';
    $csv = "Kategori,Kode Brg,Nama Barang,Penulis,Hrg Jual\n{$row}\n{$row}\n";

    $this->actingAs($this->admin)
        ->post(route('admin.books.import'), ['file' => bookCsvUpload($csv)])
        ->assertRedirect();

    expect(Book::count())->toBe(1);

    $page = $this->actingAs($this->admin)
        ->get(route('admin.books.index'))
        ->viewData('page');

    expect($page['flash']['toast']['message'])->toContain('1 buku baru, 0 diperbarui, 1 dilewati');
});

it('imports pricelist layout (judul + harga) when right headers absent', function (): void {
    $csv = "No,Judul Buku,Harga Normal,Harga Promo/Guru,%,Potongan\n"
        ."1,40 Hadist Pengagungan,30.000,28.500,5,10%\n"
        ."2,Judul Kedua,60.000,42.000,30,10%\n";

    $this->actingAs($this->admin)
        ->post(route('admin.books.import'), ['file' => bookCsvUpload($csv)])
        ->assertRedirect();

    expect(Book::where('judul', '40 Hadist Pengagungan')->exists())->toBeTrue()
        ->and(Book::where('judul', '40 Hadist Pengagungan')->value('harga'))->toBe(30_000)
        ->and(Book::where('judul', '40 Hadist Pengagungan')->value('category_id'))->toBeNull()
        ->and(Book::where('judul', '40 Hadist Pengagungan')->value('kode_sku'))->toBe('SKU-0001');
});

it('reports rows with empty title or invalid price as errors', function (): void {
    $csv = "Kategori,Kode Brg,Nama Barang,Penulis,Hrg Jual\n"
        .",, ,,100.000\n"
        ."Parenting,PRN000001,Buku Bagus,A,#N/A\n"
        ."Parenting,PRN000002,Buku Valid,B,50.000\n";

    $this->actingAs($this->admin)
        ->post(route('admin.books.import'), ['file' => bookCsvUpload($csv)])
        ->assertRedirect();

    expect(Book::count())->toBe(1)
        ->and(Book::first()->judul)->toBe('Buku Valid');

    $page = $this->actingAs($this->admin)
        ->get(route('admin.books.index'))
        ->viewData('page');

    expect($page['flash']['toast']['message'])->toContain('2 baris gagal');
});

it('sets harga_beli from csv column and preserves it when absent', function (): void {
    $csv = "kategori,kode,judul,penulis,harga_jual,harga_beli\n"
        ."Tafsir dan Tadabbur,ALQ000001,Fami Bi Syauqin,A,100000,80000\n";

    $this->actingAs($this->admin)
        ->post(route('admin.books.import'), ['file' => bookCsvUpload($csv)])
        ->assertRedirect();

    $edition = Book::where('kode_sku', 'ALQ000001')->first()->editions()->first();

    expect($edition->harga_beli)->toBe(80_000)
        ->and($edition->harga_jual)->toBe(100_000);

    // Import ulang tanpa kolom harga_beli → harga_beli tidak ditimpa.
    $csv2 = "kategori,kode,judul,penulis,harga_jual\n"
        ."Tafsir dan Tadabbur,ALQ000001,Fami Bi Syauqin,A,100000\n";

    $this->actingAs($this->admin)
        ->post(route('admin.books.import'), ['file' => bookCsvUpload($csv2)])
        ->assertRedirect();

    expect($edition->fresh()->harga_beli)->toBe(80_000);
});

it('skips bundling category rows and reports them', function (): void {
    $csv = "kategori,kode,judul,penulis,harga_jual,harga_beli\n"
        ."Tafsir dan Tadabbur,ALQ000001,Fami Bi Syauqin,A,100000,\n"
        ."Bundling,Bdl000001,Bundling Contoh,B,150000,\n";

    $this->actingAs($this->admin)
        ->post(route('admin.books.import'), ['file' => bookCsvUpload($csv)])
        ->assertRedirect();

    expect(Book::count())->toBe(1) // hanya Fami Bi Syauqin
        ->and(Book::where('judul', 'Bundling Contoh')->exists())->toBeFalse()
        ->and(Category::where('nama', 'Bundling')->exists())->toBeFalse();

    $page = $this->actingAs($this->admin)
        ->get(route('admin.books.index'))
        ->viewData('page');

    expect($page['flash']['toast']['message'])->toContain('1 baris bundling dilewati');
});

it('rejects non csv files', function (): void {
    $file = UploadedFile::fake()->create('data.pdf', 100, 'application/pdf');

    $this->actingAs($this->admin)
        ->post(route('admin.books.import'), ['file' => $file])
        ->assertSessionHasErrors('file');

    expect(Book::count())->toBe(0);
});

it('requires admin to import', function (): void {
    $customer = User::factory()->create();

    $this->actingAs($customer)
        ->post(route('admin.books.import'), [
            'file' => bookCsvUpload("Kategori,Kode Brg,Nama Barang,Penulis,Hrg Jual\n"),
        ])
        ->assertForbidden();
});

it('logs a single import activity without per-book observer logs', function (): void {
    $csv = "Kategori,Kode Brg,Nama Barang,Penulis,Hrg Jual\n"
        ."Parenting,PRN000001,Buku Satu,A,10.000\n"
        ."Parenting,PRN000002,Buku Dua,B,20.000\n";

    $this->actingAs($this->admin)
        ->post(route('admin.books.import'), ['file' => bookCsvUpload($csv)])
        ->assertRedirect();

    $log = ActivityLog::where('action', ActivityAction::BookImport->value)->first();

    expect($log)->not->toBeNull()
        ->and($log->description)->toContain('2 dibuat')
        ->and($log->user_id)->toBe($this->admin->id)
        ->and(ActivityLog::where('action', ActivityAction::BookCreate->value)->count())->toBe(0)
        ->and(ActivityLog::where('action', 'category.create')->count())->toBe(0);
});

it('stores category with kode via form and rejects duplicates/invalid kode', function (): void {
    $this->actingAs($this->admin)
        ->post(route('admin.categories.store'), ['nama' => 'Parenting', 'kode' => 'PRN'])
        ->assertRedirect(route('admin.categories.index'));

    expect(Category::where('nama', 'Parenting')->first()->kode)->toBe('PRN');

    // Kode duplikat ditolak.
    $this->actingAs($this->admin)
        ->post(route('admin.categories.store'), ['nama' => 'Parenting 2', 'kode' => 'PRN'])
        ->assertSessionHasErrors('kode');

    // Kode dengan spasi ditolak.
    $this->actingAs($this->admin)
        ->post(route('admin.categories.store'), ['nama' => 'Parenting 3', 'kode' => 'PR N'])
        ->assertSessionHasErrors('kode');
});

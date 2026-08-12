<?php

use App\Enums\ActivityAction;
use App\Enums\PromotionType;
use App\Models\ActivityLog;
use App\Models\Book;
use App\Models\Category;
use App\Models\Promotion;
use App\Models\TierDiscount;
use App\Models\User;
use Illuminate\Http\UploadedFile;

beforeEach(function (): void {
    $this->admin = User::factory()->admin()->create();
});

function importCsvUpload(string $content): UploadedFile
{
    $path = tempnam(sys_get_temp_dir(), 'csv-');
    file_put_contents($path, $content);

    return new UploadedFile($path, 'import.csv', 'text/csv', null, true);
}

// ── Kategori ──────────────────────────────────────────────────────────────

it('imports categories with kode', function (): void {
    $csv = "kode,nama\nALQ,Tafsir dan Tadabbur\nPRN,Parenting\n";

    $this->actingAs($this->admin)
        ->post(route('admin.categories.import'), ['file' => importCsvUpload($csv)])
        ->assertRedirect();

    expect(Category::count())->toBe(2)
        ->and(Category::where('nama', 'Tafsir dan Tadabbur')->first()->kode)->toBe('ALQ')
        ->and(Category::where('nama', 'Parenting')->first()->kode)->toBe('PRN');
});

it('updates category by kode and skips identical rows', function (): void {
    Category::create(['nama' => 'Lama', 'kode' => 'PRN']);

    $csv = "kode,nama\nPRN,Parenting\nPRN,Parenting\n";

    $this->actingAs($this->admin)
        ->post(route('admin.categories.import'), ['file' => importCsvUpload($csv)])
        ->assertRedirect();

    expect(Category::count())->toBe(1)
        ->and(Category::first()->nama)->toBe('Parenting');

    $page = $this->actingAs($this->admin)
        ->get(route('admin.categories.index'))
        ->viewData('page');

    expect($page['flash']['toast']['message'])->toContain('1 diperbarui, 1 dilewati');
});

it('logs category import without per-category observer logs', function (): void {
    $csv = "kode,nama\nPRN,Parenting\n";

    $this->actingAs($this->admin)
        ->post(route('admin.categories.import'), ['file' => importCsvUpload($csv)])
        ->assertRedirect();

    expect(ActivityLog::where('action', ActivityAction::CategoryImport->value)->count())->toBe(1)
        ->and(ActivityLog::where('action', ActivityAction::CategoryCreate->value)->count())->toBe(0);
});

// ── Tier Discount ─────────────────────────────────────────────────────────

it('imports tier discount rules globally', function (): void {
    $csv = "tier,min_qty,discount_percent\nguru,1,30\nbazaf,1,10\n";

    $this->actingAs($this->admin)
        ->post(route('admin.tier-discounts.import'), ['file' => importCsvUpload($csv)])
        ->assertRedirect();

    expect(TierDiscount::where('tier', 'guru')->where('min_qty', 1)->first()->discount_percent)->toBe(30)
        ->and(TierDiscount::where('tier', 'bazaf')->where('min_qty', 1)->first()->discount_percent)->toBe(10);
});

it('updates existing tier rule and rejects unknown tier', function (): void {
    TierDiscount::create(['tier' => 'guru', 'min_qty' => 1, 'discount_percent' => 20]);

    $csv = "tier,min_qty,discount_percent\nguru,1,35\nVIP,1,50\n";

    $this->actingAs($this->admin)
        ->post(route('admin.tier-discounts.import'), ['file' => importCsvUpload($csv)])
        ->assertRedirect();

    expect(TierDiscount::where('tier', 'guru')->where('min_qty', 1)->first()->discount_percent)->toBe(35)
        ->and(TierDiscount::where('tier', 'VIP')->exists())->toBeFalse();

    $page = $this->actingAs($this->admin)
        ->get(route('admin.tier-discounts.index'))
        ->viewData('page');

    expect($page['flash']['toast']['message'])->toContain('1 baris gagal');
});

// ── Promo (bundle) ────────────────────────────────────────────────────────

it('imports bundle promos with books resolved by judul', function (): void {
    $fami = Book::create(['judul' => 'Fami Bi Syauqin', 'penulis' => 'A', 'kode_sku' => 'ALQ000001', 'aktif' => true, 'stok' => 0]);
    $mudah = Book::create(['judul' => 'Mudah Tadabbur Juz Amma', 'penulis' => 'B', 'aktif' => true, 'stok' => 0]);

    $csv = "promo_name,promo_type,discount_percent,bundle_qty,komponen\n"
        ."Fami Bi Syauqin & Mudah Tadabbur Juz Amma,bundle,21,2,Fami Bi Syauqin|Mudah Tadabbur Juz Amma\n";

    $this->actingAs($this->admin)
        ->post(route('admin.promotions.import'), ['file' => importCsvUpload($csv)])
        ->assertRedirect();

    $promo = Promotion::first();

    expect($promo)->not->toBeNull()
        ->and($promo->promo_type)->toBe(PromotionType::Bundle)
        ->and($promo->discount_percentage)->toBe(21)
        ->and($promo->is_active)->toBeTrue()
        ->and($promo->start_date->toDateString())->toBe(now()->toDateString())
        ->and($promo->end_date->toDateString())->toBe(now()->addYear()->toDateString())
        ->and($promo->books->pluck('id')->sort()->values()->all())
        ->toBe(collect([$fami->id, $mudah->id])->sort()->values()->all());
});

it('creates promo without books and reports missing components', function (): void {
    $csv = "promo_name,promo_type,discount_percent,bundle_qty,komponen\n"
        ."Bundling Trilogi Palestina,bundle,10,3,Buku Tidak Ada|Juga Tidak Ada\n";

    $this->actingAs($this->admin)
        ->post(route('admin.promotions.import'), ['file' => importCsvUpload($csv)])
        ->assertRedirect();

    $promo = Promotion::first();

    expect($promo)->not->toBeNull()
        ->and($promo->books()->count())->toBe(0);

    $page = $this->actingAs($this->admin)
        ->get(route('admin.promotions.index'))
        ->viewData('page');

    expect($page['flash']['toast']['message'])->toContain('komponen tidak ditemukan');
});

it('updates existing promo by name and skips identical', function (): void {
    $book = Book::create(['judul' => 'Fami Bi Syauqin', 'penulis' => 'A', 'aktif' => true, 'stok' => 0]);
    $promo = Promotion::create([
        'promo_name' => 'Fami Bi Syauqin & X',
        'promo_type' => PromotionType::Bundle,
        'discount_percentage' => 10,
        'start_date' => now()->toDateString(),
        'end_date' => now()->addYear()->toDateString(),
    ]);
    $promo->books()->sync([$book->id]);

    $csv = "promo_name,promo_type,discount_percent,bundle_qty,komponen\n"
        ."Fami Bi Syauqin & X,bundle,15,2,Fami Bi Syauqin\n"
        ."Fami Bi Syauqin & X,bundle,15,2,Fami Bi Syauqin\n";

    $this->actingAs($this->admin)
        ->post(route('admin.promotions.import'), ['file' => importCsvUpload($csv)])
        ->assertRedirect();

    expect(Promotion::count())->toBe(1)
        ->and($promo->fresh()->discount_percentage)->toBe(15);

    $page = $this->actingAs($this->admin)
        ->get(route('admin.promotions.index'))
        ->viewData('page');

    expect($page['flash']['toast']['message'])->toContain('1 diperbarui, 1 dilewati');
});

it('preserves manually completed components and dates on re-import', function (): void {
    $book = Book::create(['judul' => 'Fami Bi Syauqin', 'penulis' => 'A', 'aktif' => true, 'stok' => 0]);
    $promo = Promotion::create([
        'promo_name' => 'Bundling Trilogi Palestina',
        'promo_type' => PromotionType::Bundle,
        'discount_percentage' => 10,
        'start_date' => now()->subDays(10)->toDateString(),
        'end_date' => now()->addDays(100)->toDateString(),
        'is_active' => false,
    ]);
    $promo->books()->sync([$book->id]); // komponen dilengkapi manual

    // Re-import dengan komponen kosong + diskon beda.
    $csv = "promo_name,promo_type,discount_percent,komponen\n"
        ."Bundling Trilogi Palestina,bundle,15,\n";

    $this->actingAs($this->admin)
        ->post(route('admin.promotions.import'), ['file' => importCsvUpload($csv)])
        ->assertRedirect();

    $promo->refresh();

    expect($promo->discount_percentage)->toBe(15)
        ->and($promo->books()->pluck('books.id')->all())->toBe([$book->id]) // TIDAK terhapus
        ->and($promo->start_date->toDateString())->toBe(now()->subDays(10)->toDateString()) // tanggal dipertahankan
        ->and($promo->end_date->toDateString())->toBe(now()->addDays(100)->toDateString())
        ->and($promo->is_active)->toBeFalse();
});

it('rejects non-bundle promo types', function (): void {
    $csv = "promo_name,promo_type,discount_percent,bundle_qty,komponen\n"
        ."Diskon 10%,percentage,10,,\n";

    $this->actingAs($this->admin)
        ->post(route('admin.promotions.import'), ['file' => importCsvUpload($csv)])
        ->assertRedirect();

    expect(Promotion::count())->toBe(0);
});

// ── Shared ────────────────────────────────────────────────────────────────

it('rejects non csv files on all import routes', function (): void {
    $file = UploadedFile::fake()->create('data.pdf', 100, 'application/pdf');

    foreach (['admin.categories.import', 'admin.promotions.import', 'admin.tier-discounts.import'] as $route) {
        $this->actingAs($this->admin)
            ->post(route($route), ['file' => $file])
            ->assertSessionHasErrors('file');
    }
});

it('requires admin on all import routes', function (): void {
    $customer = User::factory()->create();

    foreach (['admin.categories.import', 'admin.promotions.import', 'admin.tier-discounts.import'] as $route) {
        $this->actingAs($customer)
            ->post(route($route), ['file' => importCsvUpload("a,b\n")])
            ->assertForbidden();
    }
});

// ── Header Validation ────────────────────────────────────────────────────

it('rejects category import with wrong columns', function (): void {
    // Kolom buku, bukan kolom kategori (kode, nama)
    $csv = "judul,penulis,harga\nBuku A,Penulis A,50000\n";

    $this->actingAs($this->admin)
        ->post(route('admin.categories.import'), ['file' => importCsvUpload($csv)])
        ->assertSessionHasErrors('file');

    expect(Category::count())->toBe(0);
});

it('rejects tier discount import with wrong columns', function (): void {
    // Kolom kategori, bukan tier discount
    $csv = "kode,nama\nPRN,Parenting\n";

    $this->actingAs($this->admin)
        ->post(route('admin.tier-discounts.import'), ['file' => importCsvUpload($csv)])
        ->assertSessionHasErrors('file');

    expect(TierDiscount::count())->toBe(0);
});

it('rejects promotion import without promo_name column', function (): void {
    $csv = "nama,diskon\nPromo A,10\n";

    $this->actingAs($this->admin)
        ->post(route('admin.promotions.import'), ['file' => importCsvUpload($csv)])
        ->assertSessionHasErrors('file');

    expect(Promotion::count())->toBe(0);
});

it('rejects book import with completely wrong columns', function (): void {
    // Kolom pelanggan, bukan kolom buku
    $csv = "penerima,tujuan,kota/kabupaten\nBudi,Jawa Barat,Bandung\n";

    $this->actingAs($this->admin)
        ->post(route('admin.books.import'), ['file' => bookCsvUpload($csv)])
        ->assertSessionHasErrors('file');

    expect(Book::count())->toBe(0);
});

it('accepts category import with correct columns (kode, nama)', function (): void {
    $csv = "kode,nama\nALQ,Tafsir dan Tadabbur\n";

    $this->actingAs($this->admin)
        ->post(route('admin.categories.import'), ['file' => importCsvUpload($csv)])
        ->assertRedirect()
        ->assertSessionDoesntHaveErrors('file');

    expect(Category::count())->toBe(1);
});

it('accepts tier discount import with correct columns', function (): void {
    $csv = "tier,min_qty,discount_percent\nguru,1,30\n";

    $this->actingAs($this->admin)
        ->post(route('admin.tier-discounts.import'), ['file' => importCsvUpload($csv)])
        ->assertRedirect()
        ->assertSessionDoesntHaveErrors('file');

    expect(TierDiscount::count())->toBe(1);
});

it('accepts promotion import with correct columns', function (): void {
    $csv = "promo_name,promo_type,discount_percent,komponen\nTest Promo,bundle,10,\n";

    $this->actingAs($this->admin)
        ->post(route('admin.promotions.import'), ['file' => importCsvUpload($csv)])
        ->assertRedirect()
        ->assertSessionDoesntHaveErrors('file');

    expect(Promotion::count())->toBe(1);
});

it('rejects customer import with wrong columns', function (): void {
    // Kolom buku, bukan kolom pelanggan
    $csv = "judul,penulis,harga\nBuku A,Penulis A,50000\n";

    $this->actingAs($this->admin)
        ->post(route('admin.customers.import'), ['file' => importCsvUpload($csv)])
        ->assertSessionHasErrors('file');
});

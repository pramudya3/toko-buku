<?php

use App\Models\User;

beforeEach(function (): void {
    $this->admin = User::factory()->admin()->create();
});

it('downloads csv templates for every import menu', function (string $type, string $filename, string $header): void {
    $response = $this->actingAs($this->admin)
        ->get(route('admin.imports.template', $type));

    $response->assertOk()
        ->assertHeader('Content-Type', 'text/csv; charset=UTF-8')
        ->assertHeader('Content-Disposition', "attachment; filename={$filename}");

    // Konten = header saja (1 baris, tanpa baris contoh).
    $content = (string) $response->streamedContent();

    expect($content)->toContain($header);
    $lines = array_filter(explode("\n", trim($content)));
    expect(count($lines))->toBe(1);
})->with([
    'customers' => ['customers', 'template-pelanggan.csv', 'penerima,tujuan,kota/kabupaten,kecamatan,kelurahan'],
    'books' => ['books', 'template-buku.csv', 'kategori,kode,judul,penulis,harga_jual,harga_beli'],
    'categories' => ['categories', 'template-kategori.csv', 'kode,nama'],
    'promotions' => ['promotions', 'template-promo.csv', 'promo_name,promo_type,discount_percent,komponen'],
    'tier-discounts' => ['tier-discounts', 'template-tier-discount.csv', 'tier,min_qty,discount_percent'],
]);

it('returns 404 for unknown template type', function (): void {
    $this->actingAs($this->admin)
        ->get(route('admin.imports.template', 'bogus'))
        ->assertNotFound();
});

it('requires admin to download templates', function (): void {
    $customer = User::factory()->create();

    $this->actingAs($customer)
        ->get(route('admin.imports.template', 'books'))
        ->assertForbidden();
});

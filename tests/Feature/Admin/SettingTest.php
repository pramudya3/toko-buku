<?php

use App\Models\Courier;
use App\Models\PaymentMethod as PaymentMethodModel;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    $this->admin = User::factory()->admin()->create();
});

it('redirects the legacy settings url to the lembaga page', function (): void {
    $this->actingAs($this->admin)
        ->get(route('admin.settings.index'))
        ->assertRedirect(route('admin.settings.lembaga'));
});

it('shows the lembaga page with address values', function (): void {
    Setting::set('origin_postal_code', '65141');
    Setting::set('store_provinsi', 'Jawa Timur');

    $this->actingAs($this->admin)
        ->get(route('admin.settings.lembaga'))
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->component('admin/settings/Lembaga')
            ->where('origin_postal_code', '65141')
            ->where('provinsi', 'Jawa Timur'));
});

it('persists address via the lembaga page and composes store_alamat', function (): void {
    $this->actingAs($this->admin)
        ->put(route('admin.settings.lembaga.update'), [
            'nama_lembaga' => 'Toko Buku Nusantara',
            'origin_postal_code' => '65144',
            'alamat_jalan' => 'Jl. Merdeka No. 1',
            'provinsi' => 'Jawa Timur',
            'kabupaten_kota' => 'Kota Malang',
            'kecamatan' => 'Lowokwaru',
            'kelurahan' => 'Merjosari',
        ])
        ->assertRedirect();

    expect(Setting::get('origin_postal_code'))->toBe('65144')
        ->and(Setting::get('store_provinsi'))->toBe('Jawa Timur')
        ->and(Setting::get('store_kelurahan'))->toBe('Merjosari')
        ->and(Setting::get('store_alamat'))->toBe('Jl. Merdeka No. 1, Merjosari, Lowokwaru, Kota Malang, Jawa Timur, 65144');
});

it('requires the origin postal code', function (): void {
    $this->actingAs($this->admin)
        ->put(route('admin.settings.lembaga.update'), [
            'nama_lembaga' => 'Toko Buku Nusantara',
            'origin_postal_code' => '',
        ])
        ->assertSessionHasErrors('origin_postal_code');

    expect(Setting::count())->toBe(0);
});

it('blocks customers from store settings', function (): void {
    $customer = User::factory()->customer()->create();

    $this->actingAs($customer)
        ->get(route('admin.settings.lembaga'))
        ->assertForbidden();
});

it('shows the lembaga settings page with saved values', function (): void {
    Setting::set('store_nama_lembaga', 'Toko Buku Nusantara');

    $this->actingAs($this->admin)
        ->get(route('admin.settings.lembaga'))
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->component('admin/settings/Lembaga')
            ->where('nama_lembaga', 'Toko Buku Nusantara'));
});

it('persists lembaga settings', function (): void {
    $this->actingAs($this->admin)
        ->put(route('admin.settings.lembaga.update'), [
            'nama_lembaga' => 'Toko Buku Nusantara',
            'origin_postal_code' => '65144',
            'tagline' => 'Membaca untuk semua',
            'telepon' => '081234567890',
            'email' => 'halo@tokobuku.test',
            'jam_operasional' => 'Senin–Sabtu, 08.00–17.00',
            'deskripsi' => 'Toko buku terlengkap.',
        ])
        ->assertRedirect();

    expect(Setting::get('store_nama_lembaga'))->toBe('Toko Buku Nusantara')
        ->and(Setting::get('store_email'))->toBe('halo@tokobuku.test')
        ->and(Setting::get('store_deskripsi'))->toBe('Toko buku terlengkap.');
});

it('requires the lembaga name', function (): void {
    $this->actingAs($this->admin)
        ->put(route('admin.settings.lembaga.update'), [
            'nama_lembaga' => '',
        ])
        ->assertSessionHasErrors('nama_lembaga');
});

it('shows the api key page without leaking the key', function (): void {
    Setting::setSecret('biteship_api_key', 'biteship_test_rahasia_1234567890');

    $this->actingAs($this->admin)
        ->get(route('admin.settings.api-key'))
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->component('admin/settings/ApiKey')
            ->where('has_api_key', true)
            ->where('key_masked', '••••••7890')
            ->where('key_source', 'database')
            ->whereNot('key_masked', 'biteship_test_rahasia_1234567890'));
});

it('stores the api key encrypted and never echoes it back', function (): void {
    $secret = 'biteship_production_abcdefghijklmnopqrstuvwxyz123456';

    $this->actingAs($this->admin)
        ->put(route('admin.settings.api-key.update'), [
            'api_key' => $secret,
        ])
        ->assertRedirect();

    // Tersimpan terenkripsi — bukan plaintext.
    $row = Setting::where('key', 'biteship_api_key')->first();
    expect($row->value)->not->toBe($secret);
    expect(Setting::getSecret('biteship_api_key'))->toBe($secret);

    // Response halaman tidak pernah memuat key asli.
    $this->get(route('admin.settings.api-key'))
        ->assertSuccessful()
        ->assertDontSee($secret);
});

it('keeps the existing api key when the field is empty', function (): void {
    Setting::setSecret('biteship_api_key', 'biteship_test_existing_9876543210');

    $this->actingAs($this->admin)
        ->put(route('admin.settings.api-key.update'), [
            'api_key' => '',
        ])
        ->assertRedirect();

    expect(Setting::getSecret('biteship_api_key'))->toBe('biteship_test_existing_9876543210');
});

it('clears the api key only via explicit action', function (): void {
    Setting::setSecret('biteship_api_key', 'biteship_test_existing_9876543210');

    $this->actingAs($this->admin)
        ->put(route('admin.settings.api-key.update'), [
            'clear_key' => '1',
        ])
        ->assertRedirect();

    expect(Setting::getSecret('biteship_api_key'))->toBeNull();
});

it('rejects short api keys', function (): void {
    $this->actingAs($this->admin)
        ->put(route('admin.settings.api-key.update'), [
            'api_key' => 'pendek',
        ])
        ->assertSessionHasErrors('api_key');
});

it('persists lembaga about-page fields', function (): void {
    $this->actingAs($this->admin)
        ->put(route('admin.settings.lembaga.update'), [
            'nama_lembaga' => 'Toko Buku Nusantara',
            'origin_postal_code' => '65144',
            'deskripsi' => 'Cerita kami',
            'visi' => 'Menjadi toko buku pilihan.',
            'misi' => "Menyediakan buku berkualitas\nMelayani dengan ramah",
            'keamanan' => 'Transaksi aman dan terenkripsi.',
            'syarat' => "Pembelian minimal 1 eksemplar\nRetur dalam 7 hari",
        ])
        ->assertRedirect();

    expect(Setting::get('store_visi'))->toBe('Menjadi toko buku pilihan.')
        ->and(Setting::get('store_misi'))->toContain('Melayani dengan ramah')
        ->and(Setting::get('store_keamanan'))->toBe('Transaksi aman dan terenkripsi.')
        ->and(Setting::get('store_syarat'))->toContain('Retur dalam 7 hari');
});

it('shows the ekspedisi page with couriers from the table', function (): void {
    Courier::factory()->create(['code' => 'jne', 'name' => 'JNE', 'is_active' => true]);
    Courier::factory()->create(['code' => 'jnt', 'name' => 'J&T Express', 'is_active' => false]);

    $this->actingAs($this->admin)
        ->get(route('admin.settings.ekspedisi'))
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->component('admin/settings/Ekspedisi')
            ->has('couriers', 2)
            ->where('couriers.0.code', 'jnt')
            ->where('couriers.0.is_active', false)
            ->where('couriers.1.code', 'jne')
            ->where('couriers.1.is_active', true));
});

it('shows the payment methods page with methods from the table', function (): void {
    PaymentMethodModel::factory()->create(['code' => 'transfer', 'name' => 'Transfer', 'is_active' => true]);
    PaymentMethodModel::factory()->create(['code' => 'qris', 'name' => 'QRIS', 'is_active' => false]);

    $this->actingAs($this->admin)
        ->get(route('admin.settings.pembayaran'))
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->component('admin/settings/Pembayaran')
            ->has('paymentMethods', 2)
            ->where('paymentMethods.0.code', 'qris')
            ->where('paymentMethods.0.is_active', false)
            ->where('paymentMethods.1.code', 'transfer')
            ->where('paymentMethods.1.is_active', true));
});

it('filters order couriers and payment options by active records', function (): void {
    Courier::factory()->create(['code' => 'jne', 'name' => 'JNE', 'is_active' => true]);
    Courier::factory()->create(['code' => 'jnt', 'name' => 'J&T Express', 'is_active' => false]);
    PaymentMethodModel::factory()->create(['code' => 'transfer', 'name' => 'Transfer', 'is_active' => true]);
    PaymentMethodModel::factory()->create(['code' => 'cod', 'name' => 'COD', 'is_active' => false]);

    $this->actingAs($this->admin)
        ->get(route('admin.orders.create'))
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->where('couriers', ['jne' => 'JNE'])
            ->where('paymentOptions', ['transfer' => 'Transfer']));
});

it('rejects disabled payment methods on checkout', function (): void {
    PaymentMethodModel::factory()->create(['code' => 'transfer', 'name' => 'Transfer', 'is_active' => false]);

    $this->actingAs($this->admin)
        ->post(route('admin.orders.store'), [
            'nama_pembeli' => 'Budi',
            'metode_bayar' => 'transfer',
            'items' => [],
        ])
        ->assertSessionHasErrors('metode_bayar');
});

it('shares store name and logo url for the sidebar', function (): void {
    Setting::set('store_nama_lembaga', 'Toko Buku Nusantara');
    Setting::set('store_logo_url', 'https://cdn.example.com/logos/logo.png');

    $this->actingAs($this->admin)
        ->get(route('admin.dashboard'))
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->where('storeName', 'Toko Buku Nusantara')
            ->where('storeLogoUrl', 'https://cdn.example.com/logos/logo.png'));
});

it('stores the lembaga logo and exposes its url', function (): void {
    Storage::fake('r2');

    $this->actingAs($this->admin)
        ->put(route('admin.settings.lembaga.update'), [
            'nama_lembaga' => 'Toko Buku Nusantara',
            'origin_postal_code' => '65144',
            'logo' => UploadedFile::fake()->image('logo.png', 200, 200),
        ])
        ->assertRedirect();

    $url = Setting::get('store_logo_url');
    expect($url)->not->toBeNull();
    Storage::disk('r2')->assertExists('logos/'.basename($url));
});

it('replaces the lembaga logo and deletes the old file', function (): void {
    Storage::fake('r2');
    Setting::set('store_logo_url', Storage::disk('r2')->url('logos/lama.png'));
    Storage::disk('r2')->put('logos/lama.png', 'lama');

    $this->actingAs($this->admin)
        ->put(route('admin.settings.lembaga.update'), [
            'nama_lembaga' => 'Toko Buku Nusantara',
            'origin_postal_code' => '65144',
            'logo' => UploadedFile::fake()->image('baru.png', 200, 200),
        ])
        ->assertRedirect();

    Storage::disk('r2')->assertMissing('logos/lama.png');
    expect(Setting::get('store_logo_url'))->not->toBeNull();
});

it('removes the lembaga logo via explicit action', function (): void {
    Storage::fake('r2');
    Setting::set('store_logo_url', Storage::disk('r2')->url('logos/logo.png'));
    Storage::disk('r2')->put('logos/logo.png', 'gambar');

    $this->actingAs($this->admin)
        ->put(route('admin.settings.lembaga.update'), [
            'nama_lembaga' => 'Toko Buku Nusantara',
            'origin_postal_code' => '65144',
            'hapus_logo' => '1',
        ])
        ->assertRedirect();

    Storage::disk('r2')->assertMissing('logos/logo.png');
    expect(Setting::get('store_logo_url'))->toBeNull();
});

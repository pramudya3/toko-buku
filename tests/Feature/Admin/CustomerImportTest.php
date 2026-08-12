<?php

use App\Enums\ActivityAction;
use App\Enums\CustomerTier;
use App\Models\ActivityLog;
use App\Models\City;
use App\Models\District;
use App\Models\Province;
use App\Models\User;
use App\Models\Village;
use Illuminate\Http\UploadedFile;

beforeEach(function (): void {
    $this->admin = User::factory()->admin()->create();

    // Wilayah lokal mini untuk resolve alamat import.
    Province::forceCreate(['code' => '32', 'name' => 'JAWA BARAT']);
    City::forceCreate(['code' => '3273', 'province_code' => '32', 'name' => 'KOTA BANDUNG']);
    District::forceCreate(['code' => '3273111', 'city_code' => '3273', 'name' => 'PANYILEUKAN', 'kode_pos' => '40614']);
    Village::forceCreate(['code' => '3273111002', 'district_code' => '3273111', 'name' => 'CIPADUNG KIDUL']);

    Province::forceCreate(['code' => '31', 'name' => 'DKI JAKARTA']);
    City::forceCreate(['code' => '3174', 'province_code' => '31', 'name' => 'KOTA JAKARTA SELATAN']);
    District::forceCreate(['code' => '3174060', 'city_code' => '3174', 'name' => 'JAGAKARSA', 'kode_pos' => '12620']);
    Village::forceCreate(['code' => '3174060003', 'district_code' => '3174060', 'name' => 'JAGAKARSA', 'kode_pos' => '12530']);

    Province::forceCreate(['code' => '35', 'name' => 'JAWA TIMUR']);
    City::forceCreate(['code' => '3506', 'province_code' => '35', 'name' => 'KABUPATEN KEDIRI']);
    District::forceCreate(['code' => '3506140', 'city_code' => '3506', 'name' => 'PARE', 'kode_pos' => '64213']);
    Village::forceCreate(['code' => '3506140001', 'district_code' => '3506140', 'name' => 'PARE']);
});

/**
 * Buat file CSV untuk di-upload dalam test.
 */
function csvUpload(string $content): UploadedFile
{
    $path = tempnam(sys_get_temp_dir(), 'csv-');
    file_put_contents($path, $content);

    return new UploadedFile($path, 'pelanggan.csv', 'text/csv', null, true);
}

it('imports customers from csv with wilayah resolution', function (): void {
    $csv = "PENERIMA,TUJUAN,Kota/Kabupaten,Kecamatan,Kelurahan\n"
        ."Ustadzah Mia Nur Amalia,Jawa Barat, Kota Bandung, Panyileukan, Cipadung Kidul\n"
        ."\"Kusyaeni, M.Pd.\",Jawa Barat, Kota Bandung, Panyileukan, Cipadung Kidul\n";

    $this->actingAs($this->admin)
        ->post(route('admin.customers.import'), ['file' => csvUpload($csv)])
        ->assertRedirect();

    $user = User::where('name', 'Ustadzah Mia Nur Amalia')->first();

    expect($user)->not->toBeNull()
        ->and($user->email)->toBeNull()
        ->and($user->password)->not->toBeNull()
        ->and($user->is_admin)->toBeFalse()
        ->and($user->is_active)->toBeTrue()
        ->and($user->status_pelanggan)->toBe(CustomerTier::Reguler)
        ->and($user->provinsi)->toBe('JAWA BARAT')
        ->and($user->kabupaten_kota)->toBe('KOTA BANDUNG')
        ->and($user->kecamatan)->toBe('PANYILEUKAN')
        ->and($user->kelurahan)->toBe('CIPADUNG KIDUL')
        ->and($user->village_code)->toBe('3273111002')
        ->and($user->kode_pos)->toBe('40614');

    expect(User::where('name', 'Kusyaeni, M.Pd.')->exists())->toBeTrue();
});

it('normalizes province and city names to canonical wilayah data', function (): void {
    $csv = "PENERIMA,TUJUAN,Kota/Kabupaten,Kecamatan,Kelurahan\n"
        ."Yuni Koerniawati,Dki Jakarta, Kota Jakarta Selatan, Jagakarsa, Jagakarsa\n"
        ."Indah Naili,Jawa Timur, Kab. Kediri, Pare, Pare\n";

    $this->actingAs($this->admin)
        ->post(route('admin.customers.import'), ['file' => csvUpload($csv)])
        ->assertRedirect();

    $jakarta = User::where('name', 'Yuni Koerniawati')->first();

    expect($jakarta->provinsi)->toBe('DKI JAKARTA')
        ->and($jakarta->kabupaten_kota)->toBe('KOTA JAKARTA SELATAN')
        ->and($jakarta->kecamatan)->toBe('JAGAKARSA')
        ->and($jakarta->kelurahan)->toBe('JAGAKARSA')
        ->and($jakarta->village_code)->toBe('3174060003')
        ->and($jakarta->kode_pos)->toBe('12530'); // kode pos kelurahan menang atas kecamatan (12620)

    $kediri = User::where('name', 'Indah Naili')->first();

    expect($kediri->kabupaten_kota)->toBe('KABUPATEN KEDIRI')
        ->and($kediri->kode_pos)->toBe('64213');
});

it('updates address when customer with same name exists (Opsi A)', function (): void {
    $customer = User::factory()->create([
        'name' => 'Bazaf Bogor',
        'provinsi' => 'LAMA',
        'kabupaten_kota' => 'KOTA LAMA',
    ]);

    $csv = "PENERIMA,TUJUAN,Kota/Kabupaten,Kecamatan,Kelurahan\n"
        ."Bazaf Bogor,Jawa Barat, Kota Bandung, Panyileukan, Cipadung Kidul\n";

    $this->actingAs($this->admin)
        ->post(route('admin.customers.import'), ['file' => csvUpload($csv)])
        ->assertRedirect();

    expect(User::where('name', 'Bazaf Bogor')->count())->toBe(1);

    $customer->refresh();

    expect($customer->provinsi)->toBe('JAWA BARAT')
        ->and($customer->kabupaten_kota)->toBe('KOTA BANDUNG')
        ->and($customer->kelurahan)->toBe('CIPADUNG KIDUL')
        ->and($customer->kode_pos)->toBe('40614');
});

it('skips duplicate rows with identical name and address', function (): void {
    $csv = "PENERIMA,TUJUAN,Kota/Kabupaten,Kecamatan,Kelurahan\n"
        ."Bazaf Gresik,Jawa Barat, Kota Bandung, Panyileukan, Cipadung Kidul\n"
        ."Bazaf Gresik,Jawa Barat, Kota Bandung, Panyileukan, Cipadung Kidul\n";

    $this->actingAs($this->admin)
        ->post(route('admin.customers.import'), ['file' => csvUpload($csv)])
        ->assertRedirect();

    expect(User::where('name', 'Bazaf Gresik')->count())->toBe(1);

    $page = $this->actingAs($this->admin)
        ->get(route('admin.customers.index'))
        ->viewData('page');

    expect($page['flash']['toast'])->toMatchArray([
        'type' => 'success',
    ])->and($page['flash']['toast']['message'])->toContain('1 pelanggan baru, 0 diperbarui, 1 dilewati');
});

it('imports rows with unknown wilayah using raw normalized names', function (): void {
    $csv = "PENERIMA,TUJUAN,Kota/Kabupaten,Kecamatan,Kelurahan\n"
        ."Toko Batam,Kepulauan Riau, Kota Batam, Sekupang, Tanjung Riau\n";

    $this->actingAs($this->admin)
        ->post(route('admin.customers.import'), ['file' => csvUpload($csv)])
        ->assertRedirect();

    $user = User::where('name', 'Toko Batam')->first();

    expect($user)->not->toBeNull()
        ->and($user->provinsi)->toBe('KEPULAUAN RIAU')
        ->and($user->kabupaten_kota)->toBe('KOTA BATAM')
        ->and($user->kecamatan)->toBe('SEKUPANG')
        ->and($user->kelurahan)->toBe('TANJUNG RIAU')
        ->and($user->kode_pos)->toBeNull()
        ->and($user->village_code)->toBeNull();
});

it('reports rows with empty names as errors and imports the rest', function (): void {
    $csv = "PENERIMA,TUJUAN,Kota/Kabupaten,Kecamatan,Kelurahan\n"
        .",Jawa Barat, Kota Bandung, Panyileukan, Cipadung Kidul\n"
        ."Maulana,Jawa Barat, Kota Bandung, Panyileukan, Cipadung Kidul\n";

    $this->actingAs($this->admin)
        ->post(route('admin.customers.import'), ['file' => csvUpload($csv)])
        ->assertRedirect();

    expect(User::where('name', 'Maulana')->exists())->toBeTrue()
        ->and(User::count())->toBe(2); // admin + Maulana

    $page = $this->actingAs($this->admin)
        ->get(route('admin.customers.index'))
        ->viewData('page');

    expect($page['flash']['toast']['message'])->toContain('1 baris gagal');
});

it('rejects non csv files', function (): void {
    $file = UploadedFile::fake()->create('data.pdf', 100, 'application/pdf');

    $this->actingAs($this->admin)
        ->post(route('admin.customers.import'), ['file' => $file])
        ->assertSessionHasErrors('file');

    expect(User::count())->toBe(1); // hanya admin
});

it('requires admin to import', function (): void {
    $customer = User::factory()->create();

    $this->actingAs($customer)
        ->post(route('admin.customers.import'), [
            'file' => csvUpload("PENERIMA\nSiapa\n"),
        ])
        ->assertForbidden();
});

it('allows many customers without email', function (): void {
    User::factory()->create(['email' => null]);

    $csv = "PENERIMA,TUJUAN,Kota/Kabupaten,Kecamatan,Kelurahan\n"
        ."Satu,Jawa Barat, Kota Bandung, Panyileukan, Cipadung Kidul\n"
        ."Dua,Jawa Barat, Kota Bandung, Panyileukan, Cipadung Kidul\n";

    $this->actingAs($this->admin)
        ->post(route('admin.customers.import'), ['file' => csvUpload($csv)])
        ->assertRedirect();

    expect(User::whereNull('email')->count())->toBe(3);
});

it('stores a customer without email via the form', function (): void {
    $this->actingAs($this->admin)
        ->post(route('admin.customers.store'), [
            'name' => 'Tanpa Email',
            'password' => 'password123',
            'status_pelanggan' => CustomerTier::Reguler->value,
        ])
        ->assertRedirect(route('admin.customers.index'));

    expect(User::where('name', 'Tanpa Email')->first()->email)->toBeNull();
});

it('logs a single import activity without per-user observer logs', function (): void {
    $csv = "PENERIMA,TUJUAN,Kota/Kabupaten,Kecamatan,Kelurahan\n"
        ."Satu,Jawa Barat, Kota Bandung, Panyileukan, Cipadung Kidul\n"
        ."Dua,Jawa Barat, Kota Bandung, Panyileukan, Cipadung Kidul\n";

    $this->actingAs($this->admin)
        ->post(route('admin.customers.import'), ['file' => csvUpload($csv)])
        ->assertRedirect();

    $log = ActivityLog::where('action', ActivityAction::CustomerImport->value)->first();

    expect($log)->not->toBeNull()
        ->and($log->description)->toContain('2 dibuat')
        ->and($log->user_id)->toBe($this->admin->id)
        ->and(ActivityLog::where('action', ActivityAction::UserCreate->value)->count())->toBe(1); // hanya admin
});

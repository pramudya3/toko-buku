<?php

use App\Enums\VoucherScope;
use App\Enums\VoucherType;
use App\Models\Order;
use App\Models\User;
use App\Models\Voucher;
use App\Models\VoucherUsage;

beforeEach(function (): void {
    $this->admin = User::factory()->admin()->create();
});

/**
 * Tandai voucher sudah dipakai di sebuah order.
 */
function markVoucherUsed(Voucher $voucher, User $user): VoucherUsage
{
    return VoucherUsage::create([
        'voucher_id' => $voucher->getKey(),
        'order_id' => Order::factory()->create()->getKey(),
        'user_id' => $user->getKey(),
    ]);
}

it('creates a percentage voucher with auto-generated kode', function (): void {
    $this->actingAs($this->admin)
        ->post(route('admin.vouchers.store'), [
            'nama' => 'Diskon Lebaran',
            'kode' => '',
            'voucher_type' => VoucherType::Percentage->value,
            'discount_scope' => VoucherScope::Item->value,
            'discount_percentage' => 15,
            'min_order_amount' => 100000,
            'max_uses' => 50,
            'max_uses_per_user' => 1,
            'start_date' => now()->toDateString(),
            'end_date' => now()->addDays(7)->toDateString(),
            'is_active' => true,
        ])
        ->assertRedirect(route('admin.vouchers.index'));

    $voucher = Voucher::where('nama', 'Diskon Lebaran')->first();

    expect($voucher)->not->toBeNull()
        ->and($voucher->voucher_type)->toBe(VoucherType::Percentage)
        ->and($voucher->discount_percentage)->toBe(15)
        ->and($voucher->kode)->toBe('DISKON-LEBARAN');
});

it('creates a fixed voucher keeping the given kode uppercased', function (): void {
    $this->actingAs($this->admin)
        ->post(route('admin.vouchers.store'), [
            'nama' => 'Potongan Spesial',
            'kode' => 'potong20',
            'voucher_type' => VoucherType::Fixed->value,
            'discount_scope' => VoucherScope::Item->value,
            'discount_value' => 25000,
            'start_date' => now()->toDateString(),
            'end_date' => now()->addDays(7)->toDateString(),
            'is_active' => true,
        ])
        ->assertRedirect(route('admin.vouchers.index'));

    $voucher = Voucher::where('nama', 'Potongan Spesial')->first();

    expect($voucher->kode)->toBe('POTONG20')
        ->and($voucher->discount_value)->toBe(25000);
});

it('stores a voucher with empty min_order_amount as zero', function (): void {
    // Browser mengirim string kosong → null → jangan menabrak NOT NULL.
    $this->actingAs($this->admin)
        ->post(route('admin.vouchers.store'), [
            'nama' => 'Tanpa Minimal Belanja',
            'voucher_type' => VoucherType::Percentage->value,
            'discount_scope' => VoucherScope::Item->value,
            'discount_percentage' => 10,
            'min_order_amount' => '',
            'start_date' => now()->toDateString(),
            'end_date' => now()->addDays(1)->toDateString(),
            'is_active' => true,
        ])
        ->assertRedirect(route('admin.vouchers.index'));

    $voucher = Voucher::where('nama', 'Tanpa Minimal Belanja')->first();

    expect($voucher)->not->toBeNull()
        ->and($voucher->min_order_amount)->toBe(0);
});

it('validates end date is after start date', function (): void {
    $this->actingAs($this->admin)
        ->post(route('admin.vouchers.store'), [
            'nama' => 'Salah Tanggal',
            'voucher_type' => VoucherType::Percentage->value,
            'discount_scope' => VoucherScope::Item->value,
            'discount_percentage' => 10,
            'start_date' => now()->addDays(5)->toDateString(),
            'end_date' => now()->toDateString(),
        ])
        ->assertSessionHasErrors('end_date');
});

it('validates discount field per voucher type', function (): void {
    // Fixed tanpa discount_value.
    $this->actingAs($this->admin)
        ->post(route('admin.vouchers.store'), [
            'nama' => 'Fixed Tanpa Nilai',
            'voucher_type' => VoucherType::Fixed->value,
            'discount_scope' => VoucherScope::Item->value,
            'start_date' => now()->toDateString(),
            'end_date' => now()->addDays(1)->toDateString(),
        ])
        ->assertSessionHasErrors('discount_value');

    // Percentage tanpa discount_percentage.
    $this->actingAs($this->admin)
        ->post(route('admin.vouchers.store'), [
            'nama' => 'Persen Tanpa Nilai',
            'voucher_type' => VoucherType::Percentage->value,
            'discount_scope' => VoucherScope::Item->value,
            'start_date' => now()->toDateString(),
            'end_date' => now()->addDays(1)->toDateString(),
        ])
        ->assertSessionHasErrors('discount_percentage');
});

it('rejects a duplicate voucher kode', function (): void {
    $voucher = Voucher::factory()->create(['kode' => 'DUPLIKAT']);

    $this->actingAs($this->admin)
        ->post(route('admin.vouchers.store'), [
            'nama' => 'Voucher Baru',
            'kode' => 'duplikat',
            'voucher_type' => VoucherType::Percentage->value,
            'discount_scope' => VoucherScope::Item->value,
            'discount_percentage' => 10,
            'start_date' => now()->toDateString(),
            'end_date' => now()->addDays(1)->toDateString(),
        ])
        ->assertSessionHasErrors('kode');

    expect(Voucher::where('nama', 'Voucher Baru')->doesntExist())->toBeTrue();
});

it('creates an ongkir-scope voucher', function (): void {
    $this->actingAs($this->admin)
        ->post(route('admin.vouchers.store'), [
            'nama' => 'Gratis Ongkir',
            'voucher_type' => VoucherType::Percentage->value,
            'discount_scope' => VoucherScope::Ongkir->value,
            'discount_percentage' => 50,
            'start_date' => now()->toDateString(),
            'end_date' => now()->addDays(7)->toDateString(),
            'is_active' => true,
        ])
        ->assertRedirect(route('admin.vouchers.index'));

    $voucher = Voucher::where('nama', 'Gratis Ongkir')->first();

    expect($voucher->discount_scope)->toBe(VoucherScope::Ongkir);
});

it('validates discount_scope is required', function (): void {
    $this->actingAs($this->admin)
        ->post(route('admin.vouchers.store'), [
            'nama' => 'Tanpa Scope',
            'voucher_type' => VoucherType::Percentage->value,
            'discount_percentage' => 10,
            'start_date' => now()->toDateString(),
            'end_date' => now()->addDays(1)->toDateString(),
        ])
        ->assertSessionHasErrors('discount_scope');
});

it('toggles voucher active state', function (): void {
    $voucher = Voucher::factory()->create(['is_active' => true]);

    $this->actingAs($this->admin)
        ->patch(route('admin.vouchers.toggle', $voucher))
        ->assertRedirect();

    expect($voucher->fresh()->is_active)->toBeFalse();

    $this->actingAs($this->admin)
        ->patch(route('admin.vouchers.toggle', $voucher))
        ->assertRedirect();

    expect($voucher->fresh()->is_active)->toBeTrue();
});

it('updates a voucher', function (): void {
    $voucher = Voucher::factory()->percentage(10)->create();

    $this->actingAs($this->admin)
        ->put(route('admin.vouchers.update', $voucher), [
            'nama' => 'Nama Baru',
            'kode' => $voucher->kode,
            'voucher_type' => VoucherType::Percentage->value,
            'discount_scope' => VoucherScope::Item->value,
            'discount_percentage' => 25,
            'min_order_amount' => 150000,
            'max_uses' => 20,
            'start_date' => now()->toDateString(),
            'end_date' => now()->addDays(10)->toDateString(),
            'is_active' => true,
        ])
        ->assertRedirect(route('admin.vouchers.index'));

    $voucher->refresh();

    expect($voucher->nama)->toBe('Nama Baru')
        ->and($voucher->discount_percentage)->toBe(25)
        ->and($voucher->min_order_amount)->toBe(150000);
});

it('deletes and restores a soft-deleted voucher', function (): void {
    $voucher = Voucher::factory()->create();

    $this->actingAs($this->admin)
        ->delete(route('admin.vouchers.destroy', $voucher))
        ->assertRedirect(route('admin.vouchers.index'));

    expect(Voucher::find($voucher->id))->toBeNull();

    $this->actingAs($this->admin)
        ->post(route('admin.vouchers.restore', $voucher))
        ->assertRedirect(route('admin.vouchers.index'));

    expect(Voucher::find($voucher->id))->not->toBeNull();
});

it('blocks updating a used voucher', function (): void {
    $voucher = Voucher::factory()->percentage(10)->create(['nama' => 'Voucher Lama']);
    markVoucherUsed($voucher, $this->admin);

    $this->actingAs($this->admin)
        ->put(route('admin.vouchers.update', $voucher), [
            'nama' => 'Nama Baru',
            'kode' => $voucher->kode,
            'voucher_type' => VoucherType::Percentage->value,
            'discount_scope' => VoucherScope::Item->value,
            'discount_percentage' => 25,
            'min_order_amount' => 150000,
            'max_uses' => 20,
            'start_date' => now()->toDateString(),
            'end_date' => now()->addDays(10)->toDateString(),
            'is_active' => true,
        ])
        ->assertRedirect(route('admin.vouchers.index'));

    expect($voucher->fresh()->nama)->toBe('Voucher Lama')
        ->and($voucher->fresh()->discount_percentage)->toBe(10)
        ->and(session('inertia.flash_data.toast.type'))->toBe('error');
});

it('blocks deleting a used voucher', function (): void {
    $voucher = Voucher::factory()->create();
    markVoucherUsed($voucher, $this->admin);

    $this->actingAs($this->admin)
        ->delete(route('admin.vouchers.destroy', $voucher))
        ->assertRedirect();

    expect(Voucher::withTrashed()->find($voucher->id))->not->toBeNull()
        ->and($voucher->fresh()->trashed())->toBeFalse()
        ->and(session('inertia.flash_data.toast.type'))->toBe('error');
});

it('blocks toggling a used voucher', function (): void {
    $voucher = Voucher::factory()->create(['is_active' => true]);
    markVoucherUsed($voucher, $this->admin);

    $this->actingAs($this->admin)
        ->patch(route('admin.vouchers.toggle', $voucher))
        ->assertRedirect();

    expect($voucher->fresh()->is_active)->toBeTrue()
        ->and(session('inertia.flash_data.toast.type'))->toBe('error');
});

it('blocks non-admin users from creating vouchers', function (): void {
    $customer = User::factory()->create();

    $this->actingAs($customer)
        ->post(route('admin.vouchers.store'), [
            'nama' => 'Nakal',
            'voucher_type' => VoucherType::Percentage->value,
            'discount_scope' => VoucherScope::Item->value,
            'discount_percentage' => 10,
            'start_date' => now()->toDateString(),
            'end_date' => now()->addDays(1)->toDateString(),
        ])
        ->assertForbidden();

    expect(Voucher::where('nama', 'Nakal')->doesntExist())->toBeTrue();
});

<?php

use App\Enums\PromotionType;
use App\Models\Book;
use App\Models\Promotion;
use App\Models\User;

beforeEach(function (): void {
    $this->admin = User::factory()->admin()->create();
});

it('creates a percentage promotion attached to specific books (PROM-01, PROM-03)', function (): void {
    $books = Book::factory()->count(2)->create();

    $this->actingAs($this->admin)
        ->post(route('admin.promotions.store'), [
            'promo_name' => 'Diskon Buku Pilihan',
            'promo_type' => PromotionType::Percentage->value,
            'discount_percentage' => 15,
            'start_date' => now()->toDateString(),
            'end_date' => now()->addDays(7)->toDateString(),
            'is_active' => true,
            'book_ids' => $books->pluck('id')->all(),
        ])
        ->assertRedirect(route('admin.promotions.index'));

    $promo = Promotion::where('promo_name', 'Diskus Buku Pilihan')->orWhere('promo_name', 'Diskon Buku Pilihan')->first();

    expect($promo)->not->toBeNull()
        ->and($promo->promo_type)->toBe(PromotionType::Percentage)
        ->and($promo->books()->count())->toBe(2);
});

it('creates a global promotion when no books are attached', function (): void {
    $this->actingAs($this->admin)
        ->post(route('admin.promotions.store'), [
            'promo_name' => 'Promo Global',
            'promo_type' => PromotionType::Fixed->value,
            'promo_value' => 49000,
            'start_date' => now()->toDateString(),
            'end_date' => now()->addDays(3)->toDateString(),
            'is_active' => true,
            'book_ids' => [],
        ])
        ->assertRedirect(route('admin.promotions.index'));

    $promotion = Promotion::where('promo_name', 'Promo Global')->first();

    expect($promotion->books()->count())->toBe(0)
        ->and($promotion->is_global)->toBeTrue();
});

it('validates end date is after start date (PROM-04)', function (): void {
    $this->actingAs($this->admin)
        ->post(route('admin.promotions.store'), [
            'promo_name' => 'Salah Tanggal',
            'promo_type' => PromotionType::Percentage->value,
            'discount_percentage' => 10,
            'start_date' => now()->addDays(5)->toDateString(),
            'end_date' => now()->toDateString(),
        ])
        ->assertSessionHasErrors('end_date');
});

it('validates required fields per promo type', function (): void {
    // Fixed tanpa promo_value.
    $this->actingAs($this->admin)
        ->post(route('admin.promotions.store'), [
            'promo_name' => 'Fixed Tanpa Nilai',
            'promo_type' => PromotionType::Fixed->value,
            'start_date' => now()->toDateString(),
            'end_date' => now()->addDays(1)->toDateString(),
        ])
        ->assertSessionHasErrors('promo_value');

    // Bundle tanpa bundle_qty.
    $this->actingAs($this->admin)
        ->post(route('admin.promotions.store'), [
            'promo_name' => 'Bundle Tanpa Qty',
            'promo_type' => PromotionType::Bundle->value,
            'start_date' => now()->toDateString(),
            'end_date' => now()->addDays(1)->toDateString(),
        ])
        ->assertSessionHasErrors('bundle_qty');
});

it('toggles promotion active state (PROM-05)', function (): void {
    $promo = Promotion::factory()->create(['is_active' => true]);

    $this->actingAs($this->admin)
        ->patch(route('admin.promotions.toggle', $promo))
        ->assertRedirect();

    expect($promo->fresh()->is_active)->toBeFalse();

    $this->actingAs($this->admin)
        ->patch(route('admin.promotions.toggle', $promo))
        ->assertRedirect();

    expect($promo->fresh()->is_active)->toBeTrue();
});

it('updates a promotion and syncs its books', function (): void {
    $promo = Promotion::factory()->percentage()->create();
    $book = Book::factory()->create();

    $this->actingAs($this->admin)
        ->put(route('admin.promotions.update', $promo), [
            'promo_name' => 'Nama Baru',
            'promo_type' => PromotionType::Percentage->value,
            'discount_percentage' => 25,
            'start_date' => now()->toDateString(),
            'end_date' => now()->addDays(10)->toDateString(),
            'is_active' => true,
            'book_ids' => [$book->id],
        ])
        ->assertRedirect(route('admin.promotions.index'));

    $promo->refresh();

    expect($promo->promo_name)->toBe('Nama Baru')
        ->and($promo->books()->pluck('books.id')->all())->toBe([$book->id]);
});

it('deletes a promotion', function (): void {
    $promo = Promotion::factory()->create();

    $this->actingAs($this->admin)
        ->delete(route('admin.promotions.destroy', $promo))
        ->assertRedirect(route('admin.promotions.index'));

    expect(Promotion::find($promo->id))->toBeNull();
});

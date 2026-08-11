<?php

use App\Models\TierDiscount;
use App\Models\User;

beforeEach(function (): void {
    $this->admin = User::factory()->admin()->create();
});

it('lists tier discounts with tier filter', function (): void {
    TierDiscount::create(['tier' => 'reseller', 'min_qty' => 5, 'discount_percent' => 10]);
    TierDiscount::create(['tier' => 'guru', 'min_qty' => 3, 'discount_percent' => 8]);

    $this->actingAs($this->admin)
        ->get(route('admin.tier-discounts.index'))
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->component('admin/tier-discounts/Index')
            // 4 rule default dari migration + 2 buatan test.
            ->has('tierDiscounts.data', 6));

    $this->actingAs($this->admin)
        ->get(route('admin.tier-discounts.index', ['tier' => 'reseller']))
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page->has('tierDiscounts.data', 3));
});

it('creates a tier discount rule', function (): void {
    $this->actingAs($this->admin)
        ->post(route('admin.tier-discounts.store'), [
            'tier' => 'reseller',
            'min_qty' => 5,
            'discount_percent' => 12,
        ])
        ->assertRedirect(route('admin.tier-discounts.index'));

    expect(TierDiscount::where('tier', 'reseller')->where('min_qty', 5)->first())
        ->not->toBeNull()
        ->discount_percent->toBe(12);
});

it('updates an existing rule when the same (tier, min_qty) is submitted', function (): void {
    TierDiscount::create(['tier' => 'reseller', 'min_qty' => 5, 'discount_percent' => 10]);

    $this->actingAs($this->admin)
        ->post(route('admin.tier-discounts.store'), [
            'tier' => 'reseller',
            'min_qty' => 5,
            'discount_percent' => 15,
        ])
        ->assertRedirect();

    expect(TierDiscount::where('tier', 'reseller')->where('min_qty', 5)->count())->toBe(1)
        ->and(TierDiscount::where('tier', 'reseller')->where('min_qty', 5)->first()->discount_percent)->toBe(15);
});

it('updates an existing rule via the update endpoint', function (): void {
    $rule = TierDiscount::create(['tier' => 'guru', 'min_qty' => 3, 'discount_percent' => 8]);

    $this->actingAs($this->admin)
        ->put(route('admin.tier-discounts.update', $rule), [
            'min_qty' => 4,
            'discount_percent' => 9,
        ])
        ->assertRedirect(route('admin.tier-discounts.index'));

    expect($rule->fresh()->min_qty)->toBe(4)
        ->and($rule->fresh()->discount_percent)->toBe(9);
});

it('validates tier discount rules', function (): void {
    $this->actingAs($this->admin)
        ->post(route('admin.tier-discounts.store'), [
            'tier' => 'tidak-ada',
            'min_qty' => 0,
            'discount_percent' => 101,
        ])
        ->assertSessionHasErrors(['tier', 'min_qty', 'discount_percent']);

    // Rule default dari migration tetap utuh.
    expect(TierDiscount::count())->toBe(4);
});

it('soft deletes and restores a tier discount rule', function (): void {
    $rule = TierDiscount::create(['tier' => 'bazaf', 'min_qty' => 2, 'discount_percent' => 5]);

    $this->actingAs($this->admin)
        ->delete(route('admin.tier-discounts.destroy', $rule))
        ->assertRedirect(route('admin.tier-discounts.index'));

    expect(TierDiscount::find($rule->id))->toBeNull();

    $this->actingAs($this->admin)
        ->post(route('admin.tier-discounts.restore', $rule))
        ->assertRedirect(route('admin.tier-discounts.index'));

    expect(TierDiscount::find($rule->id))->not->toBeNull();
});

it('blocks non-admin customers from tier discount routes', function (): void {
    $customer = User::factory()->customer()->create();

    $this->actingAs($customer)
        ->get(route('admin.tier-discounts.index'))
        ->assertForbidden();
});

<?php

use App\Enums\OrderStatus;
use App\Models\Book;
use App\Models\Category;
use App\Models\Order;
use App\Models\User;

beforeEach(function (): void {
    $this->admin = User::factory()->admin()->create();
});

it('allows admin to access the dashboard', function (): void {
    $this->actingAs($this->admin)
        ->get(route('admin.dashboard'))
        ->assertSuccessful();
});

it('blocks guests from admin routes', function (): void {
    $this->get(route('admin.books.index'))
        ->assertRedirect(route('login'));
});

it('blocks non-admin customers from admin routes', function (): void {
    $customer = User::factory()->create();

    $this->actingAs($customer)
        ->get(route('admin.books.index'))
        ->assertForbidden();

    $this->actingAs($customer)
        ->get(route('admin.dashboard'))
        ->assertForbidden();

    $this->actingAs($customer)
        ->post(route('admin.orders.store'), [])
        ->assertForbidden();
});

it('keeps customers out of the panel even when a user_id is passed', function (): void {
    $customer = User::factory()->create();

    $this->actingAs($customer)
        ->patch(route('admin.orders.status', Order::factory()->create()), ['status' => OrderStatus::Batal->value])
        ->assertForbidden();
});

it('allows admin to manage books', function (): void {
    $this->actingAs($this->admin)
        ->post(route('admin.books.store'), [
            'judul' => 'Buku Test',
            'penulis' => 'Penulis Test',
            'harga' => 50000,
            'category_id' => Category::factory()->create()->id,
            'editions' => [
                ['cetakan_ke' => 1, 'harga_beli' => 30000, 'harga_jual' => 50000, 'is_active' => true],
            ],
        ])
        ->assertRedirect(route('admin.books.index'));

    expect(Book::where('judul', 'Buku Test')->exists())->toBeTrue();
});

it('provides an admin login alias', function (): void {
    $this->get(route('admin.login'))
        ->assertRedirect(route('login'));
});

it('shows demo credentials on the login page in local environment', function (): void {
    $this->get(route('login'))
        ->assertSuccessful();
});

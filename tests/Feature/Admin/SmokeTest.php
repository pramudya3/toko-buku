<?php

use App\Models\Book;
use App\Models\User;

it('renders every admin module from the sidebar (AC-09)', function (): void {
    $admin = User::factory()->admin()->create();

    $routes = [
        'admin.dashboard',
        'admin.books.index',
        'admin.categories.index',
        'admin.customers.index',
        'admin.orders.index',
        'admin.promotions.index',
        'admin.inventory.index',
        'admin.cash-flow.index',
        'admin.dropship.index',
    ];

    foreach ($routes as $route) {
        $this->actingAs($admin)
            ->get(route($route))
            ->assertSuccessful();
    }
});

it('renders the book and promotion create forms', function (): void {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->get(route('admin.books.create'))
        ->assertSuccessful();

    $this->actingAs($admin)
        ->get(route('admin.promotions.create'))
        ->assertSuccessful();
});

it('limits large picker payloads and supports server-side option search', function (): void {
    $admin = User::factory()->admin()->create();
    Book::factory()->count(51)->create();
    Book::factory()->create(['judul' => 'Buku Target Pencarian']);

    $this->actingAs($admin)
        ->getJson(route('admin.orders.options.books'))
        ->assertSuccessful()
        ->assertJsonCount(50);

    $this->actingAs($admin)
        ->getJson(route('admin.orders.options.books', ['search' => 'Target Pencarian']))
        ->assertSuccessful()
        ->assertJsonCount(1)
        ->assertJsonPath('0.judul', 'Buku Target Pencarian');

    $this->actingAs($admin)
        ->getJson(route('admin.promotions.options.books', ['search' => 'Target Pencarian']))
        ->assertSuccessful()
        ->assertJsonCount(1);
});

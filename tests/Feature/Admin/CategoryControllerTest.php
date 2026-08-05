<?php

use App\Enums\OrderStatus;
use App\Models\Book;
use App\Models\Category;
use App\Models\Order;
use App\Models\User;

beforeEach(function (): void {
    $this->admin = User::factory()->admin()->create();
});

it('lists categories with book counts', function (): void {
    $category = Category::factory()->create();
    Book::factory()->withStock()->create(['category_id' => $category->id]);

    $this->actingAs($this->admin)
        ->get(route('admin.categories.index'))
        ->assertSuccessful()
        ->assertSee($category->nama);
});

it('creates a category', function (): void {
    $this->actingAs($this->admin)
        ->post(route('admin.categories.store'), [
            'nama' => 'Fiksi Ilmiah',
            'slug' => 'fiksi-ilmiah',
        ])
        ->assertRedirect(route('admin.categories.index'));

    expect(Category::where('slug', 'fiksi-ilmiah')->exists())->toBeTrue();
});

it('rejects duplicate slugs', function (): void {
    Category::factory()->create(['slug' => 'fiksi']);

    $this->actingAs($this->admin)
        ->post(route('admin.categories.store'), [
            'nama' => 'Fiksi Lagi',
            'slug' => 'fiksi',
        ])
        ->assertSessionHasErrors('slug');
});

it('prevents deleting a category that is still used by books', function (): void {
    $category = Category::factory()->create();
    Book::factory()->withStock()->create(['category_id' => $category->id]);

    $this->actingAs($this->admin)
        ->delete(route('admin.categories.destroy', $category))
        ->assertRedirect();

    expect(Category::find($category->id))->not->toBeNull();
});

it('deletes an empty category', function (): void {
    $category = Category::factory()->create();

    $this->actingAs($this->admin)
        ->delete(route('admin.categories.destroy', $category))
        ->assertRedirect(route('admin.categories.index'));

    expect(Category::find($category->id))->toBeNull();
});

it('allows updating a category', function (): void {
    $category = Category::factory()->create();

    $this->actingAs($this->admin)
        ->put(route('admin.categories.update', $category), [
            'nama' => 'Nama Baru',
            'slug' => 'nama-baru',
        ])
        ->assertRedirect(route('admin.categories.index'));

    expect($category->fresh()->nama)->toBe('Nama Baru');
});

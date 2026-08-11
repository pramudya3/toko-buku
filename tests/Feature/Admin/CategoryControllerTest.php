<?php

use App\Models\Book;
use App\Models\Category;
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
        ])
        ->assertRedirect(route('admin.categories.index'));

    expect(Category::where('nama', 'Fiksi Ilmiah')->exists())->toBeTrue();
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
        ])
        ->assertRedirect(route('admin.categories.index'));

    expect($category->fresh()->nama)->toBe('Nama Baru');
});

it('restores a soft-deleted category', function (): void {
    $category = Category::factory()->create();

    $this->actingAs($this->admin)
        ->delete(route('admin.categories.destroy', $category))
        ->assertRedirect();

    expect(Category::find($category->id))->toBeNull();

    $this->actingAs($this->admin)
        ->post(route('admin.categories.restore', $category))
        ->assertRedirect(route('admin.categories.index'));

    expect(Category::find($category->id))->not->toBeNull();
});

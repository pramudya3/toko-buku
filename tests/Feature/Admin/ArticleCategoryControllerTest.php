<?php

use App\Models\Article;
use App\Models\ArticleCategory;
use App\Models\User;

beforeEach(function (): void {
    $this->admin = User::factory()->admin()->create();
});

it('lists article categories with article counts', function (): void {
    $category = ArticleCategory::factory()->create(['nama' => 'Esai']);
    Article::factory()->create(['article_category_id' => $category->id]);

    $this->actingAs($this->admin)
        ->get(route('admin.article-categories.index'))
        ->assertSuccessful()
        ->assertSee('Esai');

    $props = inertiaProps($this->actingAs($this->admin)->get(route('admin.article-categories.index')));
    $row = collect($props['categories']['data'])->firstWhere('nama', 'Esai');

    expect($row['articles_count'])->toBe(1);
});

it('creates a category with an auto slug', function (): void {
    $this->actingAs($this->admin)
        ->post(route('admin.article-categories.store'), [
            'nama' => 'Rekomendasi',
        ])
        ->assertRedirect(route('admin.article-categories.index'));

    $category = ArticleCategory::where('nama', 'Rekomendasi')->first();

    expect($category)->not->toBeNull()
        ->and($category->slug)->toBe('rekomendasi');
});

it('uses the explicit slug when provided', function (): void {
    $this->actingAs($this->admin)
        ->post(route('admin.article-categories.store'), [
            'nama' => 'Di Balik Penerbitan',
            'slug' => 'penerbitan',
        ])
        ->assertRedirect();

    expect(ArticleCategory::where('slug', 'penerbitan')->exists())->toBeTrue();
});

it('updates a category', function (): void {
    $category = ArticleCategory::factory()->create(['nama' => 'Esai', 'slug' => 'esai']);

    $this->actingAs($this->admin)
        ->put(route('admin.article-categories.update', $category), [
            'nama' => 'Esai & Opini',
            'slug' => null,
        ])
        ->assertRedirect(route('admin.article-categories.index'));

    expect($category->refresh()->nama)->toBe('Esai & Opini')
        ->and($category->slug)->toBe('esai-opini');
});

it('prevents deleting a category that is still used by articles', function (): void {
    $category = ArticleCategory::factory()->create();
    Article::factory()->create(['article_category_id' => $category->id]);

    $this->actingAs($this->admin)
        ->delete(route('admin.article-categories.destroy', $category))
        ->assertRedirect();

    expect(ArticleCategory::find($category->id))->not->toBeNull();
});

it('soft deletes and restores an empty category', function (): void {
    $category = ArticleCategory::factory()->create(['nama' => 'Praktis']);

    $this->actingAs($this->admin)
        ->delete(route('admin.article-categories.destroy', $category))
        ->assertRedirect();

    expect(ArticleCategory::find($category->id))->toBeNull()
        ->and(ArticleCategory::withTrashed()->find($category->id))->not->toBeNull();

    $this->actingAs($this->admin)
        ->post(route('admin.article-categories.restore', $category))
        ->assertRedirect(route('admin.article-categories.index'));

    expect(ArticleCategory::find($category->id))->not->toBeNull();
});

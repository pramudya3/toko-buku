<?php

use App\Models\Article;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * Menu Artikel di storefront — daftar (/artikel) & detail (/artikel/{slug})
 * untuk storefront utama dan storefront paralel proto-d (/pcd/**).
 */
it('lists only published articles on the main storefront', function (): void {
    Article::factory()->create(['judul' => 'Artikel Terbit', 'published_at' => today()->toDateString()]);
    Article::factory()->create(['judul' => 'Artikel Draft', 'is_active' => false]);
    Article::factory()->create(['judul' => 'Artikel Masa Depan', 'published_at' => today()->addDay()->toDateString()]);

    $this->get(route('articles.index'))
        ->assertOk()
        ->assertSee('Artikel Terbit')
        ->assertDontSee('Artikel Draft')
        ->assertDontSee('Artikel Masa Depan');
});

it('shows an article detail page by slug', function (): void {
    $article = Article::factory()->create([
        'judul' => 'Membaca di Tengah Gempuran Layar',
        'published_at' => '2026-08-16',
    ]);

    $this->get(route('articles.show', $article->slug))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('storefront/ArticleDetail')
            ->where('article.judul', 'Membaca di Tengah Gempuran Layar'));
});

it('404s for inactive or unpublished articles', function (): void {
    $inactive = Article::factory()->draft()->create(['judul' => 'Tersembunyi']);
    $future = Article::factory()->create(['published_at' => today()->addDay()->toDateString()]);

    $this->get(route('articles.show', $inactive->slug))->assertNotFound();
    $this->get(route('articles.show', $future->slug))->assertNotFound();
});

it('passes real articles to the pcd home', function (): void {
    Article::factory()->create([
        'judul' => 'Artikel PCD Unggulan',
        'published_at' => '2026-08-16',
    ]);
    Article::factory()->draft()->create(['judul' => 'Artikel PCD Draft']);

    $this->get(route('pcd.home'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('storefront-pcd/Home')
            ->has('articles', 1)
            ->where('articles.0.judul', 'Artikel PCD Unggulan'));
});

it('shows the pcd article detail and 404s for unpublished ones', function (): void {
    $article = Article::factory()->create([
        'judul' => 'Artikel PCD Detail',
        'published_at' => '2026-08-16',
    ]);
    $draft = Article::factory()->draft()->create(['judul' => 'Artikel PCD Draft']);

    $this->get(route('pcd.articles.show', $article->slug))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('storefront-pcd/ArticleDetail')
            ->where('article.judul', 'Artikel PCD Detail'));

    $this->get(route('pcd.articles.show', $draft->slug))->assertNotFound();
});

<?php

use App\Models\Article;
use App\Models\ArticleCategory;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function (): void {
    $this->admin = User::factory()->admin()->create();
});

it('lists articles newest first', function (): void {
    Article::factory()->create([
        'judul' => 'Artikel Lama',
        'published_at' => '2026-07-01',
    ]);
    Article::factory()->create([
        'judul' => 'Artikel Baru',
        'published_at' => '2026-08-01',
    ]);

    $this->actingAs($this->admin)
        ->get(route('admin.articles.index'))
        ->assertSuccessful()
        ->assertSee('Artikel Baru')
        ->assertSee('Artikel Lama');

    $props = inertiaProps($this->actingAs($this->admin)->get(route('admin.articles.index')));
    $juduls = collect($props['articles']['data'])->pluck('judul')->all();

    expect(array_search('Artikel Baru', $juduls, true))
        ->toBeLessThan(array_search('Artikel Lama', $juduls, true));
});

it('searches articles by title', function (): void {
    Article::factory()->create(['judul' => 'Membaca di Tengah Gempuran Layar']);
    Article::factory()->create(['judul' => 'Resensi Laut Bercerita']);

    $this->actingAs($this->admin)
        ->get(route('admin.articles.index', ['search' => 'Gempuran']))
        ->assertSuccessful()
        ->assertSee('Membaca di Tengah Gempuran Layar')
        ->assertDontSee('Resensi Laut Bercerita');
});

it('renders the create and edit pages', function (): void {
    $article = Article::factory()->create(['judul' => 'Artikel Edit']);

    $this->actingAs($this->admin)->get(route('admin.articles.create'))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/articles/Form')
            ->where('article', null));

    $this->actingAs($this->admin)->get(route('admin.articles.edit', $article))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/articles/Form')
            ->where('article.id', $article->id));
});

it('creates an article with a unique slug', function (): void {
    $this->actingAs($this->admin)
        ->post(route('admin.articles.store'), [
            'judul' => 'Membaca di Tengah Gempuran Layar',
            'article_category_id' => ArticleCategory::factory()->create()->id,
            'penulis' => 'Tim Penerbit',
            'ringkasan' => 'Ringkasan singkat.',
            'isi' => "Paragraf pertama.\n\n> Kutipan tengah.\n\nParagraf kedua.",
            'motif' => 'lamp',
            'is_active' => '1',
            'published_at' => '2026-08-16',
        ])
        ->assertRedirect(route('admin.articles.index'));

    $article = Article::where('judul', 'Membaca di Tengah Gempuran Layar')->first();

    expect($article)->not->toBeNull()
        ->and($article->slug)->toBe('membaca-di-tengah-gempuran-layar')
        ->and($article->is_active)->toBeTrue();
});

it('stores plain text isi as html paragraphs and blockquotes', function (): void {
    $this->actingAs($this->admin)
        ->post(route('admin.articles.store'), [
            'judul' => 'Format isi',
            'article_category_id' => ArticleCategory::factory()->create()->id,
            'ringkasan' => 'Ringkasan.',
            'isi' => "Paragraf satu.\n\n> Kutipan.\n\nParagraf dua.",
        ])
        ->assertRedirect(route('admin.articles.index'));

    $article = Article::where('judul', 'Format isi')->first();

    expect($article->isi)
        ->toBe("<p>Paragraf satu.</p>\n<blockquote>Kutipan.</blockquote>\n<p>Paragraf dua.</p>");
});

it('sanitizes scripts and event handlers from article isi', function (): void {
    $this->actingAs($this->admin)
        ->post(route('admin.articles.store'), [
            'judul' => 'Artikel Aman',
            'article_category_id' => ArticleCategory::factory()->create()->id,
            'ringkasan' => 'Ringkasan.',
            'isi' => '<p>Teks aman</p><script>alert(1)</script><p onclick="x()">Klik</p>',
        ])
        ->assertRedirect(route('admin.articles.index'));

    $article = Article::where('judul', 'Artikel Aman')->first();

    expect($article->isi)
        ->toContain('Teks aman')
        ->toContain('Klik')
        ->not->toContain('<script')
        ->not->toContain('onclick');
});

it('keeps rich editor tags but strips disallowed ones', function (): void {
    $this->actingAs($this->admin)
        ->post(route('admin.articles.store'), [
            'judul' => 'Rich Editor',
            'article_category_id' => ArticleCategory::factory()->create()->id,
            'ringkasan' => 'Ringkasan.',
            'isi' => '<h1>Bab Utama</h1><h5>Catatan</h5><h6>Kecil</h6><p style="text-align: center">Tengah</p>'
                .'<p>Teks <mark data-color="#fef08a" style="background-color: #fef08a">penting</mark> dan H<sub>2</sub>O / m<sup>2</sup>.</p><hr>'
                .'<ul data-type="taskList"><li data-type="taskItem" data-checked="true"><label><input type="checkbox" checked></label><div><p>Check</p></div></li></ul>'
                .'<iframe src="https://evil.example"></iframe><img src="x" onerror="alert(1)">',
        ])
        ->assertRedirect(route('admin.articles.index'));

    $article = Article::where('judul', 'Rich Editor')->first();

    expect($article->isi)
        ->toContain('<h1>Bab Utama</h1>')
        ->toContain('<h5>Catatan</h5>')
        ->toContain('<h6>Kecil</h6>')
        ->toContain('style="text-align: center"')
        ->toContain('<mark data-color="#fef08a"')
        ->toContain('<hr>')
        ->toContain('<sub>2</sub>')
        ->toContain('<sup>2</sup>')
        ->toContain('data-type="taskItem"')
        ->toContain('<input type="checkbox"')
        ->not->toContain('<iframe')
        ->not->toContain('<img')
        ->not->toContain('onerror');
});

it('allows http images but strips non-http and event handlers from img', function (): void {
    $this->actingAs($this->admin)
        ->post(route('admin.articles.store'), [
            'judul' => 'Gambar Aman',
            'article_category_id' => ArticleCategory::factory()->create()->id,
            'ringkasan' => 'Ringkasan.',
            'isi' => '<p>Gambar:</p>'
                .'<img src="https://cdn.example.com/buku.jpg" alt="Sampul buku" title="Buku X">'
                .'<img src="javascript:alert(1)" onerror="steal()">'
                .'<img src="data:image/png;base64,AAA">'
                .'<img src="relative/path.png">',
        ])
        ->assertRedirect(route('admin.articles.index'));

    $article = Article::where('judul', 'Gambar Aman')->first();

    expect($article->isi)
        ->toContain('src="https://cdn.example.com/buku.jpg"')
        ->toContain('alt="Sampul buku"')
        ->toContain('title="Buku X"')
        ->not->toContain('javascript:')
        ->not->toContain('data:image')
        ->not->toContain('relative/path.png')
        ->not->toContain('onerror');
});

it('uploads an inline article image to r2', function (): void {
    Storage::fake('r2');

    $response = $this->actingAs($this->admin)
        ->post(route('admin.articles.upload-image'), [
            'image' => UploadedFile::fake()->image('artikel.png', 200, 100),
        ])
        ->assertOk()
        ->assertJsonStructure(['url']);

    $url = json_decode($response->getContent(), true)['url'];

    // Fake disk mengembalikan path lokal, bukan R2 URL asli — cukup pastikan
    // file tersimpan dan path mengarah ke folder article-images.
    expect($url)->toContain('article-images/')
        ->and(Storage::disk('r2')->files('article-images'))->not->toBeEmpty();
});

it('appends a suffix when the slug is already taken', function (): void {
    Article::factory()->create(['judul' => 'Judul Sama', 'slug' => 'judul-sama']);

    $this->actingAs($this->admin)
        ->post(route('admin.articles.store'), [
            'judul' => 'Judul Sama',
            'article_category_id' => ArticleCategory::factory()->create()->id,
            'ringkasan' => 'Ringkasan.',
            'isi' => 'Isi artikel.',
        ])
        ->assertRedirect(route('admin.articles.index'));

    expect(Article::where('slug', 'judul-sama-2')->exists())->toBeTrue();
});

it('updates an article and refreshes the slug when the title changes', function (): void {
    $article = Article::factory()->create(['judul' => 'Judul Awal', 'slug' => 'judul-awal']);

    $this->actingAs($this->admin)
        ->put(route('admin.articles.update', $article), [
            'judul' => 'Judul Baru',
            'article_category_id' => ArticleCategory::factory()->create()->id,
            'ringkasan' => 'Ringkasan baru.',
            'isi' => 'Isi baru.',
            'is_active' => '0',
        ])
        ->assertRedirect(route('admin.articles.index'));

    $article->refresh();

    expect($article->judul)->toBe('Judul Baru')
        ->and($article->slug)->toBe('judul-baru')
        ->and($article->is_active)->toBeFalse();
});

it('rejects invalid categories', function (): void {
    $this->actingAs($this->admin)
        ->post(route('admin.articles.store'), [
            'judul' => 'Judul',
            'article_category_id' => '9a9c9c9c-9c9c-9c9c-9c9c-9c9c9c9c9c9c',
            'ringkasan' => 'Ringkasan.',
            'isi' => 'Isi.',
        ])
        ->assertSessionHasErrors('article_category_id');

    expect(Article::count())->toBe(0);
});

it('toggles article visibility', function (): void {
    $article = Article::factory()->create();

    $this->actingAs($this->admin)
        ->patch(route('admin.articles.toggle-active', $article))
        ->assertRedirect();

    expect($article->refresh()->is_active)->toBeFalse();

    $this->actingAs($this->admin)
        ->patch(route('admin.articles.toggle-active', $article))
        ->assertRedirect();

    expect($article->refresh()->is_active)->toBeTrue();
});

it('toggles the featured article and keeps only one featured', function (): void {
    $current = Article::factory()->create(['is_featured' => true]);
    $target = Article::factory()->create(['is_featured' => false]);

    // Jadikan target unggulan → unggulan lama otomatis di-reset.
    $this->actingAs($this->admin)
        ->patch(route('admin.articles.toggle-featured', $target))
        ->assertRedirect();

    expect($target->refresh()->is_featured)->toBeTrue()
        ->and($current->refresh()->is_featured)->toBeFalse();

    // Batalkan unggulan → tidak ada artikel unggulan.
    $this->actingAs($this->admin)
        ->patch(route('admin.articles.toggle-featured', $target))
        ->assertRedirect();

    expect($target->refresh()->is_featured)->toBeFalse()
        ->and(Article::where('is_featured', true)->count())->toBe(0);
});

it('soft deletes and restores an article', function (): void {
    $article = Article::factory()->create();

    $this->actingAs($this->admin)
        ->delete(route('admin.articles.destroy', $article))
        ->assertRedirect();

    expect(Article::find($article->id))->toBeNull()
        ->and(Article::withTrashed()->find($article->id))->not->toBeNull();

    $this->actingAs($this->admin)
        ->post(route('admin.articles.restore', $article))
        ->assertRedirect(route('admin.articles.index'));

    expect(Article::find($article->id))->not->toBeNull();
});

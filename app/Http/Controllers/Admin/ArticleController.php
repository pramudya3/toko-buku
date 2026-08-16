<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ArticleImageUploadRequest;
use App\Http\Requests\Admin\ArticleRequest;
use App\Models\Article;
use App\Models\ArticleCategory;
use App\Services\ImageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;

class ArticleController extends Controller
{
    public function __construct(
        private readonly ImageService $imageService,
    ) {}

    /**
     * Daftar artikel — terbaru di atas, dengan pencarian.
     */
    public function index(Request $request): Response
    {
        $articles = Article::query()
            ->with('category:id,nama')
            ->when($request->filled('search'), function ($query) use ($request): void {
                $query->whereLike('judul', '%'.$request->string('search')->toString().'%');
            })
            ->orderByDesc('published_at')
            ->orderByDesc('created_at')
            ->paginate(10)
            ->withQueryString()
            ->through(fn (Article $article): array => [
                'id' => $article->id,
                'judul' => $article->judul,
                'slug' => $article->slug,
                'article_category_id' => $article->article_category_id,
                'kategori_label' => $article->category->nama ?? 'Tanpa kategori',
                'penulis' => $article->penulis,
                'ringkasan' => $article->ringkasan,
                'isi' => $article->isi,
                'motif' => $article->motif,
                'cover_url' => $article->cover_url,
                'is_active' => $article->is_active,
                'published_at' => $article->published_at,
            ]);

        return Inertia::render('admin/articles/Index', [
            'articles' => $articles,
            'filters' => $request->only(['search']),
            'kategoriOptions' => ArticleCategory::orderBy('nama')->get(['id', 'nama']),
        ]);
    }

    /**
     * Form buat artikel baru — halaman terpisah dengan editor WYSIWYG.
     */
    public function create(): Response
    {
        return Inertia::render('admin/articles/Form', [
            'article' => null,
            'kategoriOptions' => ArticleCategory::orderBy('nama')->get(['id', 'nama']),
            'motifOptions' => Article::motifOptions(),
        ]);
    }

    /**
     * Form edit artikel.
     */
    public function edit(Article $article): Response
    {
        return Inertia::render('admin/articles/Form', [
            'article' => $article->load('category:id,nama'),
            'kategoriOptions' => ArticleCategory::orderBy('nama')->get(['id', 'nama']),
            'motifOptions' => Article::motifOptions(),
        ]);
    }

    public function store(ArticleRequest $request): RedirectResponse
    {
        $data = $this->payload($request);

        $data['slug'] = $this->uniqueSlug($data['judul']);

        Article::create($data);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Artikel berhasil dibuat.',
        ]);

        return to_route('admin.articles.index');
    }

    public function update(ArticleRequest $request, Article $article): RedirectResponse
    {
        $data = $this->payload($request, $article);

        // Judul berubah → slug ikut disegarkan (tetap unik).
        if ($data['judul'] !== $article->judul) {
            $data['slug'] = $this->uniqueSlug($data['judul'], $article);
        }

        $article->update($data);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Artikel berhasil diperbarui.',
        ]);

        return to_route('admin.articles.index');
    }

    /**
     * Hapus artikel (soft delete) — bisa dipulihkan via undo.
     */
    public function destroy(Article $article): RedirectResponse
    {
        $article->delete();

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => "Artikel {$article->judul} berhasil dihapus.",
            'undo' => ['url' => route('admin.articles.restore', $article)],
        ]);

        return back();
    }

    public function restore(Article $article): RedirectResponse
    {
        $article->restore();

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => "Artikel {$article->judul} berhasil dipulihkan.",
        ]);

        return to_route('admin.articles.index');
    }

    /**
     * Tampilkan/sembunyikan artikel di storefront.
     */
    public function toggleActive(Article $article): RedirectResponse
    {
        $article->update(['is_active' => ! $article->is_active]);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => $article->is_active
                ? "Artikel {$article->judul} berhasil diaktifkan."
                : "Artikel {$article->judul} berhasil dinonaktifkan.",
        ]);

        return back();
    }

    /**
     * Unggah gambar di dalam body artikel (editor). Dipakai langsung oleh
     * toolbar Insert Image — bukan bagian dari form artikel.
     */
    public function uploadImage(ArticleImageUploadRequest $request): JsonResponse
    {
        $path = $this->imageService
            ->normalize($request->file('image'))
            ->store('article-images', 'r2');

        if ($path === false) {
            throw new RuntimeException('Gambar artikel gagal disimpan.');
        }

        return response()->json([
            'url' => Storage::disk('r2')->url($path),
        ]);
    }

    /**
     * Payload validasi + upload cover ke R2 (hapus cover lama bila diganti).
     *
     * @return array<string, mixed>
     */
    private function payload(ArticleRequest $request, ?Article $article = null): array
    {
        $data = $request->validated();

        unset($data['cover'], $data['remove_cover']);

        if ($request->hasFile('cover')) {
            if ($article !== null) {
                $this->deleteStoredFile((string) $article->cover_url);
            }

            $path = $this->imageService
                ->normalize($request->file('cover'))
                ->store('articles', 'r2');

            if ($path === false) {
                throw new RuntimeException('Cover artikel gagal disimpan.');
            }

            $data['cover_url'] = Storage::disk('r2')->url($path);
        }

        // Hapus cover tersimpan (tanpa upload file baru).
        if ($request->boolean('remove_cover') && ! $request->hasFile('cover')) {
            if ($article !== null) {
                $this->deleteStoredFile((string) $article->cover_url);
            }

            $data['cover_url'] = null;
        }

        if ($request->has('is_active')) {
            $data['is_active'] = $request->boolean('is_active');
        }

        return $data;
    }

    /**
     * Slug unik dari judul — suffix numerik bila sudah dipakai.
     */
    private function uniqueSlug(string $judul, ?Article $ignore = null): string
    {
        $base = Str::slug($judul);

        if ($base === '') {
            $base = 'artikel';
        }

        $slug = $base;
        $i = 2;

        while (Article::where('slug', $slug)->when($ignore, fn ($query) => $query->whereKeyNot($ignore->getKey()))->exists()) {
            $slug = $base.'-'.$i;
            $i++;
        }

        return $slug;
    }

    /**
     * Hapus file dari storage (R2 atau lokal).
     */
    private function deleteStoredFile(string $url): void
    {
        $r2Url = rtrim((string) config('filesystems.disks.r2.url'), '/');

        if ($r2Url !== '' && str_starts_with($url, $r2Url)) {
            $path = ltrim(str_replace($r2Url, '', $url), '/');
            Storage::disk('r2')->delete($path);

            return;
        }

        if (str_starts_with($url, '/storage/')) {
            $path = str_replace('/storage/', '', $url);
            Storage::disk('public')->delete($path);
        }
    }
}

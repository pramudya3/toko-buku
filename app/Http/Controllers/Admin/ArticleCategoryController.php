<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ArticleCategoryRequest;
use App\Models\ArticleCategory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class ArticleCategoryController extends Controller
{
    /**
     * Daftar kategori konten + jumlah artikel per kategori.
     */
    public function index(Request $request): Response
    {
        $categories = ArticleCategory::query()
            ->withCount('articles')
            ->when($request->filled('search'), function ($query) use ($request): void {
                $query->whereLike('nama', '%'.$request->string('search')->toString().'%');
            })
            ->orderBy('nama')
            ->paginate(10)
            ->withQueryString();

        return Inertia::render('admin/article-categories/Index', [
            'categories' => $categories,
            'filters' => $request->only(['search']),
        ]);
    }

    public function store(ArticleCategoryRequest $request): RedirectResponse
    {
        $data = $request->validated();

        $category = ArticleCategory::create([
            'nama' => $data['nama'],
            'slug' => $this->uniqueSlug($data['nama'], isset($data['slug']) ? $data['slug'] : null),
        ]);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => "Kategori konten {$category->nama} berhasil dibuat.",
        ]);

        return to_route('admin.article-categories.index');
    }

    public function update(ArticleCategoryRequest $request, ArticleCategory $articleCategory): RedirectResponse
    {
        $data = $request->validated();

        $articleCategory->update([
            'nama' => $data['nama'],
            'slug' => $this->uniqueSlug($data['nama'], isset($data['slug']) ? $data['slug'] : null, $articleCategory),
        ]);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => "Kategori konten {$articleCategory->nama} berhasil diperbarui.",
        ]);

        return to_route('admin.article-categories.index');
    }

    /**
     * Hapus kategori — diblokir bila masih dipakai artikel.
     */
    public function destroy(ArticleCategory $articleCategory): RedirectResponse
    {
        if ($articleCategory->articles()->exists()) {
            Inertia::flash('toast', [
                'type' => 'error',
                'message' => "Kategori {$articleCategory->nama} masih dipakai artikel dan tidak dapat dihapus.",
            ]);

            return back();
        }

        $articleCategory->delete();

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => "Kategori {$articleCategory->nama} berhasil dihapus.",
            'undo' => ['url' => route('admin.article-categories.restore', $articleCategory)],
        ]);

        return back();
    }

    public function restore(ArticleCategory $articleCategory): RedirectResponse
    {
        $articleCategory->restore();

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => "Kategori {$articleCategory->nama} berhasil dipulihkan.",
        ]);

        return to_route('admin.article-categories.index');
    }

    /**
     * Slug unik dari nama — suffix numerik bila sudah dipakai.
     */
    private function uniqueSlug(string $nama, ?string $explicit, ?ArticleCategory $ignore = null): string
    {
        $base = $explicit !== null && $explicit !== ''
            ? $explicit
            : Str::slug($nama);

        if ($base === '') {
            $base = 'kategori';
        }

        $slug = $base;
        $i = 2;

        while (ArticleCategory::where('slug', $slug)
            ->when($ignore, fn ($query) => $query->whereKeyNot($ignore->getKey()))
            ->exists()) {
            $slug = $base.'-'.$i;
            $i++;
        }

        return $slug;
    }
}

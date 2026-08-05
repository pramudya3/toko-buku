<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CategoryRequest;
use App\Models\Category;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CategoryController extends Controller
{
    /**
     * List kategori + jumlah buku per kategori (CAT-02).
     */
    public function index(Request $request): Response
    {
        $categories = Category::query()
            ->withCount('books')
            ->when($request->filled('search'), function ($query) use ($request): void {
                $query->whereLike('nama', '%'.$request->string('search')->toString().'%');
            })
            ->orderBy('nama')
            ->paginate(10)
            ->withQueryString();

        return Inertia::render('admin/categories/Index', [
            'categories' => $categories,
            'filters' => $request->only(['search']),
        ]);
    }

    /**
     * Simpan kategori baru (CAT-01).
     */
    public function store(CategoryRequest $request): RedirectResponse
    {
        $category = Category::create($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => "Kategori {$category->nama} berhasil dibuat."]);

        return to_route('admin.categories.index');
    }

    /**
     * Update kategori (CAT-01).
     */
    public function update(CategoryRequest $request, Category $category): RedirectResponse
    {
        $category->update($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => "Kategori {$category->nama} berhasil diperbarui."]);

        return to_route('admin.categories.index');
    }

    /**
     * Hapus kategori — diblokir bila masih dipakai buku (CAT-03).
     */
    public function destroy(Category $category): RedirectResponse
    {
        if ($category->books()->exists()) {
            Inertia::flash('toast', [
                'type' => 'error',
                'message' => "Kategori {$category->nama} masih dipakai buku dan tidak dapat dihapus.",
            ]);

            return back();
        }

        $category->delete();

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => "Kategori {$category->nama} berhasil dihapus.",
            'undo' => ['url' => route('admin.categories.restore', $category)],
        ]);

        return to_route('admin.categories.index');
    }

    /**
     * Pulihkan kategori yang dihapus (soft delete).
     */
    public function restore(Category $category): RedirectResponse
    {
        $category->restore();

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => "Kategori {$category->nama} berhasil dipulihkan.",
        ]);

        return to_route('admin.categories.index');
    }
}

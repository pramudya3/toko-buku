<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreKasCategoryRequest;
use App\Http\Requests\Admin\StoreKasSubCategoryRequest;
use App\Http\Requests\Admin\UpdateKasCategoryRequest;
use App\Http\Requests\Admin\UpdateKasSubCategoryRequest;
use App\Models\CashFlow;
use App\Models\KasCategory;
use App\Models\KasSubCategory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class KasCategoryController extends Controller
{
    public function index(Request $request): Response
    {
        $categories = KasCategory::query()
            ->withCount(['subCategories'])
            ->with(['subCategories' => fn ($q) => $q->withCount('cashFlows')->orderBy('sort_order')])
            ->orderBy('sort_order')
            ->orderBy('nama')
            ->get();

        // Untuk select di dialog kas — kategori aktif saja
        $activeCategories = KasCategory::where('is_active', true)
            ->with(['subCategories' => fn ($q) => $q->where('is_active', true)->orderBy('sort_order')])
            ->orderBy('sort_order')
            ->get(['id', 'nama']);

        return Inertia::render('admin/kas/Kategori', [
            'categories' => $categories,
            'activeCategories' => $activeCategories,
            'filters' => $request->only(['search']),
        ]);
    }

    // --- Kategori ---

    public function storeCategory(StoreKasCategoryRequest $request): RedirectResponse
    {
        $data = $request->validated();

        $max = KasCategory::max('sort_order') ?? 0;

        KasCategory::create([
            'nama' => $data['nama'],
            'sort_order' => $max + 1,
            'is_active' => true,
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => "Kategori {$data['nama']} berhasil dibuat."]);

        return back();
    }

    public function updateCategory(UpdateKasCategoryRequest $request, KasCategory $kasCategory): RedirectResponse
    {
        $data = $request->validated();

        $kasCategory->update([
            'nama' => $data['nama'],
            'is_active' => $data['is_active'] ?? $kasCategory->is_active,
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => "Kategori {$kasCategory->nama} diperbarui."]);

        return back();
    }

    public function destroyCategory(KasCategory $kasCategory): RedirectResponse
    {
        if ($kasCategory->subCategories()->exists() || CashFlow::where('kas_category_id', $kasCategory->id)->exists()) {
            Inertia::flash('toast', ['type' => 'error', 'message' => "Kategori {$kasCategory->nama} masih dipakai dan tidak bisa dihapus."]);

            return back();
        }

        $kasCategory->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => "Kategori {$kasCategory->nama} dihapus.", 'undo' => ['url' => route('admin.kas.categories.restore', $kasCategory)]]);

        return back();
    }

    public function restoreCategory(KasCategory $kasCategory): RedirectResponse
    {
        $kasCategory->restore();

        Inertia::flash('toast', ['type' => 'success', 'message' => "Kategori {$kasCategory->nama} dipulihkan."]);

        return back();
    }

    // --- Sub Kategori ---

    public function storeSub(StoreKasSubCategoryRequest $request): RedirectResponse
    {
        $data = $request->validated();

        $exists = KasSubCategory::where('cash_flow_category_id', $data['kas_category_id'])
            ->whereRaw('LOWER(nama) = ?', [strtolower($data['nama'])])
            ->exists();

        if ($exists) {
            Inertia::flash('toast', ['type' => 'error', 'message' => 'Sub kategori sudah ada di kategori ini.']);

            return back();
        }

        $max = KasSubCategory::where('cash_flow_category_id', $data['kas_category_id'])->max('sort_order') ?? 0;

        KasSubCategory::create([
            'cash_flow_category_id' => $data['kas_category_id'],
            'nama' => $data['nama'],
            'sort_order' => $max + 1,
            'is_active' => true,
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => "Sub kategori {$data['nama']} dibuat."]);

        return back();
    }

    public function updateSub(UpdateKasSubCategoryRequest $request, KasSubCategory $kasSubCategory): RedirectResponse
    {
        $data = $request->validated();

        $kasSubCategory->update([
            'nama' => $data['nama'],
            'is_active' => $data['is_active'] ?? $kasSubCategory->is_active,
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => "Sub kategori {$kasSubCategory->nama} diperbarui."]);

        return back();
    }

    public function destroySub(KasSubCategory $kasSubCategory): RedirectResponse
    {
        if (CashFlow::where('kas_sub_category_id', $kasSubCategory->id)->exists()) {
            Inertia::flash('toast', ['type' => 'error', 'message' => "Sub kategori {$kasSubCategory->nama} masih dipakai."]);

            return back();
        }

        $kasSubCategory->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => "Sub kategori {$kasSubCategory->nama} dihapus.", 'undo' => ['url' => route('admin.kas.subCategories.restore', $kasSubCategory)]]);

        return back();
    }

    public function restoreSub(KasSubCategory $kasSubCategory): RedirectResponse
    {
        $kasSubCategory->restore();

        Inertia::flash('toast', ['type' => 'success', 'message' => "Sub kategori {$kasSubCategory->nama} dipulihkan."]);

        return back();
    }
}

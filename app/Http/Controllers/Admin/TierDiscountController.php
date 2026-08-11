<?php

namespace App\Http\Controllers\Admin;

use App\Enums\CustomerTier;
use App\Http\Controllers\Controller;
use App\Models\TierDiscount;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TierDiscountController extends Controller
{
    /**
     * List semua tier discount rules.
     */
    public function index(Request $request): Response
    {
        $tierDiscounts = TierDiscount::query()
            ->when($request->filled('search'), function ($query) use ($request): void {
                $query->where('tier', 'like', '%'.$request->string('search')->toString().'%');
            })
            ->when($request->filled('tier'), fn ($query) => $query->where('tier', $request->string('tier')->toString()))
            ->orderBy('tier')
            ->orderBy('min_qty')
            ->paginate(10)
            ->withQueryString();

        return Inertia::render('admin/tier-discounts/Index', [
            'tierDiscounts' => $tierDiscounts,
            'tierOptions' => CustomerTier::options(),
            'filters' => $request->only(['search', 'tier']),
        ]);
    }

    /**
     * Simpan tier discount baru.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'tier' => ['required', 'string', 'in:reguler,bazaf,guru,reseller'],
            'min_qty' => ['required', 'integer', 'min:1'],
            'discount_percent' => ['required', 'integer', 'min:0', 'max:100'],
        ]);

        TierDiscount::updateOrCreate(
            ['tier' => $validated['tier'], 'min_qty' => $validated['min_qty']],
            [
                'discount_percent' => $validated['discount_percent'],
            ],
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Tier discount berhasil disimpan.']);

        return to_route('admin.tier-discounts.index');
    }

    /**
     * Update tier discount.
     */
    public function update(Request $request, TierDiscount $tierDiscount): RedirectResponse
    {
        $validated = $request->validate([
            'min_qty' => ['required', 'integer', 'min:1'],
            'discount_percent' => ['required', 'integer', 'min:0', 'max:100'],
        ]);

        $tierDiscount->update([
            'min_qty' => $validated['min_qty'],
            'discount_percent' => $validated['discount_percent'],
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Tier discount berhasil diperbarui.']);

        return to_route('admin.tier-discounts.index');
    }

    /**
     * Hapus tier discount rule.
     */
    public function destroy(TierDiscount $tierDiscount): RedirectResponse
    {
        $tierDiscount->delete();

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Tier discount berhasil dihapus.',
            'undo' => ['url' => route('admin.tier-discounts.restore', $tierDiscount)],
        ]);

        return to_route('admin.tier-discounts.index');
    }

    /**
     * Pulihkan tier discount yang dihapus (soft delete).
     */
    public function restore(TierDiscount $tierDiscount): RedirectResponse
    {
        $tierDiscount->restore();

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Tier discount berhasil dipulihkan.',
        ]);

        return to_route('admin.tier-discounts.index');
    }
}

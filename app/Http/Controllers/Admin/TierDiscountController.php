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
    public function index(): Response
    {
        $tierDiscounts = TierDiscount::query()
            ->orderBy('tier')
            ->orderBy('min_qty')
            ->get()
            ->groupBy('tier');

        return Inertia::render('admin/tier-discounts/Index', [
            'tierDiscounts' => $tierDiscounts,
            'tierOptions' => CustomerTier::options(),
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
            'max_qty' => ['nullable', 'integer', 'gte:min_qty'],
            'discount_percent' => ['required', 'integer', 'min:0', 'max:100'],
        ]);

        TierDiscount::updateOrCreate(
            ['tier' => $validated['tier'], 'min_qty' => $validated['min_qty']],
            [
                'max_qty' => $validated['max_qty'] ?? null,
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
            'max_qty' => ['nullable', 'integer', 'gte:min_qty'],
            'discount_percent' => ['required', 'integer', 'min:0', 'max:100'],
        ]);

        $tierDiscount->update([
            'min_qty' => $validated['min_qty'],
            'max_qty' => $validated['max_qty'] ?? null,
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

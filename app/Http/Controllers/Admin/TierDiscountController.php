<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ActivityAction;
use App\Enums\CustomerTier;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreTierDiscountRequest;
use App\Http\Requests\Admin\TierDiscountImportRequest;
use App\Http\Requests\Admin\UpdateTierDiscountRequest;
use App\Models\TierDiscount;
use App\Support\ActivityLogger;
use App\Support\Pagination;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;

class TierDiscountController extends Controller
{
    /**
     * Import tier discount dari file CSV (kolom: tier, min_qty, discount_percent).
     */
    public function importCsv(TierDiscountImportRequest $request): RedirectResponse
    {
        $result = DB::transaction(function () use ($request): array {
            $rows = $this->parseCsvRows($request->file('file')->getRealPath());

            return TierDiscount::withoutEvents(fn (): array => $this->processTierRows($rows));
        });

        ActivityLogger::log(
            ActivityAction::TierDiscountImport,
            "Import CSV tier discount: {$result['created']} dibuat, {$result['updated']} diperbarui, {$result['skipped']} dilewati.",
            null,
            ['summary' => $result],
        );

        $message = "Import CSV selesai: {$result['created']} aturan baru, {$result['updated']} diperbarui, {$result['skipped']} dilewati.";

        if ($result['errors'] !== []) {
            $message .= ' '.count($result['errors']).' baris gagal ('.implode('; ', array_slice($result['errors'], 0, 3)).').';
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => $message]);

        return back();
    }

    /**
     * @return array<int, array{line: int, cells: array<int, string>}>
     */
    private function parseCsvRows(string $path): array
    {
        $handle = fopen($path, 'r');

        if ($handle === false) {
            throw new RuntimeException('Tidak dapat membaca file CSV.');
        }

        $rows = [];
        $lineNumber = 0;
        $isFirstRow = true;

        while (($cells = fgetcsv($handle, null, ',', '"', '\\')) !== false) {
            $lineNumber++;
            $cells = array_map(fn ($cell): string => trim((string) $cell), $cells);

            if ($isFirstRow) {
                $isFirstRow = false;
                $cells[0] = (string) preg_replace('/^\xEF\xBB\xBF/', '', $cells[0] ?? '');

                if (strtolower($cells[0]) === 'tier') {
                    continue; // header
                }
            }

            if (count(array_filter($cells, fn ($cell): bool => $cell !== '')) === 0) {
                continue;
            }

            $rows[] = ['line' => $lineNumber, 'cells' => $cells];
        }

        fclose($handle);

        return $rows;
    }

    /**
     * @param  array<int, array{line: int, cells: array<int, string>}>  $rows
     * @return array{created: int, updated: int, skipped: int, errors: list<string>}
     */
    private function processTierRows(array $rows): array
    {
        $created = 0;
        $updated = 0;
        $skipped = 0;
        $errors = [];
        $validTiers = CustomerTier::cases();

        foreach ($rows as $row) {
            $line = $row['line'];
            $cells = $row['cells'];
            $tier = strtolower(trim($cells[0] ?? ''));
            $minQty = (int) ($cells[1] ?? 0);
            $discount = (int) ($cells[2] ?? 0);

            $tierValid = collect($validTiers)->contains(fn (CustomerTier $case): bool => $case->value === $tier);

            if (! $tierValid) {
                $errors[] = "Baris {$line}: tier '{$tier}' tidak dikenal";

                continue;
            }

            if ($minQty < 1 || $discount < 0 || $discount > 100) {
                $errors[] = "Baris {$line}: min_qty/discount_percent tidak valid";

                continue;
            }

            $existing = TierDiscount::query()
                ->where('tier', $tier)
                ->where('min_qty', $minQty)
                ->first();

            if ($existing !== null && $existing->discount_percent === $discount) {
                $skipped++;

                continue;
            }

            TierDiscount::updateOrCreate(
                ['tier' => $tier, 'min_qty' => $minQty],
                ['discount_percent' => $discount],
            );

            $existing !== null ? $updated++ : $created++;
        }

        return compact('created', 'updated', 'skipped', 'errors');
    }

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
            ->paginate(Pagination::perPage($request))
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
    public function store(StoreTierDiscountRequest $request): RedirectResponse
    {
        $validated = $request->validated();

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
    public function update(UpdateTierDiscountRequest $request, TierDiscount $tierDiscount): RedirectResponse
    {
        $validated = $request->validated();

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

<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ActivityAction;
use App\Enums\FlowType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CashFlowMonthRequest;
use App\Http\Requests\Admin\CashFlowStoreRequest;
use App\Http\Requests\Admin\CashFlowUpdateRequest;
use App\Models\CashFlow;
use App\Models\CashFlowMonth;
use App\Models\KasCategory;
use App\Support\ActivityLogger;
use App\Support\Pagination;
use Carbon\CarbonInterface;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class CashFlowController extends Controller
{
    /**
     * Pencatatan kas — OPSI A: hanya manual (income/expense), penjualan tidak masuk Kas.
     * Dioptimalkan untuk 2GB VPS + 1000 concurrent baca: agregasi di DB (bukan PHP get()->groupBy) + index + cache 5 menit.
     */
    public function pencatatan(): Response
    {
        $cacheKey = 'kas:pencatatan:v2:'.CashFlow::max('updated_at').':'.CashFlowMonth::max('updated_at');

        $data = Cache::remember($cacheKey, 300, function (): array {
            $driver = DB::getDriverName();
            $isSqlite = $driver === 'sqlite';
            $bulanExpr = $isSqlite ? "strftime('%Y-%m', entry_date)" : "to_char(entry_date, 'YYYY-MM')";

            $rows = CashFlow::query()
                ->whereIn('flow_type', [FlowType::Income->value, FlowType::Expense->value])
                ->selectRaw("{$bulanExpr} as bulan")
                ->selectRaw('COUNT(*) as count')
                ->selectRaw("COALESCE(SUM(CASE WHEN flow_type = 'income' THEN amount ELSE 0 END),0) as masuk")
                ->selectRaw("COALESCE(SUM(CASE WHEN flow_type = 'expense' THEN amount ELSE 0 END),0) as keluar")
                ->groupByRaw($bulanExpr)
                ->orderByDesc('bulan')
                ->get();

            $months = $rows->mapWithKeys(fn ($row) => [
                $row->bulan => [
                    'key' => $row->bulan,
                    'label' => $this->monthLabel(Carbon::createFromFormat('Y-m', $row->bulan)),
                    'count' => (int) $row->count,
                    'masuk' => (int) $row->masuk,
                    'keluar' => (int) $row->keluar,
                ],
            ]);

            foreach (CashFlowMonth::all(['bulan']) as $month) {
                if (! $months->has($month->bulan)) {
                    $months->put($month->bulan, [
                        'key' => $month->bulan,
                        'label' => $this->monthLabel(Carbon::createFromFormat('Y-m', $month->bulan)),
                        'count' => 0,
                        'masuk' => 0,
                        'keluar' => 0,
                    ]);
                }
            }

            $months = $months->sortKeysDesc()->values()->all();

            $summary = CashFlow::query()
                ->whereIn('flow_type', [FlowType::Income->value, FlowType::Expense->value])
                ->selectRaw("COALESCE(SUM(CASE WHEN flow_type = 'income' THEN amount ELSE 0 END),0) as masuk")
                ->selectRaw("COALESCE(SUM(CASE WHEN flow_type = 'expense' THEN amount ELSE 0 END),0) as keluar")
                ->first();

            return [
                'months' => $months,
                'summary' => ['masuk' => (int) $summary->masuk, 'keluar' => (int) $summary->keluar],
            ];
        });

        return Inertia::render('admin/kas/Pencatatan', $data);
    }

    /**
     * Detail satu bulan — 2 tabel: pencatatan (uang masuk) & pengeluaran.
     */
    public function detail(string $bulan): Response
    {
        $date = Carbon::createFromFormat('Y-m', $bulan);

        if ($date === false || $date->format('Y-m') !== $bulan) {
            throw new NotFoundHttpException;
        }

        $flows = CashFlow::query()
            ->with(['kasCategory:id,nama', 'kasSubCategory:id,nama'])
            ->whereIn('flow_type', [FlowType::Income->value, FlowType::Expense->value])
            ->whereYear('entry_date', $date->year)
            ->whereMonth('entry_date', $date->month)
            ->orderBy('entry_date')
            ->orderBy('id')
            ->get();

        $split = fn (bool $inflow) => $flows
            ->filter(fn (CashFlow $flow): bool => $flow->flow_type?->isInflow() === $inflow)
            ->map(fn (CashFlow $flow): array => [
                'id' => $flow->id,
                'entry_date' => $flow->entry_date->toDateString(),
                'flow_type' => $flow->flow_type?->value,
                'description' => $flow->description,
                'kas_category_id' => $flow->kas_category_id,
                'kas_sub_category_id' => $flow->kas_sub_category_id,
                'kas_category' => $flow->kasCategory?->nama,
                'kas_sub_category' => $flow->kasSubCategory?->nama,
                'amount' => $flow->amount,
            ])
            ->values()
            ->all();

        $masuk = $split(true);
        $keluar = $split(false);

        $monthRecord = CashFlowMonth::query()->where('bulan', $bulan)->first();
        $isClosed = (bool) ($monthRecord?->is_closed ?? false);

        $kasCategories = KasCategory::where('is_active', true)
            ->with(['subCategories' => fn ($q) => $q->where('is_active', true)->orderBy('sort_order')])
            ->orderBy('sort_order')
            ->get(['id', 'nama']);

        return Inertia::render('admin/kas/KasDetail', [
            'bulan' => $bulan,
            'bulan_label' => $this->monthLabel($date),
            'is_closed' => $isClosed,
            'kasCategories' => $kasCategories,
            'summary' => [
                'masuk' => (int) collect($masuk)->sum('amount'),
                'keluar' => (int) collect($keluar)->sum('amount'),
            ],
            'pencatatan' => $masuk,
            'pengeluaran' => $keluar,
        ]);
    }

    /**
     * Buka bulan baru untuk pencatatan kas (belum ada entri sama sekali).
     * Gagal bila bulan sudah punya entri atau sudah pernah dibuka.
     */
    public function storeMonth(CashFlowMonthRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        $bulan = $validated['bulan'];
        $date = Carbon::createFromFormat('Y-m', $bulan);

        $exists = CashFlowMonth::query()->where('bulan', $bulan)->exists()
            || CashFlow::query()->whereIn('flow_type', [FlowType::Income->value, FlowType::Expense->value])
                ->whereYear('entry_date', $date->year)
                ->whereMonth('entry_date', $date->month)
                ->exists();

        if ($exists) {
            Inertia::flash('toast', [
                'type' => 'error',
                'message' => "Bulan {$this->monthLabel($date)} sudah ada.",
            ]);

            return back();
        }

        $month = CashFlowMonth::create(['bulan' => $bulan]);

        ActivityLogger::log(
            ActivityAction::CashMonthCreate,
            "Bulan {$this->monthLabel($date)} dibuka untuk pencatatan",
            $month,
            ['bulan' => $bulan],
        );

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => "Bulan {$this->monthLabel($date)} berhasil dibuka — silakan catat entri kas.",
        ]);

        return to_route('admin.kas.detail', $bulan);
    }

    /**
     * Laporan arus kas — OPSI A: hanya Kas manual (income/expense).
     */
    public function laporan(Request $request): Response
    {
        $bulan = $request->string('bulan')->toString();
        $query = CashFlow::query()->whereIn('flow_type', [FlowType::Income->value, FlowType::Expense->value]);

        if ($bulan !== '' && preg_match('/^\d{4}-\d{2}$/', $bulan) === 1) {
            $date = Carbon::createFromFormat('Y-m', $bulan);
            $query->whereYear('entry_date', $date->year)
                ->whereMonth('entry_date', $date->month);
        } else {
            $bulan = '';
        }

        $flows = (clone $query)
            ->with(['kasCategory:id,nama', 'kasSubCategory:id,nama'])
            ->orderByDesc('entry_date')
            ->orderByDesc('id')
            ->paginate(Pagination::perPage($request))
            ->withQueryString();

        $flows->getCollection()->transform(fn (CashFlow $flow): array => [
            'id' => $flow->id,
            'entry_date' => $flow->entry_date->toDateString(),
            'flow_type' => $flow->flow_type?->value,
            'description' => $flow->description,
            'kas_category' => $flow->kasCategory?->nama,
            'kas_sub_category' => $flow->kasSubCategory?->nama,
            'amount' => $flow->amount,
        ]);

        $summary = (clone $query)
            ->selectRaw("
                COALESCE(SUM(CASE WHEN flow_type = 'income' THEN amount ELSE 0 END), 0) as inflow,
                COALESCE(SUM(CASE WHEN flow_type = 'expense' THEN amount ELSE 0 END), 0) as outflow
            ")
            ->first();

        return Inertia::render('admin/kas/Laporan', [
            'flows' => $flows,
            'filters' => ['bulan' => $bulan],
            'monthOptions' => $this->monthOptions(),
            'summary' => [
                'inflow' => (int) $summary->getAttribute('inflow'),
                'outflow' => (int) $summary->getAttribute('outflow'),
                'net' => (int) $summary->getAttribute('inflow') - (int) $summary->getAttribute('outflow'),
            ],
            'flowOptions' => FlowType::options(),
        ]);
    }

    /**
     * Cek apakah bulan dalam format Y-m sudah ditutup.
     */
    private function isMonthClosed(string $bulan): bool
    {
        return (bool) CashFlowMonth::query()->where('bulan', $bulan)->value('is_closed');
    }

    /**
     * Tutup bulan — tidak bisa tambah/edit setelah ini.
     */
    public function close(Request $request, string $bulan): RedirectResponse
    {
        $date = Carbon::createFromFormat('Y-m', $bulan);

        if ($date === false || $date->format('Y-m') !== $bulan) {
            throw new NotFoundHttpException;
        }

        $month = CashFlowMonth::query()->firstOrCreate(['bulan' => $bulan]);

        if ($month->is_closed) {
            Inertia::flash('toast', ['type' => 'info', 'message' => "Bulan {$this->monthLabel($date)} sudah ditutup."]);

            return back();
        }

        $month->update([
            'is_closed' => true,
            'closed_at' => now(),
            'closed_by' => $request->user()->id,
        ]);

        ActivityLogger::log(ActivityAction::CashMonthCreate, "Bulan {$this->monthLabel($date)} ditutup", $month, ['bulan' => $bulan, 'action' => 'close']);

        Inertia::flash('toast', ['type' => 'success', 'message' => "Bulan {$this->monthLabel($date)} berhasil ditutup — pencatatan terkunci."]);

        return back();
    }

    /**
     * Buka kembali bulan yang sudah ditutup.
     */
    public function reopen(string $bulan): RedirectResponse
    {
        $date = Carbon::createFromFormat('Y-m', $bulan);

        if ($date === false || $date->format('Y-m') !== $bulan) {
            throw new NotFoundHttpException;
        }

        $month = CashFlowMonth::query()->where('bulan', $bulan)->first();

        if ($month === null || ! $month->is_closed) {
            Inertia::flash('toast', ['type' => 'info', 'message' => "Bulan {$this->monthLabel($date)} masih terbuka."]);

            return back();
        }

        $month->update([
            'is_closed' => false,
            'closed_at' => null,
            'closed_by' => null,
        ]);

        ActivityLogger::log(ActivityAction::CashMonthCreate, "Bulan {$this->monthLabel($date)} dibuka kembali", $month, ['bulan' => $bulan, 'action' => 'reopen']);

        Inertia::flash('toast', ['type' => 'success', 'message' => "Bulan {$this->monthLabel($date)} dibuka kembali."]);

        return back();
    }

    /**
     * Simpan entri kas manual (income / expense).
     */
    public function store(CashFlowStoreRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        $bulan = Carbon::parse($validated['entry_date'])->format('Y-m');

        if ($this->isMonthClosed($bulan)) {
            Inertia::flash('toast', ['type' => 'error', 'message' => "Bulan {$this->monthLabel(Carbon::createFromFormat('Y-m', $bulan))} sudah ditutup — tidak bisa menambah entri."]);

            return back();
        }

        $flow = CashFlow::create([
            'order_id' => null,
            'entry_date' => $validated['entry_date'],
            'flow_type' => $validated['flow_type'],
            'amount' => (int) $validated['amount'],
            'description' => $validated['description'],
            'kas_category_id' => $validated['kas_category_id'],
            'kas_sub_category_id' => $validated['kas_sub_category_id'],
        ]);

        $label = $flow->flow_type instanceof FlowType
            ? $flow->flow_type->label()
            : FlowType::tryFrom((string) $flow->flow_type)?->label() ?? 'Kas';

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => "{$label} Rp ".number_format($flow->amount, 0, ',', '.').' berhasil dicatat.',
        ]);

        ActivityLogger::log(
            ActivityAction::CashEntry,
            "{$label} Rp ".number_format($flow->amount, 0, ',', '.').' dicatat',
            $flow,
            ['flow_type' => $flow->flow_type->value, 'amount' => $flow->amount],
        );

        return back();
    }

    /**
     * Update entri kas manual — hanya sebelum bulan di-close & hanya manual (order_id null).
     */
    public function update(CashFlowUpdateRequest $request, CashFlow $cashFlow): RedirectResponse
    {
        if ($cashFlow->order_id !== null) {
            Inertia::flash('toast', ['type' => 'error', 'message' => 'Entri otomatis dari pesanan tidak bisa diedit.']);

            return back();
        }

        $oldBulan = $cashFlow->entry_date->format('Y-m');

        if ($this->isMonthClosed($oldBulan)) {
            Inertia::flash('toast', ['type' => 'error', 'message' => "Bulan {$this->monthLabel(Carbon::createFromFormat('Y-m', $oldBulan))} sudah ditutup — tidak bisa diedit."]);

            return back();
        }

        $validated = $request->validated();
        $newBulan = Carbon::parse($validated['entry_date'])->format('Y-m');

        if ($newBulan !== $oldBulan && $this->isMonthClosed($newBulan)) {
            Inertia::flash('toast', ['type' => 'error', 'message' => "Bulan tujuan {$this->monthLabel(Carbon::createFromFormat('Y-m', $newBulan))} sudah ditutup."]);

            return back();
        }

        $cashFlow->update([
            'entry_date' => $validated['entry_date'],
            'flow_type' => $validated['flow_type'],
            'amount' => (int) $validated['amount'],
            'description' => $validated['description'],
            'kas_category_id' => $validated['kas_category_id'],
            'kas_sub_category_id' => $validated['kas_sub_category_id'],
        ]);

        $label = $cashFlow->flow_type instanceof FlowType ? $cashFlow->flow_type->label() : 'Kas';

        Inertia::flash('toast', ['type' => 'success', 'message' => "{$label} berhasil diperbarui."]);

        ActivityLogger::log(ActivityAction::CashEntry, "{$label} Rp ".number_format($cashFlow->amount, 0, ',', '.').' diperbarui', $cashFlow, ['action' => 'update', 'amount' => $cashFlow->amount]);

        return back();
    }

    /**
     * Hapus entri kas manual — hanya sebelum bulan di-close & hanya manual.
     */
    public function destroy(CashFlow $cashFlow): RedirectResponse
    {
        if ($cashFlow->order_id !== null) {
            Inertia::flash('toast', ['type' => 'error', 'message' => 'Entri otomatis dari pesanan tidak bisa dihapus.']);

            return back();
        }

        $bulan = $cashFlow->entry_date->format('Y-m');

        if ($this->isMonthClosed($bulan)) {
            Inertia::flash('toast', ['type' => 'error', 'message' => "Bulan {$this->monthLabel(Carbon::createFromFormat('Y-m', $bulan))} sudah ditutup — tidak bisa menghapus."]);

            return back();
        }

        $label = $cashFlow->flow_type instanceof FlowType ? $cashFlow->flow_type->label() : 'Kas';
        $amount = $cashFlow->amount;

        $cashFlow->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => "{$label} Rp ".number_format($amount, 0, ',', '.').' dihapus.']);

        ActivityLogger::log(ActivityAction::CashEntry, "{$label} Rp ".number_format($amount, 0, ',', '.').' dihapus', $cashFlow, ['action' => 'delete', 'amount' => $amount]);

        return back();
    }

    /**
     * Opsi bulan (dari data) untuk filter laporan — terbaru dulu.
     *
     * @return array<int, array{value: string, label: string}>
     */
    private function monthOptions(): array
    {
        $options = collect();

        // Bulan yang dibuka manual ikut tersedia di filter laporan.
        foreach (CashFlowMonth::all() as $month) {
            $options->put($month->bulan, $this->monthLabel(Carbon::createFromFormat('Y-m', $month->bulan)));
        }

        CashFlow::query()->whereIn('flow_type', [FlowType::Income->value, FlowType::Expense->value])
            ->orderByDesc('entry_date')
            ->get(['entry_date'])
            ->groupBy(fn (CashFlow $flow): string => $flow->entry_date->format('Y-m'))
            ->each(function ($grouped) use ($options): void {
                $options->put($grouped->first()->entry_date->format('Y-m'), $this->monthLabel($grouped->first()->entry_date));
            });

        return $options
            ->sortKeysDesc()
            ->map(fn (string $label, string $value): array => ['value' => $value, 'label' => $label])
            ->values()
            ->all();
    }

    private function monthLabel(CarbonInterface $date): string
    {
        $months = [
            1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
            5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
            9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember',
        ];

        return $months[$date->month].' '.$date->year;
    }
}

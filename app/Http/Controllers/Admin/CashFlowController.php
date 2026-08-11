<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ActivityAction;
use App\Enums\FlowType;
use App\Http\Controllers\Controller;
use App\Models\CashFlow;
use App\Models\CashFlowMonth;
use App\Support\ActivityLogger;
use Carbon\CarbonInterface;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class CashFlowController extends Controller
{
    /**
     * Pencatatan kas — tabel bulanan (total masuk / keluar per bulan).
     */
    public function pencatatan(): Response
    {
        // Ambil semua arus kas, kelompokkan per bulan di PHP (DB-agnostic).
        $flows = CashFlow::query()
            ->orderBy('entry_date')
            ->get(['id', 'entry_date', 'flow_type', 'amount']);

        $months = $flows
            ->groupBy(fn (CashFlow $flow): string => $flow->entry_date->format('Y-m'))
            ->map(function ($grouped) {
                $sum = fn (bool $inflow): int => (int) $grouped
                    ->filter(fn (CashFlow $flow): bool => $flow->flow_type?->isInflow() === $inflow)
                    ->sum('amount');

                return [
                    'key' => $grouped->first()->entry_date->format('Y-m'),
                    'label' => $this->monthLabel($grouped->first()->entry_date),
                    'count' => $grouped->count(),
                    'masuk' => $sum(true),
                    'keluar' => $sum(false),
                ];
            });

        // Bulan yang dibuka manual (belum punya entri) ikut tampil.
        foreach (CashFlowMonth::all() as $month) {
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

        return Inertia::render('admin/kas/Pencatatan', [
            'months' => $months,
            'summary' => [
                'masuk' => (int) $flows->sum(fn (CashFlow $flow): int => $flow->flow_type?->isInflow() ? $flow->amount : 0),
                'keluar' => (int) $flows->sum(fn (CashFlow $flow): int => $flow->flow_type?->isInflow() ? 0 : $flow->amount),
            ],
        ]);
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
            ->with('order:id,no_order')
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
                'order_no' => $flow->order?->no_order,
                'amount' => $flow->amount,
            ])
            ->values()
            ->all();

        $masuk = $split(true);
        $keluar = $split(false);

        return Inertia::render('admin/kas/KasDetail', [
            'bulan' => $bulan,
            'bulan_label' => $this->monthLabel($date),
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
    public function storeMonth(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'bulan' => ['required', 'string', 'regex:/^\d{4}-\d{2}$/'],
        ]);

        $bulan = $validated['bulan'];
        $date = Carbon::createFromFormat('Y-m', $bulan);

        $exists = CashFlowMonth::query()->where('bulan', $bulan)->exists()
            || CashFlow::query()
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
     * Laporan arus kas — filter per bulan atau keseluruhan.
     */
    public function laporan(Request $request): Response
    {
        $bulan = $request->string('bulan')->toString();
        $query = CashFlow::query()->with('order:id,no_order');

        if ($bulan !== '' && preg_match('/^\d{4}-\d{2}$/', $bulan) === 1) {
            $date = Carbon::createFromFormat('Y-m', $bulan);
            $query->whereYear('entry_date', $date->year)
                ->whereMonth('entry_date', $date->month);
        } else {
            $bulan = '';
        }

        $flows = (clone $query)
            ->orderByDesc('entry_date')
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString();

        $summary = (clone $query)
            ->selectRaw("
                COALESCE(SUM(CASE WHEN flow_type IN ('revenue', 'shipping', 'income') THEN amount ELSE 0 END), 0) as inflow,
                COALESCE(SUM(CASE WHEN flow_type IN ('refund', 'expense') THEN amount ELSE 0 END), 0) as outflow
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
     * Simpan entri kas manual (income / expense).
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'flow_type' => ['required', 'string', 'in:income,expense'],
            'entry_date' => ['required', 'date'],
            'amount' => ['required', 'integer', 'min:1'],
            'description' => ['required', 'string', 'max:500'],
        ]);

        $flow = CashFlow::create([
            'order_id' => null,
            'entry_date' => $validated['entry_date'],
            'flow_type' => $validated['flow_type'],
            'amount' => (int) $validated['amount'],
            'description' => $validated['description'],
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

        CashFlow::query()
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

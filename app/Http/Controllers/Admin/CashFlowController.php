<?php

namespace App\Http\Controllers\Admin;

use App\Enums\FlowType;
use App\Http\Controllers\Controller;
use App\Models\CashFlow;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CashFlowController extends Controller
{
    /**
     * Laporan arus kas read-only + filter tanggal (CF-01, CF-02, CF-05).
     */
    public function index(Request $request): Response
    {
        $from = $request->filled('from') ? $request->date('from') : now()->startOfMonth();
        $to = $request->filled('to') ? $request->date('to') : now();

        $flows = CashFlow::query()
            ->with('order:id,no_order')
            ->whereDate('entry_date', '>=', $from->toDateString())
            ->whereDate('entry_date', '<=', $to->toDateString())
            ->orderByDesc('entry_date')
            ->orderByDesc('id')
            ->paginate(10)
            ->withQueryString();

        $summary = CashFlow::whereDate('entry_date', '>=', $from->toDateString())
            ->whereDate('entry_date', '<=', $to->toDateString())
            ->selectRaw("
                COALESCE(SUM(CASE WHEN flow_type IN ('revenue', 'shipping') THEN amount ELSE 0 END), 0) as inflow,
                COALESCE(SUM(CASE WHEN flow_type = 'refund' THEN amount ELSE 0 END), 0) as outflow
            ")
            ->first();

        return Inertia::render('admin/cash-flow/Index', [
            'flows' => $flows,
            'filters' => [
                'from' => $from->toDateString(),
                'to' => $to->toDateString(),
            ],
            'summary' => [
                'inflow' => (int) $summary->getAttribute('inflow'),
                'outflow' => (int) $summary->getAttribute('outflow'),
                'net' => (int) $summary->getAttribute('inflow') - (int) $summary->getAttribute('outflow'),
            ],
            'flowOptions' => FlowType::options(),
        ]);
    }
}

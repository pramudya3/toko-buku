<?php

namespace App\Http\Controllers\Admin;

use App\Enums\FlowType;
use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Models\Book;
use App\Models\CashFlow;
use App\Models\Order;
use Carbon\CarbonInterface;
use Carbon\CarbonPeriod;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    /**
     * Ringkasan statistik dashboard (DASH-01..05, CF-06).
     */
    public function index(Request $request): Response
    {
        $period = $request->integer('period', 30);

        $start = now()->subDays(max(1, min($period, 365)))->startOfDay();

        $revenue = CashFlow::where('flow_type', FlowType::Revenue->value)
            ->whereDate('entry_date', '>=', $start->toDateString())
            ->sum('amount');

        $ordersInPeriod = Order::where('created_at', '>=', $start)->count();

        $cashInThisMonth = CashFlow::whereIn('flow_type', [FlowType::Revenue->value, FlowType::Shipping->value])
            ->whereDate('entry_date', '>=', now()->startOfMonth()->toDateString())
            ->sum('amount');

        $bookCount = Book::count();
        $totalStock = Book::sum('stok');

        $lowStock = Book::where('aktif', true)
            ->where('stok', '<=', config('pricing.low_stock_threshold'))
            ->orderBy('stok')
            ->limit(10)
            ->get(['id', 'judul', 'kode_sku', 'stok']);

        $recentOrders = Order::with('user:id,name')
            ->orderByDesc('created_at')
            ->limit(10)
            ->get(['id', 'no_order', 'nama_pembeli', 'total', 'status', 'created_at']);

        $salesPerDay = $this->salesPerDay($start);

        return Inertia::render('admin/Dashboard', [
            'period' => $period,
            'stats' => [
                'revenue' => $revenue,
                'orders_count' => $ordersInPeriod,
                'cash_in_month' => $cashInThisMonth,
                'book_count' => $bookCount,
                'total_stock' => $totalStock,
            ],
            'lowStockBooks' => $lowStock,
            'recentOrders' => $recentOrders,
            'salesChart' => $salesPerDay,
            'statusOptions' => OrderStatus::options(),
        ]);
    }

    /**
     * Grafik penjualan harian (DASH-04) — total revenue per hari utk periode.
     *
     * @return array<int, array{date: string, total: int}>
     */
    private function salesPerDay(CarbonInterface $start): array
    {
        $rows = CashFlow::where('flow_type', FlowType::Revenue->value)
            ->whereDate('entry_date', '>=', $start->toDateString())
            ->selectRaw('entry_date, SUM(amount) as total')
            ->groupBy('entry_date')
            ->orderBy('entry_date')
            ->get()
            ->keyBy(fn (CashFlow $row): string => $row->entry_date->toDateString())
            ->map(fn (CashFlow $row): int => (int) $row->getAttribute('total'));

        $days = [];

        foreach (CarbonPeriod::create($start, now()) as $day) {
            $date = $day->toDateString();

            $days[] = [
                'date' => $date,
                'total' => (int) ($rows[$date] ?? 0),
            ];
        }

        return $days;
    }
}

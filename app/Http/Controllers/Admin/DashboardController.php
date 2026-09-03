<?php

namespace App\Http\Controllers\Admin;

use App\Enums\FlowType;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Http\Controllers\Controller;
use App\Models\Book;
use App\Models\CashFlow;
use App\Models\Order;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Carbon\CarbonPeriod;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Concurrency;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    /**
     * Ringkasan statistik dashboard (DASH-01..05, CF-06).
     *
     * Statistik kartu memakai periode bulan terpilih (default: bulan
     * berjalan); chart iterasi seluruh hari pada bulan tersebut.
     */
    public function index(Request $request): Response
    {
        $bulan = $request->filled('bulan') ? $request->string('bulan')->toString() : now()->format('Y-m');

        if (! preg_match('/^\\d{4}-\\d{2}$/', $bulan)) {
            $bulan = now()->format('Y-m');
        }

        $start = Carbon::parse($bulan.'-01')->startOfMonth();
        $isCurrentMonth = $bulan === now()->format('Y-m');
        $end = $isCurrentMonth ? now() : $start->copy()->endOfMonth();

        $computeStats = function () use ($start, $end): array {
            $useConcurrency = ! app()->runningUnitTests() && config('database.default') !== 'sqlite' && class_exists(Concurrency::class);
            if ($useConcurrency) {
                try {
                    [$revenue, $refund, $ordersInPeriod, $cashInMonth, $bookCount, $totalStock] = Concurrency::run([
                        fn () => (int) Order::where('status', OrderStatus::Selesai->value)
                            ->whereBetween('created_at', [$start->startOfDay(), $end->endOfDay()])
                            ->selectRaw('COALESCE(SUM(total - shipping_cost),0) as sum')
                            ->value('sum'),
                        fn () => (int) Order::where('status', OrderStatus::Batal->value)
                            ->whereBetween('updated_at', [$start->startOfDay(), $end->endOfDay()])
                            ->selectRaw('COALESCE(SUM(total - shipping_cost),0) as sum')
                            ->value('sum'),
                        fn () => Order::whereBetween('created_at', [$start->startOfDay(), $end->endOfDay()])->count(),
                        function () use ($start, $end): int {
                            $cashIn = (int) Order::where('status', OrderStatus::Selesai->value)
                                ->where('metode_bayar', PaymentMethod::Cash->value)
                                ->whereBetween('created_at', [$start->startOfDay(), $end->endOfDay()])
                                ->selectRaw('COALESCE(SUM(total - shipping_cost),0) as sum')
                                ->value('sum');
                            $cashRefund = (int) Order::where('status', OrderStatus::Batal->value)
                                ->where('metode_bayar', PaymentMethod::Cash->value)
                                ->whereBetween('updated_at', [$start->startOfDay(), $end->endOfDay()])
                                ->selectRaw('COALESCE(SUM(total - shipping_cost),0) as sum')
                                ->value('sum');

                            return max(0, $cashIn - $cashRefund);
                        },
                        fn () => Book::count(),
                        fn () => Book::sum('stok'),
                    ]);
                } catch (\Throwable) {
                    $useConcurrency = false;
                }
            }
            if (! $useConcurrency) {
                $revenue = (int) Order::where('status', OrderStatus::Selesai->value)
                    ->whereBetween('created_at', [$start->startOfDay(), $end->endOfDay()])
                    ->selectRaw('COALESCE(SUM(total - shipping_cost),0) as sum')
                    ->value('sum');
                $refund = (int) Order::where('status', OrderStatus::Batal->value)
                    ->whereBetween('updated_at', [$start->startOfDay(), $end->endOfDay()])
                    ->selectRaw('COALESCE(SUM(total - shipping_cost),0) as sum')
                    ->value('sum');
                $ordersInPeriod = Order::whereBetween('created_at', [$start->startOfDay(), $end->endOfDay()])->count();
                $cashIn = (int) Order::where('status', OrderStatus::Selesai->value)
                    ->where('metode_bayar', PaymentMethod::Cash->value)
                    ->whereBetween('created_at', [$start->startOfDay(), $end->endOfDay()])
                    ->selectRaw('COALESCE(SUM(total - shipping_cost),0) as sum')
                    ->value('sum');
                $cashRefund = (int) Order::where('status', OrderStatus::Batal->value)
                    ->where('metode_bayar', PaymentMethod::Cash->value)
                    ->whereBetween('updated_at', [$start->startOfDay(), $end->endOfDay()])
                    ->selectRaw('COALESCE(SUM(total - shipping_cost),0) as sum')
                    ->value('sum');
                $cashInMonth = max(0, $cashIn - $cashRefund);
                $bookCount = Book::count();
                $totalStock = Book::sum('stok');
            }

            $lowStock = Book::where('aktif', true)
                ->where('stok', '>', 0)
                ->where('stok', '<=', config('pricing.low_stock_threshold'))
                ->orderBy('stok')
                ->limit(10)
                ->get(['id', 'judul', 'kode_sku', 'stok']);

            $emptyStock = Book::where('aktif', true)
                ->where('stok', 0)
                ->orderByDesc('updated_at')
                ->limit(10)
                ->get(['id', 'judul', 'kode_sku', 'stok']);

            $recentOrders = Order::with('user:id,name')
                ->orderByDesc('created_at')
                ->limit(10)
                ->get(['id', 'no_order', 'nama_pembeli', 'total', 'status', 'created_at']);

            $salesPerDay = $this->salesPerDay($start, $end);

            return [
                'revenue' => $revenue,
                'refund' => $refund,
                'ordersInPeriod' => $ordersInPeriod,
                'cashInMonth' => $cashInMonth,
                'bookCount' => $bookCount,
                'totalStock' => $totalStock,
                'lowStock' => $lowStock,
                'emptyStock' => $emptyStock,
                'recentOrders' => $recentOrders,
                'salesPerDay' => $salesPerDay,
            ];
        };

        $stats = app()->environment('testing')
            ? $computeStats()
            : Cache::remember('dashboard:stats:'.$bulan.':'.today()->toDateString().':'.Order::max('updated_at').':'.Book::max('updated_at'), 60, $computeStats);

        // Fallback legacyRefund untuk data lama (CashFlow refund)
        $legacyRefund = CashFlow::where('flow_type', FlowType::Refund->value)
            ->whereBetween('entry_date', [$start->toDateString(), $end->toDateString()])
            ->sum('amount');
        $refund = max($stats['refund'], $legacyRefund);
        $revenue = max(0, $stats['revenue'] - $refund);
        $cashInMonth = $stats['cashInMonth'];

        // Opsi bulan: dari order pertama s.d. terakhir (selalu include bulan berjalan).
        $minDate = Order::min('created_at');
        $monthStart = $minDate ? Carbon::parse($minDate)->startOfMonth() : $start->copy()->firstOfYear();
        $monthEnd = $start->copy()->startOfMonth()->max(now()->startOfMonth());
        $monthOptions = [];

        foreach (CarbonPeriod::create($monthStart, '1 month', $monthEnd) as $month) {
            $monthOptions[] = $month->format('Y-m');
        }

        return Inertia::render('admin/Dashboard', [
            'stats' => [
                'revenue' => $revenue,
                'orders_count' => $stats['ordersInPeriod'],
                'cash_in_month' => $cashInMonth,
                'book_count' => $stats['bookCount'],
                'total_stock' => $stats['totalStock'],
            ],
            'lowStockBooks' => $stats['lowStock'],
            'emptyStockBooks' => $stats['emptyStock'],
            'lowStockThreshold' => config('pricing.low_stock_threshold'),
            'recentOrders' => $stats['recentOrders'],
            'salesChart' => $stats['salesPerDay'],
            'statusOptions' => OrderStatus::options(),
            'monthOptions' => $monthOptions,
            'filters' => ['bulan' => $bulan],
        ]);
    }

    /**
     * Grafik penjualan harian (DASH-04) — net revenue per hari pada bulan
     * terpilih (revenue order dikurangi refund retur).
     *
     * @return array<int, array{date: string, total: int}>
     */
    private function salesPerDay(CarbonInterface $start, CarbonInterface $end): array
    {
        // OPSI A: grafik dari orders (bukan cash_flows) — tanpa ongkir.
        $rows = Order::where('status', OrderStatus::Selesai->value)
            ->whereBetween('created_at', [$start->startOfDay(), $end->endOfDay()])
            ->selectRaw('DATE(created_at) as entry_date, SUM(total - shipping_cost) as total')
            ->groupByRaw('DATE(created_at)')
            ->orderByRaw('DATE(created_at)')
            ->get()
            ->keyBy(fn (Order $row): string => $row->getAttribute('entry_date'))
            ->map(fn (Order $row): int => (int) $row->getAttribute('total'));

        $days = [];

        foreach (CarbonPeriod::create($start, $end->copy()->endOfDay()) as $day) {
            $date = $day->toDateString();

            $days[] = [
                'date' => $date,
                'total' => (int) ($rows[$date] ?? 0),
            ];
        }

        return $days;
    }
}

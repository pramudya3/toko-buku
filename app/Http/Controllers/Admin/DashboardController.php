<?php

namespace App\Http\Controllers\Admin;

use App\Enums\FlowType;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
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
     *
     * Statistik kartu memakai bulan berjalan (tanggal 1 s.d. hari ini),
     * chart memakai 30 hari terakhir (rolling) supaya selalu penuh.
     */
    public function index(Request $request): Response
    {
        // Statistik: bulan berjalan (kalender).
        $start = now()->startOfMonth();

        // Revenue bersih periode = pendapatan order − refund retur penjualan.
        $revenue = CashFlow::where('flow_type', FlowType::Revenue->value)
            ->whereDate('entry_date', '>=', $start->toDateString())
            ->sum('amount');

        $refund = CashFlow::where('flow_type', FlowType::Refund->value)
            ->whereDate('entry_date', '>=', $start->toDateString())
            ->sum('amount');

        $revenue -= $refund;

        $ordersInPeriod = Order::where('created_at', '>=', $start)->count();

        // Uang cash bulan ini — HANYA order yang dibayar tunai (metode_bayar = cash),
        // dikurangi refund retur order cash.
        $cashInMonth = CashFlow::whereIn('flow_type', [FlowType::Revenue->value, FlowType::Shipping->value])
            ->whereDate('entry_date', '>=', now()->startOfMonth()->toDateString())
            ->whereHas('order', fn ($query) => $query->where('metode_bayar', PaymentMethod::Cash->value))
            ->sum('amount');

        $cashRefundMonth = CashFlow::where('flow_type', FlowType::Refund->value)
            ->whereDate('entry_date', '>=', now()->startOfMonth()->toDateString())
            ->whereHas('order', fn ($query) => $query->where('metode_bayar', PaymentMethod::Cash->value))
            ->sum('amount');

        $cashInMonth = max(0, $cashInMonth - $cashRefundMonth);

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

        // Grafik menampilkan bulan berjalan (tanggal 1 s.d. hari ini) —
        // konsisten dengan statistik kartu; label tanggal (dd) terbaca.
        $salesPerDay = $this->salesPerDay(now()->startOfMonth());

        return Inertia::render('admin/Dashboard', [
            'stats' => [
                'revenue' => $revenue,
                'orders_count' => $ordersInPeriod,
                'cash_in_month' => $cashInMonth,
                'book_count' => $bookCount,
                'total_stock' => $totalStock,
            ],
            'lowStockBooks' => $lowStock,
            'lowStockThreshold' => config('pricing.low_stock_threshold'),
            'recentOrders' => $recentOrders,
            'salesChart' => $salesPerDay,
            'statusOptions' => OrderStatus::options(),
        ]);
    }

    /**
     * Grafik penjualan harian (DASH-04) — net revenue per hari utk bulan
     * berjalan (1 s.d. hari ini; revenue order dikurangi refund retur).
     *
     * @return array<int, array{date: string, total: int}>
     */
    private function salesPerDay(CarbonInterface $start): array
    {
        $rows = CashFlow::whereIn('flow_type', [FlowType::Revenue->value, FlowType::Refund->value])
            ->whereDate('entry_date', '>=', $start->toDateString())
            ->selectRaw("entry_date, SUM(CASE WHEN flow_type = '".FlowType::Revenue->value."' THEN amount ELSE -amount END) as total")
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

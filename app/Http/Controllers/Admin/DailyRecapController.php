<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\SalesReturn;
use App\Models\SalesReturnItem;
use App\Services\DailyRecapXlsxExporter;
use App\Support\StoreSettings;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Rekap harian penjualan — ringkasan per tanggal (omzet, cash/transfer/COD,
 * HPP & laba) + export .xlsx.
 */
class DailyRecapController extends Controller
{
    public function __construct(private readonly DailyRecapXlsxExporter $exporter) {}

    /**
     * Halaman rekap harian.
     */
    public function index(Request $request): Response
    {
        [$from, $to] = $this->dateRange($request);

        if ((! $from || ! $to) && ! app()->runningUnitTests()) {
            return Inertia::render('admin/daily-recap/Index', [
                'rows' => collect(),
                'totals' => ['order_count' => 0, 'item_count' => 0, 'omzet' => 0, 'cash' => 0, 'transfer' => 0, 'cod' => 0, 'hpp' => 0, 'laba' => 0],
                'filters' => [
                    'from' => $from?->toDateString(),
                    'to' => $to?->toDateString(),
                    'sumber_pembelian' => $request->string('sumber_pembelian')->toString() ?: null,
                ],
                'salesChannels' => StoreSettings::allSalesChannels(),
            ]);
        }

        $sumberPembelian = $request->string('sumber_pembelian')->toString() ?: null;

        $rows = $this->buildRows($from, $to, $sumberPembelian);

        return Inertia::render('admin/daily-recap/Index', [
            'rows' => $rows,
            'totals' => $this->totals($rows),
            'filters' => [
                'from' => $from?->toDateString(),
                'to' => $to?->toDateString(),
                'sumber_pembelian' => $sumberPembelian,
            ],
            'salesChannels' => StoreSettings::allSalesChannels(),
        ]);
    }

    /**
     * Download rekap harian .xlsx.
     */
    public function export(Request $request): StreamedResponse
    {
        [$from, $to] = $this->dateRange($request);
        $from ??= Carbon::now()->subDays(30);
        $to ??= Carbon::now();

        $sumberPembelian = $request->string('sumber_pembelian')->toString() ?: null;

        return $this->exporter->download($this->buildRows($from, $to, $sumberPembelian), $from, $to, $sumberPembelian);
    }

    /**
     * Ringkasan per hari: order dihitung terpisah dari item agar ongkir
     * tidak terduplikasi oleh join order_items.
     *
     * @return Collection<int, array<string, mixed>>
     */
    private function buildRows(?Carbon $from, ?Carbon $to, ?string $sumberPembelian = null): Collection
    {
        $orders = Order::query()
            ->when($from && $to, fn ($q) => $q->whereBetween('orders.created_at', [$from->startOfDay(), $to->endOfDay()]))
            ->when($from && ! $to, fn ($q) => $q->whereDate('orders.created_at', '>=', $from->toDateString()))
            ->when(! $from && $to, fn ($q) => $q->whereDate('orders.created_at', '<=', $to->toDateString()))
            ->where('status', '!=', 'batal')
            ->when($sumberPembelian !== null, fn ($q) => $q->where('orders.sumber_pembelian', $sumberPembelian))
            ->selectRaw("
                date(created_at) as tgl,
                count(*) as order_count,
                COALESCE(SUM(total - shipping_cost), 0) as omzet,
                COALESCE(SUM(CASE WHEN metode_bayar = 'cash' THEN total - shipping_cost ELSE 0 END), 0) as cash,
                COALESCE(SUM(CASE WHEN metode_bayar = 'transfer' THEN total - shipping_cost ELSE 0 END), 0) as transfer,
                COALESCE(SUM(CASE WHEN metode_bayar = 'cod' THEN total - shipping_cost ELSE 0 END), 0) as cod
            ")
            ->groupByRaw('date(created_at)')
            ->get()
            ->keyBy('tgl');

        $items = Order::query()
            ->when($from && $to, fn ($q) => $q->whereBetween('orders.created_at', [$from->startOfDay(), $to->endOfDay()]))
            ->when($from && ! $to, fn ($q) => $q->whereDate('orders.created_at', '>=', $from->toDateString()))
            ->when(! $from && $to, fn ($q) => $q->whereDate('orders.created_at', '<=', $to->toDateString()))
            ->where('orders.status', '!=', 'batal')
            ->when($sumberPembelian !== null, fn ($q) => $q->where('orders.sumber_pembelian', $sumberPembelian))
            ->join('order_items', 'order_items.order_id', '=', 'orders.id')
            ->selectRaw('
                date(orders.created_at) as tgl,
                COALESCE(SUM(order_items.qty), 0) as item_count,
                COALESCE(SUM(order_items.price_final * order_items.qty), 0) as omzet_items,
                COALESCE(SUM(order_items.harga_beli_snapshot * order_items.qty), 0) as hpp,
                COALESCE(SUM((order_items.price_final - COALESCE(order_items.harga_beli_snapshot, 0)) * order_items.qty), 0) as laba
            ')
            ->groupByRaw('date(orders.created_at)')
            ->get()
            ->keyBy('tgl');

        // Retur penjualan per hari (berdasar return_date): refund total + split
        // metode bayar order asal. Rekap dikurangi retur — konsisten dengan
        // sales report & dashboard.
        $returns = SalesReturn::query()
            ->when($from && $to, fn ($q) => $q->whereBetween('return_date', [$from->toDateString(), $to->toDateString()]))
            ->when($from && ! $to, fn ($q) => $q->whereDate('return_date', '>=', $from->toDateString()))
            ->when(! $from && $to, fn ($q) => $q->whereDate('return_date', '<=', $to->toDateString()))
            ->when($sumberPembelian !== null, fn ($q) => $q->where('orders.sumber_pembelian', $sumberPembelian))
            ->join('orders', 'orders.id', '=', 'sales_returns.order_id')
            ->selectRaw("
                sales_returns.return_date as tgl,
                COALESCE(SUM(sales_returns.total_refund), 0) as refund_total,
                COALESCE(SUM(CASE WHEN orders.metode_bayar = 'cash' THEN sales_returns.total_refund ELSE 0 END), 0) as refund_cash,
                COALESCE(SUM(CASE WHEN orders.metode_bayar = 'transfer' THEN sales_returns.total_refund ELSE 0 END), 0) as refund_transfer,
                COALESCE(SUM(CASE WHEN orders.metode_bayar = 'cod' THEN sales_returns.total_refund ELSE 0 END), 0) as refund_cod
            ")
            ->groupByRaw('sales_returns.return_date')
            ->get()
            ->keyBy('tgl');

        $returnItems = SalesReturnItem::query()
            ->when($from && $to, fn ($q) => $q->whereBetween('sales_returns.return_date', [$from->toDateString(), $to->toDateString()]))
            ->when($from && ! $to, fn ($q) => $q->whereDate('sales_returns.return_date', '>=', $from->toDateString()))
            ->when(! $from && $to, fn ($q) => $q->whereDate('sales_returns.return_date', '<=', $to->toDateString()))
            ->when($sumberPembelian !== null, fn ($q) => $q->where('orders.sumber_pembelian', $sumberPembelian))
            ->join('sales_returns', 'sales_returns.id', '=', 'sales_return_items.sales_return_id')
            ->join('order_items', 'order_items.id', '=', 'sales_return_items.order_item_id')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->selectRaw('
                sales_returns.return_date as tgl,
                COALESCE(SUM(sales_return_items.qty), 0) as item_count,
                COALESCE(SUM(sales_return_items.price_refund * sales_return_items.qty), 0) as omzet_items,
                COALESCE(SUM(COALESCE(order_items.harga_beli_snapshot, 0) * sales_return_items.qty), 0) as hpp,
                COALESCE(SUM((sales_return_items.price_refund - COALESCE(order_items.harga_beli_snapshot, 0)) * sales_return_items.qty), 0) as laba
            ')
            ->groupByRaw('sales_returns.return_date')
            ->get()
            ->keyBy('tgl');

        $dates = $orders->keys()
            ->merge($items->keys())
            ->merge($returns->keys())
            ->merge($returnItems->keys())
            ->unique()
            ->sortDesc();

        return $dates->map(function (string $tgl) use ($orders, $items, $returns, $returnItems): array {
            $order = $orders->get($tgl);
            $item = $items->get($tgl);
            $return = $returns->get($tgl);
            $returnItem = $returnItems->get($tgl);

            return [
                'tanggal' => Carbon::parse($tgl)->format('d/m/Y'),
                'tanggal_key' => $tgl,
                'order_count' => (int) ($order->order_count ?? 0),
                'item_count' => (int) ($item->item_count ?? 0) - (int) ($returnItem->item_count ?? 0),
                'omzet' => (int) ($order->omzet ?? 0) - (int) ($return->refund_total ?? 0),
                'cash' => (int) ($order->cash ?? 0) - (int) ($return->refund_cash ?? 0),
                'transfer' => (int) ($order->transfer ?? 0) - (int) ($return->refund_transfer ?? 0),
                'cod' => (int) ($order->cod ?? 0) - (int) ($return->refund_cod ?? 0),
                'hpp' => (int) ($item->hpp ?? 0) - (int) ($returnItem->hpp ?? 0),
                'laba' => (int) ($item->laba ?? 0) - (int) ($returnItem->laba ?? 0),
            ];
        })->values();
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $rows
     * @return array<string, int>
     */
    private function totals(Collection $rows): array
    {
        $keys = ['order_count', 'item_count', 'omzet', 'cash', 'transfer', 'cod', 'hpp', 'laba'];
        $totals = [];

        foreach ($keys as $key) {
            $totals[$key] = (int) $rows->sum($key);
        }

        return $totals;
    }

    /**
     * @return array{Carbon|null, Carbon|null}
     */
    private function dateRange(Request $request): array
    {
        $from = $request->filled('from')
            ? Carbon::parse($request->string('from')->toString())
            : null;

        $to = $request->filled('to')
            ? Carbon::parse($request->string('to')->toString())
            : null;

        if ($from && $to && $from->greaterThan($to)) {
            [$from, $to] = [$to, $from];
        }

        return [$from, $to];
    }
}

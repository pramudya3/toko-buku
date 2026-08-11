<?php

namespace App\Http\Controllers\Admin;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\SalesReturn;
use App\Services\SalesXlsxExporter;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Laporan penjualan: halaman ringkasan + export .xlsx.
 * Baris laporan per item order — HPP & laba per cetakan.
 */
class SalesReportController extends Controller
{
    public function __construct(private readonly SalesXlsxExporter $exporter) {}

    /**
     * Halaman laporan penjualan dengan filter periode, metode bayar & status.
     * Preview memakai baris per item — kolom identik dengan export .xlsx.
     */
    public function index(Request $request): Response
    {
        [$from, $to] = $this->dateRange($request);

        $metodeBayar = $request->string('metode_bayar')->toString() ?: null;
        $status = $request->string('status')->toString() ?: null;

        $orders = Order::query()
            ->whereBetween('created_at', [$from->startOfDay(), $to->endOfDay()])
            ->when($metodeBayar !== null, fn ($q) => $q->where('metode_bayar', $metodeBayar))
            ->when($status !== null, fn ($q) => $q->where('status', $status))
            ->with('items:id,order_id,judul_snapshot,edition_snapshot,qty,price_original,promo_discount_amount,tier_discount_amount,price_final,harga_beli_snapshot')
            ->orderByDesc('created_at')
            ->get();

        // Ringkasan (semua data, bukan hanya halaman).
        $summary = [
            'order_count' => $orders->count(),
            'omzet' => 0,
            'hpp' => 0,
            'laba' => 0,
            'shipping' => 0,
            'item_count' => 0,
        ];

        $rows = [];

        foreach ($orders as $order) {
            $summary['shipping'] += (int) $order->shipping_cost;

            foreach ($order->items as $item) {
                $hpp = (int) ($item->harga_beli_snapshot ?? 0);

                $summary['omzet'] += $item->price_final * $item->qty;
                $summary['hpp'] += $hpp * $item->qty;
                $summary['item_count']++;

                $rows[] = [
                    'tanggal' => $order->created_at->format('d/m/Y'),
                    'sort_date' => $order->created_at->toDateString(),
                    'no_order' => $order->no_order,
                    'pembeli' => $order->nama_pembeli,
                    'metode_bayar' => PaymentMethod::labelFor((string) $order->metode_bayar),
                    'status' => $order->status->label(),
                    'buku' => $item->judul_snapshot,
                    'cetakan' => $item->edition_snapshot ?? '',
                    'qty' => $item->qty,
                    'harga_asli' => $item->price_original,
                    'diskon' => $item->promo_discount_amount + $item->tier_discount_amount,
                    'harga_final' => $item->price_final,
                    'hpp' => $hpp,
                    'laba' => ($item->price_final - $hpp) * $item->qty,
                ];
            }
        }

        // Retur penjualan mengurangi omzet/HPP periode berjalan (periode
        // terjadinya retur, bukan periode penjualan) — konsisten dengan
        // pencatatan cash_flows (entry_date = return_date). Baris retur dibuat
        // negatif: qty = -qty, harga_final/hpp tetap per-unit positif, laba negatif.
        $returns = SalesReturn::query()
            ->whereBetween('return_date', [$from->toDateString(), $to->toDateString()])
            ->when($metodeBayar !== null, fn ($q) => $q->whereHas('order', fn ($oq) => $oq->where('metode_bayar', $metodeBayar)))
            ->when($status !== null, fn ($q) => $q->whereHas('order', fn ($oq) => $oq->where('status', $status)))
            ->with([
                'order:id,no_order,nama_pembeli,metode_bayar,status',
                'items:id,sales_return_id,order_item_id,qty,price_refund',
                'items.orderItem:id,judul_snapshot,edition_snapshot,harga_beli_snapshot',
            ])
            ->get();

        foreach ($returns as $return) {
            $statusLabel = $return->order->status instanceof OrderStatus
                ? $return->order->status->label()
                : (string) $return->order->status;

            foreach ($return->items as $item) {
                $hpp = (int) ($item->orderItem->harga_beli_snapshot ?? 0);
                $qty = (int) $item->qty;

                $summary['omzet'] -= $item->price_refund * $qty;
                $summary['hpp'] -= $hpp * $qty;
                $summary['item_count']--; // baris item, konsisten dengan penjualan

                $rows[] = [
                    'tanggal' => $return->return_date->format('d/m/Y'),
                    'sort_date' => $return->return_date->toDateString(),
                    'no_order' => $return->order->no_order,
                    'pembeli' => $return->order->nama_pembeli,
                    'metode_bayar' => PaymentMethod::labelFor((string) $return->order->metode_bayar),
                    'status' => $statusLabel.' (retur)',
                    'buku' => $item->orderItem->judul_snapshot,
                    'cetakan' => $item->orderItem->edition_snapshot ?? '',
                    'qty' => -$qty,
                    'harga_asli' => 0,
                    'diskon' => 0,
                    'harga_final' => $item->price_refund,
                    'hpp' => $hpp,
                    'laba' => -($item->price_refund - $hpp) * $qty,
                ];
            }
        }

        // Urutkan gabungan baris jual + retur dari terbaru (stabil di PHP 8+).
        usort($rows, fn (array $a, array $b): int => $b['sort_date'] <=> $a['sort_date']);

        $summary['laba'] = $summary['omzet'] - $summary['hpp'];

        $page = max(1, (int) $request->query('page', 1));
        $perPage = 15;

        $paginated = new LengthAwarePaginator(
            collect($rows)->forPage($page, $perPage)->values(),
            count($rows),
            $perPage,
            $page,
            ['path' => LengthAwarePaginator::resolveCurrentPath()],
        );

        return Inertia::render('admin/sales-reports/Index', [
            'rows' => $paginated->withQueryString(),
            'summary' => $summary,
            'filters' => [
                'from' => $from->toDateString(),
                'to' => $to->toDateString(),
                'metode_bayar' => $metodeBayar,
                'status' => $status,
            ],
            'paymentOptions' => PaymentMethod::options(),
            'statusOptions' => OrderStatus::options(),
        ]);
    }

    /**
     * Download laporan penjualan .xlsx.
     */
    public function export(Request $request): StreamedResponse
    {
        [$from, $to] = $this->dateRange($request);

        $metodeBayar = $request->string('metode_bayar')->toString() ?: null;
        $status = $request->string('status')->toString() ?: null;

        $rows = $this->exporter->buildRows($from, $to, $metodeBayar, $status);

        return $this->exporter->download($rows, $from, $to, $metodeBayar, $status);
    }

    /**
     * Rentang tanggal dari filter — default: awal bulan sampai hari ini.
     *
     * @return array{Carbon, Carbon}
     */
    private function dateRange(Request $request): array
    {
        $from = $request->filled('from')
            ? Carbon::parse($request->string('from')->toString())
            : Carbon::now()->startOfMonth();

        $to = $request->filled('to')
            ? Carbon::parse($request->string('to')->toString())
            : Carbon::now();

        if ($from->greaterThan($to)) {
            [$from, $to] = [$to, $from];
        }

        return [$from, $to];
    }
}

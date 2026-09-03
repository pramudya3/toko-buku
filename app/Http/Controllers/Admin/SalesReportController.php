<?php

namespace App\Http\Controllers\Admin;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\SalesChannel as SalesChannelEnum;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\SalesReturn;
use App\Services\SalesXlsxExporter;
use App\Support\Pagination;
use App\Support\StoreSettings;
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
     * UI: 1 baris = 1 faktur (expand untuk detail item) — export tetap per item.
     */
    public function index(Request $request): Response
    {
        [$from, $to] = $this->dateRange($request);

        $metodeBayar = $request->string('metode_bayar')->toString() ?: null;
        $status = $request->string('status')->toString() ?: null;
        $sumberPembelian = $request->string('sumber_pembelian')->toString() ?: null;

        // Default kosong biar tidak berat — baru load saat admin pakai filter tanggal
        if (! $from || ! $to) {
            return Inertia::render('admin/sales-reports/Index', [
                'orders' => new LengthAwarePaginator([], 0, Pagination::perPage($request), 1, ['path' => LengthAwarePaginator::resolveCurrentPath()]),
                'rows' => new LengthAwarePaginator([], 0, Pagination::perPage($request), 1, ['path' => LengthAwarePaginator::resolveCurrentPath()]),
                'summary' => [
                    'order_count' => 0, 'omzet' => 0, 'hpp' => 0, 'laba' => 0, 'shipping' => 0, 'voucher_discount' => 0, 'item_count' => 0,
                ],
                'filters' => [
                    'from' => $from?->toDateString(),
                    'to' => $to?->toDateString(),
                    'metode_bayar' => $metodeBayar,
                    'status' => $status,
                    'sumber_pembelian' => $sumberPembelian,
                ],
                'paymentOptions' => PaymentMethod::options(),
                'statusOptions' => OrderStatus::options(),
                'salesChannels' => StoreSettings::allSalesChannels(),
            ]);
        }

        $orders = Order::query()
            ->whereBetween('created_at', [$from->startOfDay(), $to->endOfDay()])
            ->when($metodeBayar !== null, fn ($q) => $q->where('metode_bayar', $metodeBayar))
            ->when($status !== null, fn ($q) => $q->where('status', $status))
            ->when($sumberPembelian !== null, fn ($q) => $q->where('sumber_pembelian', $sumberPembelian))
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
            'voucher_discount' => 0,
            'item_count' => 0,
        ];

        $orderGroups = [];

        foreach ($orders as $order) {
            $summary['shipping'] += (int) $order->shipping_cost;

            if ($order->status !== OrderStatus::Batal) {
                $summary['voucher_discount'] += (int) $order->voucher_discount_amount;
            }

            $items = [];
            $orderQty = 0;
            $orderTotal = 0;
            $orderHpp = 0;
            $orderLaba = 0;

            foreach ($order->items as $item) {
                $hpp = (int) ($item->harga_beli_snapshot ?? 0);
                $total = $item->price_final * $item->qty;
                $laba = ($item->price_final - $hpp) * $item->qty;

                $summary['omzet'] += $total;
                $summary['hpp'] += $hpp * $item->qty;
                $summary['item_count']++;

                $orderQty += $item->qty;
                $orderTotal += $total;
                $orderHpp += $hpp * $item->qty;
                $orderLaba += $laba;

                $items[] = [
                    'buku' => $item->judul_snapshot,
                    'cetakan' => $item->edition_snapshot ?? '',
                    'qty' => $item->qty,
                    'harga_asli' => $item->price_original,
                    'diskon' => $item->promo_discount_amount + $item->tier_discount_amount,
                    'harga_final' => $item->price_final,
                    'total' => $total,
                    'hpp' => $hpp,
                    'laba' => $laba,
                ];
            }

            $orderGroups[] = [
                'id' => $order->id,
                'no_order' => $order->no_order,
                'tanggal' => $order->created_at->format('d/m/Y'),
                'sort_date' => $order->created_at->toDateString(),
                'pembeli' => $order->nama_pembeli,
                'sumber' => $order->sumber_pembelian !== null ? SalesChannelEnum::labelFor((string) $order->sumber_pembelian) : '',
                'metode_bayar' => PaymentMethod::labelFor((string) $order->metode_bayar),
                'status' => $order->status->label(),
                'shipping_cost' => (int) $order->shipping_cost,
                'voucher_discount' => (int) $order->voucher_discount_amount,
                'qty_total' => $orderQty,
                'total' => $orderTotal,
                'hpp_total' => $orderHpp,
                'laba_total' => $orderLaba,
                'items' => $items,
                'is_retur' => false,
            ];
        }

        // Retur penjualan mengurangi omzet/HPP periode berjalan (periode
        // terjadinya retur, bukan periode penjualan) — konsisten dengan
        // pencatatan cash_flows (entry_date = return_date). Baris retur dibuat
        // negatif: qty = -qty, harga_final/hpp tetap per-unit positif, laba negatif.
        $returns = SalesReturn::query()
            ->when($from && $to, fn ($q) => $q->whereBetween('return_date', [$from->toDateString(), $to->toDateString()]))
            ->when($metodeBayar !== null, fn ($q) => $q->whereHas('order', fn ($oq) => $oq->where('metode_bayar', $metodeBayar)))
            ->when($status !== null, fn ($q) => $q->whereHas('order', fn ($oq) => $oq->where('status', $status)))
            ->when($sumberPembelian !== null, fn ($q) => $q->whereHas('order', fn ($oq) => $oq->where('sumber_pembelian', $sumberPembelian)))
            ->with([
                'order:id,no_order,nama_pembeli,metode_bayar,status,sumber_pembelian',
                'items:id,sales_return_id,order_item_id,qty,price_refund',
                'items.orderItem:id,judul_snapshot,edition_snapshot,harga_beli_snapshot',
            ])
            ->get();

        // Retur: 1 group = 1 faktur retur (untuk expand, tapi qty negatif)
        $returnGroups = [];
        foreach ($returns as $return) {
            $statusLabel = $return->order->status instanceof OrderStatus ? $return->order->status->label() : (string) $return->order->status;
            $items = [];
            $retQty = 0;
            $retTotal = 0;
            $retHpp = 0;
            $retLaba = 0;
            foreach ($return->items as $item) {
                $hpp = (int) ($item->orderItem->harga_beli_snapshot ?? 0);
                $qty = (int) $item->qty;
                $summary['omzet'] -= $item->price_refund * $qty;
                $summary['hpp'] -= $hpp * $qty;
                $summary['item_count']--;
                $retQty -= $qty;
                $retTotal -= $item->price_refund * $qty;
                $retHpp -= $hpp * $qty;
                $retLaba -= ($item->price_refund - $hpp) * $qty;
                $items[] = [
                    'buku' => $item->orderItem->judul_snapshot,
                    'cetakan' => $item->orderItem->edition_snapshot ?? '',
                    'qty' => -$qty,
                    'harga_asli' => 0,
                    'diskon' => 0,
                    'harga_final' => $item->price_refund,
                    'total' => -$item->price_refund * $qty,
                    'hpp' => $hpp,
                    'laba' => -($item->price_refund - $hpp) * $qty,
                ];
            }
            $returnGroups[] = [
                'id' => 'retur-'.$return->id,
                'no_order' => $return->order->no_order,
                'tanggal' => $return->return_date->format('d/m/Y'),
                'sort_date' => $return->return_date->toDateString(),
                'pembeli' => $return->order->nama_pembeli,
                'sumber' => $return->order->sumber_pembelian !== null ? SalesChannelEnum::labelFor((string) $return->order->sumber_pembelian) : '',
                'metode_bayar' => PaymentMethod::labelFor((string) $return->order->metode_bayar),
                'status' => $statusLabel.' (retur)',
                'shipping_cost' => 0,
                'voucher_discount' => 0,
                'qty_total' => $retQty,
                'total' => $retTotal,
                'hpp_total' => -$retHpp,
                'laba_total' => $retLaba,
                'items' => $items,
                'is_retur' => true,
            ];
        }

        $allGroups = collect(array_merge($orderGroups, $returnGroups))->sortByDesc('sort_date')->values()->all();

        $summary['omzet'] -= $summary['voucher_discount'];
        $summary['laba'] = $summary['omzet'] - $summary['hpp'];

        $page = max(1, (int) $request->query('page', 1));
        $perPage = Pagination::perPage($request);

        $paginated = new LengthAwarePaginator(
            collect($allGroups)->forPage($page, $perPage)->values(),
            count($allGroups),
            $perPage,
            $page,
            ['path' => LengthAwarePaginator::resolveCurrentPath()],
        );

        // Flat rows per item (legacy untuk test suite) — harus include order + retur
        $flatOrderRows = collect($orderGroups)->flatMap(fn ($g) => array_map(fn ($it) => array_merge($it, [
            'tanggal' => $g['tanggal'], 'sort_date' => $g['sort_date'], 'no_order' => $g['no_order'], 'pembeli' => $g['pembeli'], 'sumber' => $g['sumber'], 'metode_bayar' => $g['metode_bayar'], 'status' => $g['status'],
        ]), $g['items']))->values();
        $flatReturnRows = collect($returnGroups)->flatMap(fn ($g) => array_map(fn ($it) => array_merge($it, [
            'tanggal' => $g['tanggal'], 'sort_date' => $g['sort_date'], 'no_order' => $g['no_order'], 'pembeli' => $g['pembeli'], 'sumber' => $g['sumber'], 'metode_bayar' => $g['metode_bayar'], 'status' => $g['status'],
        ]), $g['items']))->values();
        $flatRows = $flatOrderRows->merge($flatReturnRows)->sortByDesc('sort_date')->values()->all();

        return Inertia::render('admin/sales-reports/Index', [
            'orders' => $paginated->withQueryString(),
            'rows' => new LengthAwarePaginator(collect($flatRows)->forPage($page, $perPage)->values(), count($flatRows), $perPage, $page, ['path' => LengthAwarePaginator::resolveCurrentPath()]), // legacy
            'summary' => $summary,
            'filters' => [
                'from' => $from?->toDateString(),
                'to' => $to?->toDateString(),
                'metode_bayar' => $metodeBayar,
                'status' => $status,
                'sumber_pembelian' => $sumberPembelian,
            ],
            'paymentOptions' => PaymentMethod::options(),
            'statusOptions' => OrderStatus::options(),
            'salesChannels' => StoreSettings::allSalesChannels(),
        ]);
    }

    /**
     * Download laporan penjualan .xlsx.
     */
    public function export(Request $request): StreamedResponse
    {
        [$from, $to] = $this->dateRange($request);
        $from ??= Carbon::now()->subDays(30);
        $to ??= Carbon::now();

        $metodeBayar = $request->string('metode_bayar')->toString() ?: null;
        $status = $request->string('status')->toString() ?: null;
        $sumberPembelian = $request->string('sumber_pembelian')->toString() ?: null;

        $rows = $this->exporter->buildRows($from, $to, $metodeBayar, $status, $sumberPembelian);

        return $this->exporter->download($rows, $from, $to, $metodeBayar, $status, $sumberPembelian);
    }

    /**
     * Rentang tanggal dari filter — null jika tidak difilter (Semua).
     *
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

<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\SalesChannel as SalesChannelEnum;
use App\Models\Order;
use App\Models\SalesReturn;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Laporan penjualan (.xlsx): baris per item order dengan HPP & laba,
 * plus ringkasan omzet, HPP, laba, dan ongkir.
 */
final class SalesXlsxExporter
{
    private const HEADERS = [
        'Tanggal',
        'No. Order',
        'Pembeli',
        'Sumber',
        'Metode Bayar',
        'Status',
        'Buku',
        'Cetakan',
        'Qty',
        'Harga Asli',
        'Diskon',
        'Harga Final',
        'Total',
        'HPP',
        'Laba',
    ];

    /**
     * Baris per item order dalam rentang tanggal, dengan filter opsional
     * metode bayar, status & sumber penjualan.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function buildRows(CarbonInterface $from, CarbonInterface $to, ?string $metodeBayar = null, ?string $status = null, ?string $sumberPembelian = null): Collection
    {
        $orders = Order::query()
            ->whereBetween('created_at', [$from->startOfDay(), $to->endOfDay()])
            ->when($metodeBayar !== null && $metodeBayar !== '', fn ($q) => $q->where('metode_bayar', $metodeBayar))
            ->when($status !== null && $status !== '', fn ($q) => $q->where('status', $status))
            ->when($sumberPembelian !== null && $sumberPembelian !== '', fn ($q) => $q->where('sumber_pembelian', $sumberPembelian))
            ->with('items:id,order_id,book_id,judul_snapshot,edition_snapshot,qty,price_original,promo_discount_amount,tier_discount_amount,price_final,harga_beli_snapshot')
            ->orderBy('created_at')
            ->get();

        $rows = collect();

        foreach ($orders as $order) {
            $metodeLabel = PaymentMethod::labelFor((string) $order->metode_bayar);
            $statusLabel = $order->status instanceof OrderStatus
                ? $order->status->label()
                : OrderStatus::tryFrom((string) $order->status)?->label() ?? (string) $order->status;

            foreach ($order->items as $item) {
                $hpp = (int) ($item->harga_beli_snapshot ?? 0);
                $laba = ($item->price_final - $hpp) * $item->qty;

                $rows->push([
                    'tanggal' => $order->created_at->format('d/m/Y'),
                    'sort_date' => $order->created_at->toDateString(),
                    'no_order' => $order->no_order,
                    'pembeli' => $order->nama_pembeli,
                    'sumber' => $order->sumber_pembelian !== null
                        ? SalesChannelEnum::labelFor((string) $order->sumber_pembelian)
                        : '',
                    'metode_bayar' => $metodeLabel,
                    'status' => $statusLabel,
                    'buku' => $item->judul_snapshot,
                    'cetakan' => $item->edition_snapshot ?? '',
                    'qty' => $item->qty,
                    'harga_asli' => $item->price_original,
                    'diskon' => $item->promo_discount_amount + $item->tier_discount_amount,
                    'harga_final' => $item->price_final,
                    'total' => $item->price_final * $item->qty,
                    'hpp' => $hpp,
                    'laba' => $laba,
                    'shipping_cost' => (int) $order->shipping_cost,
                ]);
            }
        }

        // Baris retur penjualan (negatif) — retur mengurangi omzet/HPP di
        // periode terjadinya retur, konsisten dengan preview halaman laporan.
        $returns = SalesReturn::query()
            ->whereBetween('return_date', [$from->toDateString(), $to->toDateString()])
            ->when($metodeBayar !== null && $metodeBayar !== '', fn ($q) => $q->whereHas('order', fn ($oq) => $oq->where('metode_bayar', $metodeBayar)))
            ->when($status !== null && $status !== '', fn ($q) => $q->whereHas('order', fn ($oq) => $oq->where('status', $status)))
            ->when($sumberPembelian !== null && $sumberPembelian !== '', fn ($q) => $q->whereHas('order', fn ($oq) => $oq->where('sumber_pembelian', $sumberPembelian)))
            ->with([
                'order:id,no_order,nama_pembeli,metode_bayar,status,sumber_pembelian',
                'items:id,sales_return_id,order_item_id,qty,price_refund',
                'items.orderItem:id,judul_snapshot,edition_snapshot,harga_beli_snapshot',
            ])
            ->get();

        foreach ($returns as $return) {
            $returnMetodeLabel = PaymentMethod::labelFor((string) $return->order->metode_bayar);
            $returnStatusLabel = $return->order->status instanceof OrderStatus
                ? $return->order->status->label()
                : (string) $return->order->status;

            foreach ($return->items as $item) {
                $hpp = (int) ($item->orderItem->harga_beli_snapshot ?? 0);
                $qty = (int) $item->qty;

                $rows->push([
                    'tanggal' => $return->return_date->format('d/m/Y'),
                    'sort_date' => $return->return_date->toDateString(),
                    'no_order' => $return->order->no_order,
                    'pembeli' => $return->order->nama_pembeli,
                    'sumber' => $return->order->sumber_pembelian !== null
                        ? SalesChannelEnum::labelFor((string) $return->order->sumber_pembelian)
                        : '',
                    'metode_bayar' => $returnMetodeLabel,
                    'status' => $returnStatusLabel.' (retur)',
                    'buku' => $item->orderItem->judul_snapshot,
                    'cetakan' => $item->orderItem->edition_snapshot ?? '',
                    'qty' => -$qty,
                    'harga_asli' => 0,
                    'diskon' => 0,
                    'harga_final' => $item->price_refund,
                    'total' => -$item->price_refund * $qty,
                    'hpp' => $hpp,
                    'laba' => -($item->price_refund - $hpp) * $qty,
                    'shipping_cost' => 0,
                ]);
            }
        }

        return $rows
            ->sortByDesc('sort_date')
            ->values();
    }

    /**
     * Download laporan sebagai file .xlsx (StreamedResponse).
     */
    public function download(Collection $rows, CarbonInterface $from, CarbonInterface $to, ?string $metodeBayar = null, ?string $status = null, ?string $sumberPembelian = null): StreamedResponse
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Laporan Penjualan');

        // Judul laporan
        $sheet->setCellValue('A1', "Laporan Penjualan ({$from->format('d/m/Y')} - {$to->format('d/m/Y')})");
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);

        $subtitleParts = [];

        if ($metodeBayar !== null && $metodeBayar !== '') {
            $subtitleParts[] = 'Metode: '.(PaymentMethod::tryFrom($metodeBayar)?->label() ?? $metodeBayar);
        }

        if ($status !== null && $status !== '') {
            $subtitleParts[] = 'Status: '.(OrderStatus::tryFrom($status)?->label() ?? $status);
        }

        if ($sumberPembelian !== null && $sumberPembelian !== '') {
            $subtitleParts[] = 'Sumber: '.(SalesChannelEnum::tryFrom($sumberPembelian)?->label() ?? $sumberPembelian);
        }

        $sheet->setCellValue('A2', implode(' | ', $subtitleParts));
        $sheet->getStyle('A2')->getFont()->setBold(true)->setSize(11);

        // Header tabel
        $headerRow = 4;
        foreach (self::HEADERS as $i => $header) {
            $column = chr(65 + $i);
            $sheet->setCellValue($column.$headerRow, $header);
        }

        $headerStyle = $sheet->getStyle("A{$headerRow}:O{$headerRow}");
        $headerStyle->getFont()->setBold(true);
        $headerStyle->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFE2E8F0');
        $headerStyle->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        // Baris data
        $rowIndex = $headerRow + 1;
        $totalOmzet = 0;
        $totalDiskon = 0;
        $totalHpp = 0;
        $totalLaba = 0;
        $orderIds = [];

        foreach ($rows as $row) {
            $sheet->fromArray([
                $row['tanggal'],
                $row['no_order'],
                $row['pembeli'],
                $row['sumber'],
                $row['metode_bayar'],
                $row['status'],
                $row['buku'],
                $row['cetakan'],
                $row['qty'],
                $row['harga_asli'],
                $row['diskon'],
                $row['harga_final'],
                $row['total'],
                $row['hpp'],
                $row['laba'],
            ], null, "A{$rowIndex}");

            $totalOmzet += $row['harga_final'] * $row['qty'];
            $totalDiskon += $row['diskon'] * $row['qty'];
            $totalHpp += $row['hpp'] * $row['qty'];
            $totalLaba += $row['laba'];

            $rowIndex++;
        }

        // Ringkasan — ongkir TIDAK termasuk revenue
        $sheet->setCellValue("K{$rowIndex}", 'Total Omzet (barang)');
        $sheet->setCellValue("L{$rowIndex}", $totalOmzet);
        $sheet->setCellValue('K'.($rowIndex + 1), 'Total Diskon');
        $sheet->setCellValue('L'.($rowIndex + 1), $totalDiskon);
        $sheet->setCellValue('K'.($rowIndex + 2), 'Total HPP');
        $sheet->setCellValue('L'.($rowIndex + 2), $totalHpp);
        $sheet->setCellValue('K'.($rowIndex + 3), 'Total Laba Kotor');
        $sheet->setCellValue('L'.($rowIndex + 3), $totalLaba);
        $sheet->setCellValue('K'.($rowIndex + 4), 'Jumlah Baris Item');
        $sheet->setCellValue('L'.($rowIndex + 4), $rows->count());

        $summaryRange = "K{$rowIndex}:L".($rowIndex + 4);
        $sheet->getStyle($summaryRange)->getFont()->setBold(true);
        $sheet->getStyle($summaryRange)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFFEF3C7');

        // Format angka
        $sheet->getStyle("H{$headerRow}:O{$rowIndex}")->getNumberFormat()->setFormatCode('#,##0');

        // Lebar kolom
        foreach (range('A', 'O') as $column) {
            $sheet->getColumnDimension($column)->setWidth(
                match ($column) {
                    'A' => 12,
                    'B' => 20,
                    'C' => 26,
                    'D' => 14,
                    'E' => 14,
                    'F' => 18,
                    'G' => 40,
                    'H' => 14,
                    'I', 'J', 'K', 'L', 'M', 'N', 'O' => 13,
                    default => 13,
                },
            );
        }

        $fileName = 'laporan-penjualan_'.$from->format('Ymd').'_'.$to->format('Ymd').'.xlsx';

        return response()->streamDownload(function () use ($spreadsheet): void {
            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output');
        }, $fileName, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }
}

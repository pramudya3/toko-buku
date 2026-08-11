<?php

namespace App\Services;

use App\Models\Supplier;
use App\Models\SupplierPurchase;
use App\Models\SupplierReturn;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Laporan transaksi supplier (pembelian, retur, pembayaran) ke .xlsx.
 */
final class SupplierXlsxExporter
{
    private const HEADERS = [
        'Tanggal',
        'Jenis',
        'Ref Code',
        'Supplier',
        'Item',
        'Qty',
        'Harga',
        'Subtotal',
        'Alasan',
        'Keterangan',
    ];

    /**
     * Baris barang masuk (pembelian) & retur dalam rentang tanggal
     * (opsional per supplier). `$jenis` membatasi jenis transaksi:
     * `semua` | `pembelian` | `retur`.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function buildRows(CarbonInterface $from, CarbonInterface $to, ?string $supplierId, string $jenis = 'semua'): Collection
    {
        $rows = collect();

        if ($jenis !== 'retur') {
            $purchases = SupplierPurchase::query()
                ->whereBetween('purchase_date', [$from->toDateString(), $to->toDateString()])
                ->when($supplierId !== null, fn ($q) => $q->where('supplier_id', $supplierId))
                ->with('supplier:id,nama', 'items.book:id,judul,kode_sku', 'warehouse:id,kode,nama')
                ->get();

            foreach ($purchases as $purchase) {
                foreach ($purchase->items as $item) {
                    $rows->push([
                        'tanggal' => $purchase->purchase_date->format('d/m/Y'),
                        'jenis' => 'Barang Masuk',
                        'ref_code' => $purchase->ref_code,
                        'supplier' => $purchase->supplier->nama,
                        'gudang' => $purchase->warehouse?->nama ?? '',
                        'item' => $item->book->judul,
                        'qty' => $item->qty,
                        'harga' => $item->price,
                        'subtotal' => $item->subtotal,
                        'alasan' => '',
                        'keterangan' => $purchase->notes ?? '',
                        'jenis_key' => 'purchase',
                    ]);
                }
            }
        }

        if ($jenis !== 'pembelian') {
            $returns = SupplierReturn::query()
                ->whereBetween('return_date', [$from->toDateString(), $to->toDateString()])
                ->when($supplierId !== null, fn ($q) => $q->where('supplier_id', $supplierId))
                ->with('supplier:id,nama', 'items.book:id,judul,kode_sku', 'purchase.warehouse:id,kode,nama')
                ->get();

            foreach ($returns as $return) {
                foreach ($return->items as $item) {
                    $rows->push([
                        'tanggal' => $return->return_date->format('d/m/Y'),
                        'jenis' => 'Retur',
                        'ref_code' => 'RET-'.$return->id,
                        'supplier' => $return->supplier->nama,
                        'gudang' => $return->purchase?->warehouse?->nama ?? '',
                        'item' => $item->book->judul,
                        'qty' => $item->qty,
                        'harga' => $item->price,
                        'subtotal' => -$item->subtotal,
                        'alasan' => $item->reason,
                        'keterangan' => $return->notes ?? '',
                        'jenis_key' => 'return',
                    ]);
                }
            }
        }

        return $rows->sortBy(fn (array $row): string => $row['tanggal'].' '.$row['jenis_key'])->values();
    }

    /**
     * Download laporan sebagai file .xlsx (StreamedResponse).
     */
    public function download(Collection $rows, CarbonInterface $from, CarbonInterface $to, ?string $supplierId, string $jenis = 'semua'): StreamedResponse
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Laporan Supplier');

        // Judul laporan
        $supplierLabel = $supplierId !== null ? Supplier::find($supplierId)?->nama ?? '' : 'Semua Supplier';
        $sheet->setCellValue('A1', "Laporan Barang Masuk & Retur Supplier ({$from->format('d/m/Y')} - {$to->format('d/m/Y')})");
        $sheet->setCellValue('A2', $supplierLabel);
        $sheet->getStyle('A1:A2')->getFont()->setBold(true)->setSize(12);
        $sheet->getStyle('A1')->getFont()->setSize(14);

        // Header tabel
        $headerRow = 4;
        foreach (self::HEADERS as $i => $header) {
            $column = chr(65 + $i);
            $sheet->setCellValue($column.$headerRow, $header);
        }

        $headerStyle = $sheet->getStyle("A{$headerRow}:J{$headerRow}");
        $headerStyle->getFont()->setBold(true);
        $headerStyle->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFE2E8F0');
        $headerStyle->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        // Baris data
        $rowIndex = $headerRow + 1;
        $totalPurchase = 0;
        $totalReturn = 0;

        foreach ($rows as $row) {
            $sheet->fromArray([
                $row['tanggal'],
                $row['jenis'],
                $row['ref_code'],
                $row['supplier'],
                $row['item'],
                $row['qty'],
                $row['harga'],
                $row['subtotal'],
                $row['alasan'],
                $row['keterangan'],
            ], null, "A{$rowIndex}");

            if ($row['jenis_key'] === 'purchase') {
                $totalPurchase += $row['subtotal'];
            } else {
                $totalReturn += abs($row['subtotal']);
            }

            $rowIndex++;
        }

        // Ringkasan
        $sheet->setCellValue("H{$rowIndex}", 'Total Barang Masuk');
        $sheet->setCellValue("I{$rowIndex}", $totalPurchase);
        $sheet->setCellValue('H'.($rowIndex + 1), 'Total Retur');
        $sheet->setCellValue('I'.($rowIndex + 1), $totalReturn);
        $sheet->setCellValue('H'.($rowIndex + 2), 'Selisih (Masuk − Retur)');
        $sheet->setCellValue('I'.($rowIndex + 2), $totalPurchase - $totalReturn);

        $summaryRange = "H{$rowIndex}:I".($rowIndex + 2);
        $sheet->getStyle($summaryRange)->getFont()->setBold(true);
        $sheet->getStyle($summaryRange)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFFEF3C7');

        // Format angka
        $sheet->getStyle("F{$headerRow}:J{$rowIndex}")->getNumberFormat()->setFormatCode('#,##0');

        // Lebar kolom
        foreach (range('A', 'J') as $column) {
            $sheet->getColumnDimension($column)->setWidth(
                match ($column) {
                    'A' => 12,
                    'B' => 14,
                    'C' => 18,
                    'D' => 24,
                    'E' => 40,
                    'F', 'G', 'H', 'I' => 12,
                    default => 30,
                },
            );
        }

        $fileName = 'laporan-supplier_'.$from->format('Ymd').'_'.$to->format('Ymd').'.xlsx';

        return response()->streamDownload(function () use ($spreadsheet): void {
            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output');
        }, $fileName, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }
}

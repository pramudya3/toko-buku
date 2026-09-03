<?php

namespace App\Services;

use App\Models\ConsignmentDelivery;
use App\Models\ConsignmentReturn;
use App\Models\ConsignmentSale;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Laporan transaksi konsinyasi (serah terima, laku, retur) ke .xlsx —
 * preview halaman laporan memakai buildRows() yang sama.
 */
final class ConsignmentXlsxExporter
{
    private const HEADERS = [
        'Tanggal',
        'Jenis',
        'Mitra',
        'Judul',
        'Qty',
        'Harga',
        'Subtotal',
        'Keterangan',
    ];

    /**
     * Baris seluruh transaksi konsinyasi dalam rentang tanggal
     * (opsional per mitra). `$jenis`: `semua` | `serah_terima` | `laku` | `retur`.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function buildRows(CarbonInterface $from, CarbonInterface $to, ?string $customerId, string $jenis = 'semua'): Collection
    {
        $rows = collect();

        if ($jenis === 'semua' || $jenis === 'serah_terima') {
            $deliveries = ConsignmentDelivery::query()
                ->whereBetween('delivery_date', [$from->toDateString(), $to->toDateString()])
                ->when($customerId !== null, fn ($q) => $q->where('customer_id', $customerId))
                ->with('customer:id,name', 'items.book:id,judul')
                ->get();

            foreach ($deliveries as $delivery) {
                foreach ($delivery->items as $item) {
                    $rows->push([
                        'tanggal' => $delivery->delivery_date->format('d/m/Y'),
                        'jenis' => 'Serah Terima',
                        'mitra' => $delivery->customer->name,
                        'item' => $item->book->judul,
                        'qty' => $item->qty,
                        // Nilai titipan memakai harga buku saat pelaporan.
                        'harga' => $item->book->harga,
                        'subtotal' => $item->qty * $item->book->harga,
                        'keterangan' => $delivery->notes ?? '',
                        'jenis_key' => 'serah_terima',
                    ]);
                }
            }
        }

        if ($jenis === 'semua' || $jenis === 'laku') {
            $sales = ConsignmentSale::query()
                ->whereBetween('sale_date', [$from->toDateString(), $to->toDateString()])
                ->when($customerId !== null, fn ($q) => $q->where('customer_id', $customerId))
                ->with('customer:id,name', 'items.book:id,judul')
                ->get();

            foreach ($sales as $sale) {
                foreach ($sale->items as $item) {
                    $rows->push([
                        'tanggal' => $sale->sale_date->format('d/m/Y'),
                        'jenis' => 'Laku',
                        'mitra' => $sale->customer->name,
                        'item' => $item->book->judul,
                        'qty' => $item->qty,
                        'harga' => $item->price,
                        'subtotal' => $item->qty * $item->price,
                        'keterangan' => $sale->notes ?? '',
                        'jenis_key' => 'laku',
                    ]);
                }
            }
        }

        if ($jenis === 'semua' || $jenis === 'retur') {
            $returns = ConsignmentReturn::query()
                ->whereBetween('return_date', [$from->toDateString(), $to->toDateString()])
                ->when($customerId !== null, fn ($q) => $q->where('customer_id', $customerId))
                ->with('customer:id,name', 'book:id,judul')
                ->get();

            foreach ($returns as $return) {
                $rows->push([
                    'tanggal' => $return->return_date->format('d/m/Y'),
                    'jenis' => 'Retur',
                    'mitra' => $return->customer->name,
                    'item' => $return->book->judul,
                    'qty' => $return->qty,
                    // Nilai retur memakai harga buku saat pelaporan.
                    'harga' => $return->book->harga,
                    'subtotal' => $return->qty * $return->book->harga,
                    'keterangan' => $return->notes ?? '',
                    'jenis_key' => 'retur',
                ]);
            }
        }

        return $rows->sortBy(fn (array $row): string => $row['tanggal'].' '.$row['jenis_key'])->values();
    }

    /**
     * Download laporan sebagai file .xlsx (StreamedResponse).
     */
    public function download(Collection $rows, CarbonInterface $from, CarbonInterface $to, ?string $customerId, string $jenis = 'semua'): StreamedResponse
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Laporan Konsinyasi');

        // Judul laporan
        $mitraLabel = $customerId !== null ? User::find($customerId)?->name ?? '' : 'Semua Mitra';
        $sheet->setCellValue('A1', "Laporan Konsinyasi ({$from->format('d/m/Y')} - {$to->format('d/m/Y')})");
        $sheet->setCellValue('A2', $mitraLabel);
        $sheet->getStyle('A1:A2')->getFont()->setBold(true)->setSize(12);
        $sheet->getStyle('A1')->getFont()->setSize(14);

        // Header tabel
        $headerRow = 4;
        foreach (self::HEADERS as $i => $header) {
            $column = chr(65 + $i);
            $sheet->setCellValue($column.$headerRow, $header);
        }

        $headerStyle = $sheet->getStyle("A{$headerRow}:H{$headerRow}");
        $headerStyle->getFont()->setBold(true);
        $headerStyle->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFE2E8F0');
        $headerStyle->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        // Baris data
        $rowIndex = $headerRow + 1;
        $qtyDelivered = 0;
        $valueDelivered = 0;
        $qtySold = 0;
        $valueSold = 0;
        $qtyReturned = 0;

        foreach ($rows as $row) {
            $sheet->fromArray([
                $row['tanggal'],
                $row['jenis'],
                $row['mitra'],
                $row['item'],
                $row['qty'],
                $row['harga'],
                $row['subtotal'],
                $row['keterangan'],
            ], null, "A{$rowIndex}");

            if ($row['jenis_key'] === 'serah_terima') {
                $qtyDelivered += $row['qty'];
                $valueDelivered += $row['subtotal'];
            } elseif ($row['jenis_key'] === 'laku') {
                $qtySold += $row['qty'];
                $valueSold += $row['subtotal'];
            } else {
                $qtyReturned += $row['qty'];
                $valueDelivered -= $row['subtotal'];
            }

            $rowIndex++;
        }

        // Ringkasan
        $sheet->setCellValue("G{$rowIndex}", 'Total Serah Terima');
        $sheet->setCellValue("H{$rowIndex}", $valueDelivered);
        $sheet->setCellValue('G'.($rowIndex + 1), 'Total Laku (piutang)');
        $sheet->setCellValue('H'.($rowIndex + 1), $valueSold);
        $sheet->setCellValue('G'.($rowIndex + 2), 'Total Retur (eks)');
        $sheet->setCellValue('H'.($rowIndex + 2), $qtyReturned);

        $summaryRange = "G{$rowIndex}:H".($rowIndex + 2);
        $sheet->getStyle($summaryRange)->getFont()->setBold(true);
        $sheet->getStyle($summaryRange)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFFEF3C7');

        // Format angka
        $sheet->getStyle("E{$headerRow}:H{$rowIndex}")->getNumberFormat()->setFormatCode('#,##0');

        // Lebar kolom
        foreach (range('A', 'H') as $column) {
            $sheet->getColumnDimension($column)->setWidth(
                match ($column) {
                    'A' => 12,
                    'B' => 14,
                    'C' => 24,
                    'D' => 40,
                    'E', 'F', 'G', 'H' => 14,
                    default => 20,
                },
            );
        }

        $fileName = 'laporan-konsinyasi_'.$from->format('Ymd').'_'.$to->format('Ymd').'.xlsx';

        return response()->streamDownload(function () use ($spreadsheet): void {
            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output');
        }, $fileName, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }
}

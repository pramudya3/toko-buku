<?php

namespace App\Services;

use App\Enums\SalesChannel as SalesChannelEnum;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Laporan rekap harian penjualan (.xlsx) — ringkasan per tanggal.
 */
final class DailyRecapXlsxExporter
{
    private const HEADERS = [
        'Tanggal',
        'Order',
        'Item Terjual',
        'Omzet',
        'Cash',
        'Transfer',
        'COD',
        'HPP',
        'Laba',
    ];

    /**
     * Download rekap harian sebagai .xlsx.
     *
     * @param  Collection<int, array<string, mixed>>  $rows
     */
    public function download(Collection $rows, CarbonInterface $from, CarbonInterface $to, ?string $sumberPembelian = null): StreamedResponse
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Rekap Harian');

        $title = "Rekap Harian Penjualan ({$from->format('d/m/Y')} - {$to->format('d/m/Y')})";

        if ($sumberPembelian !== null && $sumberPembelian !== '') {
            $title .= ' — Sumber: '.(SalesChannelEnum::tryFrom($sumberPembelian)?->label() ?? $sumberPembelian);
        }

        $sheet->setCellValue('A1', $title);
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);

        $headerRow = 3;
        foreach (self::HEADERS as $i => $header) {
            $column = chr(65 + $i);
            $sheet->setCellValue($column.$headerRow, $header);
        }

        $headerStyle = $sheet->getStyle("A{$headerRow}:I{$headerRow}");
        $headerStyle->getFont()->setBold(true);
        $headerStyle->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFE2E8F0');
        $headerStyle->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        $rowIndex = $headerRow + 1;
        $totals = array_fill_keys(['order_count', 'item_count', 'omzet', 'cash', 'transfer', 'cod', 'hpp', 'laba'], 0);

        foreach ($rows as $row) {
            $sheet->fromArray([
                $row['tanggal'],
                $row['order_count'],
                $row['item_count'],
                $row['omzet'],
                $row['cash'],
                $row['transfer'],
                $row['cod'],
                $row['hpp'],
                $row['laba'],
            ], null, "A{$rowIndex}");

            foreach ($totals as $key => $value) {
                $totals[$key] += $row[$key];
            }

            $rowIndex++;
        }

        // Baris total
        $sheet->setCellValue("A{$rowIndex}", 'TOTAL');
        $sheet->fromArray(array_values(array_slice($totals, 0, 8)), null, "B{$rowIndex}");

        $totalRange = "A{$rowIndex}:I{$rowIndex}";
        $sheet->getStyle($totalRange)->getFont()->setBold(true);
        $sheet->getStyle($totalRange)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFFEF3C7');

        $sheet->getStyle("D{$headerRow}:I{$rowIndex}")->getNumberFormat()->setFormatCode('#,##0');

        foreach (range('A', 'I') as $column) {
            $sheet->getColumnDimension($column)->setWidth(
                match ($column) {
                    'A' => 14,
                    'B', 'C' => 12,
                    default => 14,
                },
            );
        }

        $fileName = 'rekap-harian_'.$from->format('Ymd').'_'.$to->format('Ymd').'.xlsx';

        return response()->streamDownload(function () use ($spreadsheet): void {
            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output');
        }, $fileName, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }
}

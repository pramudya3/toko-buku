<?php

namespace App\Services;

use App\Enums\MovementType;
use App\Models\InventoryMovement;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Laporan mutasi stok (.xlsx): audit trail per gerakan barang
 * (masuk, keluar, transfer, defect, retur) dengan rincian cetakan.
 */
final class InventoryMovementXlsxExporter
{
    private const HEADERS = [
        'Tanggal',
        'Ref Code',
        'Buku',
        'Cetakan',
        'Tipe Mutasi',
        'Dari Gudang',
        'Ke Gudang',
        'Qty',
        'Petugas',
        'Keterangan',
    ];

    /**
     * Baris mutasi dalam rentang tanggal, dengan filter opsional tipe mutasi,
     * gudang (asal atau tujuan) & pencarian buku.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function buildRows(
        CarbonInterface $from,
        CarbonInterface $to,
        ?string $type = null,
        ?int $warehouseId = null,
        ?string $search = null,
    ): Collection {
        $movements = InventoryMovement::query()
            ->whereBetween('created_at', [$from->startOfDay(), $to->endOfDay()])
            ->when($type !== null && $type !== '', fn ($q) => $q->where('type', $type))
            ->when($warehouseId !== null, function ($q) use ($warehouseId): void {
                $q->where(function ($query) use ($warehouseId): void {
                    $query->where('from_warehouse_id', $warehouseId)
                        ->orWhere('to_warehouse_id', $warehouseId);
                });
            })
            ->when($search !== null && $search !== '', function ($q) use ($search): void {
                $q->whereHas('book', function ($query) use ($search): void {
                    $query->where(function ($query) use ($search): void {
                        $query->whereLike('judul', "%{$search}%")
                            ->orWhereLike('kode_sku', "%{$search}%");
                    });
                });
            })
            ->with([
                'book:id,judul,kode_sku',
                'edition:id,book_id,cetakan_ke',
                'fromWarehouse:id,kode,nama',
                'toWarehouse:id,kode,nama',
                'user:id,name',
            ])
            ->orderBy('created_at')
            ->get();

        return $movements->map(function (InventoryMovement $movement): array {
            $type = $movement->type instanceof MovementType
                ? $movement->type->label()
                : MovementType::tryFrom((string) $movement->type)?->label() ?? (string) $movement->type;

            return [
                'tanggal' => $movement->created_at->format('d/m/Y H:i'),
                'ref_code' => $movement->reference ?? '',
                'buku' => $movement->book?->judul ?? '—',
                'cetakan' => $movement->edition !== null ? 'Cetakan ke-'.$movement->edition->cetakan_ke : '',
                'tipe' => $type,
                'dari_gudang' => $movement->fromWarehouse?->nama ?? '',
                'ke_gudang' => $movement->toWarehouse?->nama ?? '',
                'qty' => $movement->qty,
                'petugas' => $movement->user?->name ?? '',
                'keterangan' => $movement->notes ?? '',
            ];
        })->values();
    }

    /**
     * Download laporan sebagai file .xlsx (StreamedResponse).
     */
    public function download(
        Collection $rows,
        CarbonInterface $from,
        CarbonInterface $to,
        ?string $type = null,
        ?int $warehouseId = null,
        ?string $search = null,
    ): StreamedResponse {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Laporan Mutasi');

        // Judul laporan
        $sheet->setCellValue('A1', "Laporan Mutasi Stok ({$from->format('d/m/Y')} - {$to->format('d/m/Y')})");
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);

        $subtitleParts = [];

        if ($type !== null && $type !== '') {
            $subtitleParts[] = 'Tipe: '.MovementType::tryFrom($type)?->label() ?? $type;
        }

        if ($search !== null && $search !== '') {
            $subtitleParts[] = 'Cari: '.$search;
        }

        $sheet->setCellValue('A2', implode(' | ', $subtitleParts));
        $sheet->getStyle('A2')->getFont()->setBold(true)->setSize(11);

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
        $totalQty = 0;

        foreach ($rows as $row) {
            $sheet->fromArray([
                $row['tanggal'],
                $row['ref_code'],
                $row['buku'],
                $row['cetakan'],
                $row['tipe'],
                $row['dari_gudang'],
                $row['ke_gudang'],
                $row['qty'],
                $row['petugas'],
                $row['keterangan'],
            ], null, "A{$rowIndex}");

            $totalQty += $row['qty'];
            $rowIndex++;
        }

        // Ringkasan
        $sheet->setCellValue("H{$rowIndex}", 'Total Qty');
        $sheet->setCellValue("I{$rowIndex}", $totalQty);
        $sheet->setCellValue('H'.($rowIndex + 1), 'Jumlah Mutasi');
        $sheet->setCellValue('I'.($rowIndex + 1), $rows->count());

        $summaryRange = "H{$rowIndex}:I".($rowIndex + 1);
        $sheet->getStyle($summaryRange)->getFont()->setBold(true);
        $sheet->getStyle($summaryRange)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFFEF3C7');

        // Format angka
        $sheet->getStyle("H{$headerRow}:J{$rowIndex}")->getNumberFormat()->setFormatCode('#,##0');

        // Lebar kolom
        foreach (range('A', 'J') as $column) {
            $sheet->getColumnDimension($column)->setWidth(
                match ($column) {
                    'A' => 18,
                    'B' => 18,
                    'C' => 36,
                    'D' => 14,
                    'E' => 14,
                    'F', 'G' => 16,
                    'H' => 10,
                    'I' => 18,
                    default => 30,
                },
            );
        }

        $fileName = 'laporan-mutasi_'.$from->format('Ymd').'_'.$to->format('Ymd').'.xlsx';

        return response()->streamDownload(function () use ($spreadsheet): void {
            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output');
        }, $fileName, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }
}

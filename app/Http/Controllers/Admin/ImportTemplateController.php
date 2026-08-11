<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Unduh template CSV untuk tiap menu import (header saja, lowercase —
 * konsisten dengan format file hasil split).
 */
final class ImportTemplateController extends Controller
{
    /**
     * Template per tipe: nama file + kolom header.
     *
     * @return array<string, array{file: string, headers: list<string>}>
     */
    private static function templates(): array
    {
        return [
            'customers' => [
                'file' => 'template-pelanggan.csv',
                'headers' => ['penerima', 'tujuan', 'kota/kabupaten', 'kecamatan', 'kelurahan'],
            ],
            'books' => [
                'file' => 'template-buku.csv',
                'headers' => ['kategori', 'kode', 'judul', 'penulis', 'harga_jual', 'harga_beli'],
            ],
            'categories' => [
                'file' => 'template-kategori.csv',
                'headers' => ['kode', 'nama'],
            ],
            'promotions' => [
                'file' => 'template-promo.csv',
                'headers' => ['promo_name', 'promo_type', 'discount_percent', 'komponen'],
            ],
            'tier-discounts' => [
                'file' => 'template-tier-discount.csv',
                'headers' => ['tier', 'min_qty', 'discount_percent'],
            ],
        ];
    }

    /**
     * Unduh template CSV (header saja).
     */
    public function download(string $type): StreamedResponse|Response
    {
        $template = self::templates()[$type] ?? null;

        if ($template === null) {
            abort(404, 'Template tidak dikenal.');
        }

        return response()->streamDownload(function () use ($template): void {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, $template['headers']);
            fclose($handle);
        }, $template['file'], [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }
}

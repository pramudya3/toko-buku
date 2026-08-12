<?php

namespace App\Support;

use RuntimeException;

/**
 * Validasi header CSV: membandingkan kolom file yang di-upload dengan
 * kolom yang diharapkan (case-insensitive). Dipakai oleh semua FormRequest
 * import untuk memberi pesan error yang jelas sebelum data diproses.
 */
final class CsvHeaderValidator
{
    /**
     * Validasi header CSV cocok persis dengan daftar kolom yang diharapkan.
     *
     * @param  string  $filePath  Real path file CSV
     * @param  list<string>  $expectedColumns  Nama kolom yang diharapkan (lowercase)
     * @return array{valid: bool, error: string|null}
     */
    public static function validate(string $filePath, array $expectedColumns): array
    {
        $headers = self::readHeaders($filePath);

        if ($headers === null) {
            return ['valid' => false, 'error' => 'File CSV kosong atau tidak valid.'];
        }

        // Normalisasi: lowercase, trim, buang kolom kosong
        $normalized = array_values(array_filter(
            array_map(fn (string $h): string => strtolower(trim($h)), $headers),
            fn (string $h): bool => $h !== '',
        ));

        $expected = array_values(array_filter(
            array_map(fn (string $h): string => strtolower(trim($h)), $expectedColumns),
            fn (string $h): bool => $h !== '',
        ));

        // Setiap kolom yang diharapkan harus ada di header file
        $missing = array_diff($expected, $normalized);

        if ($missing !== []) {
            $expectedStr = implode(', ', $expectedColumns);
            $foundStr = implode(', ', array_map(fn (string $h): string => trim($h), $headers));

            return [
                'valid' => false,
                'error' => "Kolom tidak sesuai. Diharapkan: {$expectedStr}. Ditemukan: {$foundStr}. Silakan gunakan template yang disediakan.",
            ];
        }

        return ['valid' => true, 'error' => null];
    }

    /**
     * Validasi header CSV cocok dengan salah satu dari beberapa layout
     * (untuk file seperti buku yang mendukung multi-format). Setiap layout
     * adalah daftar kolom wajib yang harus SEMUA ada.
     *
     * @param  array<string, list<string>>  $layouts  Key: nama layout, value: kolom wajib
     * @return array{valid: bool, error: string|null}
     */
    public static function validateAny(string $filePath, array $layouts): array
    {
        $headers = self::readHeaders($filePath);

        if ($headers === null) {
            return ['valid' => false, 'error' => 'File CSV kosong atau tidak valid.'];
        }

        $normalized = array_values(array_filter(
            array_map(fn (string $h): string => strtolower(trim($h)), $headers),
            fn (string $h): bool => $h !== '',
        ));

        foreach ($layouts as $expectedColumns) {
            $expected = array_values(array_filter(
                array_map(fn (string $h): string => strtolower(trim($h)), $expectedColumns),
                fn (string $h): bool => $h !== '',
            ));

            $missing = array_diff($expected, $normalized);

            if ($missing === []) {
                return ['valid' => true, 'error' => null];
            }
        }

        // Tidak ada layout yang cocok — susun pesan yang informatif.
        $foundStr = implode(', ', array_map(fn (string $h): string => trim($h), $headers));
        $layoutDescs = [];

        foreach ($layouts as $name => $cols) {
            $layoutDescs[] = "{$name} (".implode(', ', $cols).')';
        }

        $layoutsStr = implode('; atau ', $layoutDescs);

        return [
            'valid' => false,
            'error' => "Kolom tidak sesuai. Diharapkan salah satu format: {$layoutsStr}. Ditemukan: {$foundStr}. Silakan gunakan template yang disediakan.",
        ];
    }

    /**
     * Baca baris header dari file CSV. Return null jika file kosong/tidak valid.
     *
     * @return list<string>|null
     */
    private static function readHeaders(string $filePath): ?array
    {
        $handle = fopen($filePath, 'r');

        if ($handle === false) {
            throw new RuntimeException('Tidak dapat membaca file CSV.');
        }

        $headers = fgetcsv($handle, null, ',', '"', '\\');
        fclose($handle);

        if ($headers === false || $headers === null || $headers === []) {
            return null;
        }

        // Strip BOM dari sel pertama
        $headers[0] = (string) preg_replace('/^\xEF\xBB\xBF/', '', $headers[0] ?? '');

        return $headers;
    }
}

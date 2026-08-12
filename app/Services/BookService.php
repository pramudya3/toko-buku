<?php

namespace App\Services;

use App\Models\Book;

/**
 * Helper domain buku: SKU otomatis (BOOK-03, BR-08).
 */
final class BookService
{
    /**
     * Generate SKU bila kosong.
     *
     * Kategori dengan kode (abreviasi) → `{kode}{6 digit}` (PRN000008),
     * mengikuti pola kode barang pricelist. Tanpa kode kategori → `SKU-0001`.
     */
    public function ensureSku(Book $book): Book
    {
        if (! empty($book->kode_sku)) {
            return $book;
        }

        $kode = $book->category?->kode;

        if (! empty($kode)) {
            return $this->ensureCategorySku($book, $kode);
        }

        return $this->ensureLegacySku($book);
    }

    /**
     * Generate SKU berbasis abreviasi kategori: `{kode}{000001}` — nomor urut
     * per kategori (suffix 6 digit), cocokkan anchored agar kode yang merupakan
     * prefix kategori lain (mis. B vs Bdl) tidak saling mengganggu.
     */
    private function ensureCategorySku(Book $book, string $kode): Book
    {
        $pattern = '/^'.preg_quote($kode, '/').'(\d{6})$/';
        $max = 0;

        foreach (Book::query()->where('kode_sku', 'LIKE', $kode.'%')->pluck('kode_sku') as $existing) {
            if (preg_match($pattern, (string) $existing, $matches) === 1) {
                $max = max($max, (int) $matches[1]);
            }
        }

        for ($attempt = 1; $attempt <= 10; $attempt++) {
            $sku = $kode.str_pad((string) ($max + $attempt), 6, '0', STR_PAD_LEFT);

            if (! Book::query()->where('kode_sku', $sku)->exists()) {
                $book->kode_sku = $sku;
                $book->save();

                return $book;
            }
        }

        // Fallback: andalkan unique constraint & timestamp bila masih tabrakan.
        $book->kode_sku = $kode.substr((string) time(), -6);
        $book->save();

        return $book;
    }

    /**
     * Generate SKU `SKU-0001` bila kosong — urut dari SKU tertinggi yang ada.
     */
    private function ensureLegacySku(Book $book): Book
    {
        $next = 1;

        for ($attempt = 0; $attempt < 10; $attempt++) {
            $sku = 'SKU-'.str_pad((string) ($next + $attempt), 4, '0', STR_PAD_LEFT);

            if (! Book::query()->where('kode_sku', $sku)->exists()) {
                $book->kode_sku = $sku;
                $book->save();

                return $book;
            }
        }

        // Fallback: andalkan unique constraint & timestamp bila masih tabrakan.
        $book->kode_sku = 'SKU-'.substr((string) time(), -4);
        $book->save();

        return $book;
    }
}

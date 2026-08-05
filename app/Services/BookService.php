<?php

namespace App\Services;

use App\Models\Book;

/**
 * Helper domain buku: SKU otomatis (BOOK-03, BR-08).
 */
final class BookService
{
    /**
     * Generate SKU `SKU-0001` bila kosong — urut dari SKU tertinggi yang ada.
     */
    public function ensureSku(Book $book): Book
    {
        if (! empty($book->kode_sku)) {
            return $book;
        }

        $next = (int) $book->getKey();

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

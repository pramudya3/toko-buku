/**
 * Helper stok storefront — customer hanya melihat status, bukan angka.
 *
 * Ambang "menipis" berasal dari config/pricing.php `low_stock_threshold`.
 * Nilai dishare server-side via HandleInertiaRequests (lowStockThreshold).
 * Fallback 5 menjaga kompatibilitas saat prop belum tersedia (tes / SSR).
 *
 * Prefer `stock_status`/`stock_label` dari API (StorefrontController) bila ada —
 * single source of truth server-side. Helper ini hanya fallback lokal.
 * Jangan tampilkan nilai `stok` mentah di UI customer.
 */

export const LOW_STOCK_THRESHOLD = 5;

export type StockStatus = 'preorder' | 'habis' | 'menipis' | 'tersedia';

/**
 * Tentukan status stok untuk buku.
 *
 * @param stok - jumlah stok sellable (atau total buku)
 * @param isPreorder - apakah buku berstatus pre-order / new coming
 */
export function getStockStatus(
    stok: number,
    isPreorder: boolean,
    threshold: number = LOW_STOCK_THRESHOLD,
): StockStatus {
    if (isPreorder) {
        return 'preorder';
    }

    if (stok <= 0) {
        return 'habis';
    }

    if (stok <= threshold) {
        return 'menipis';
    }

    return 'tersedia';
}

/**
 * Resolver utama untuk UI: pakai `stock_status` dari server bila ada,
 * fallback ke perhitungan lokal dengan threshold eksplisit.
 *
 * Threshold diambil dari Inertia shared prop `lowStockThreshold` di komponen
 * (via usePage), fallback ke konstanta agar tidak ada magic number tersebar.
 */
export function resolveStockStatus(
    book: {
        stok: number;
        is_preorder?: boolean;
        isPreorder?: boolean;
        stock_status?: string;
    },
    threshold: number = LOW_STOCK_THRESHOLD,
): StockStatus {
    if (book.stock_status && isStockStatus(book.stock_status)) {
        return book.stock_status;
    }

    const isPreorder = Boolean(book.is_preorder ?? book.isPreorder);

    return getStockStatus(book.stok, isPreorder, threshold);
}

export function isStockStatus(value: string): value is StockStatus {
    return (
        value === 'preorder' ||
        value === 'habis' ||
        value === 'menipis' ||
        value === 'tersedia'
    );
}

export function stockLabel(status: StockStatus): string {
    switch (status) {
        case 'preorder':
            return 'Pre-Order';
        case 'habis':
            return 'Stok habis';
        case 'menipis':
            return 'Stok menipis';
        case 'tersedia':
            return 'Stok tersedia';
    }
}

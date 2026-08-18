/**
 * Bentuk ringkas buku untuk slot iklan / kartu promosi storefront proto-d.
 */
export type BookPromo = {
    id: string;
    judul: string;
    penulis: string | null;
    harga: number;
    cover_url: string | null;
    price_breakdown?: {
        original_price: number;
        final_price: number;
    } | null;
};

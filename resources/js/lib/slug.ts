/**
 * Bersihkan teks jadi bentuk aman URL (huruf kecil, strip, tanpa diakritik).
 * Server memakai Str::slug() — hasilnya harus konsisten (id + judul di URL publik).
 */
export function slugify(text: string): string {
    return text
        .toLowerCase()
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')
        .replace(/[^a-z0-9]+/g, '-')
        .replace(/^-+|-+$/g, '');
}

/**
 * URL publik buku: {uuid}-{judul-bersih}. Lookup tetap pakai uuid (36 char
 * pertama), judul hanya hiasan URL.
 */
export function bookShowUrl(book: { id: string; judul: string }): string {
    return `${book.id}-${slugify(book.judul)}`;
}

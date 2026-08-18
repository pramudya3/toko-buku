/**
 * Konfigurasi iklan storefront proto-d.
 *
 * Template memakai placeholder iklan (tanpa jaringan iklan nyata) agar
 * layout dan slot sudah siap dipasangi ad network nanti. Semua slot bisa
 * dimatikan lewat `enabled` tanpa menyentuh komponen.
 */
export const adConfig = {
    /** Nyalakan semua slot iklan sekaligus. */
    enabled: true,
    articleDetail: {
        /** Iklan di bawah konten artikel (setelah badan artikel). */
        bottom: true,
        /** Iklan samping (rail) — 'left', 'right', atau 'none'. */
        side: 'right' as 'left' | 'right' | 'none',
    },
};

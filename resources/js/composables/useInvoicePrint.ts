// Print invoice — buka dialog print otomatis setelah halaman nota dirender
// (tunggu font/gambar/logo selesai dimuat).
import { onMounted } from 'vue';

export function useInvoicePrint(): void {
    onMounted(() => {
        requestAnimationFrame(() => {
            setTimeout(() => window.print(), 250);
        });
    });
}

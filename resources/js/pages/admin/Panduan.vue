<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import {
    ArrowDownToLine,
    Banknote,
    BookOpen,
    Boxes,
    ChevronDown,
    FileUp,
    LayoutGrid,
    Lightbulb,
    ScrollText,
    Settings,
    ShoppingCart,
} from '@lucide/vue';
import { ref } from 'vue';
import type { LucideIcon } from '@lucide/vue';

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Beranda', href: '/admin/dashboard' },
            { title: 'Panduan', href: '/admin/panduan' },
        ],
    },
});

type Section = {
    id: string;
    icon: LucideIcon;
    title: string;
    content: string[];
    tips: string[];
};

const sections: Section[] = [
    {
        id: 'dashboard',
        icon: LayoutGrid,
        title: 'Dashboard',
        content: [
            'Dashboard menampilkan ringkasan data toko: total pesanan, pelanggan, pendapatan, dan grafik tren.',
            'Gunakan dashboard untuk memantau performa toko secara cepat tanpa harus membuka menu satu per satu.',
        ],
        tips: [
            'Klik kartu ringkasan untuk langsung menuju halaman detail terkait.',
        ],
    },
    {
        id: 'katalog',
        icon: BookOpen,
        title: 'Katalog — Buku, Kategori, Promosi & Tier Discount',
        content: [
            '<strong>Buku</strong>: Kelola semua data buku (judul, harga, stok, cover, ISBN, dll). Gunakan tombol <em>Tambah Buku</em> untuk input manual, atau <em>Import CSV</em> untuk massal.',
            '<strong>Kategori</strong>: Buat dan atur kategori buku (fiksi, non-fiksi, religi, dll). Setiap buku wajib punya satu kategori.',
            '<strong>Promosi</strong>: Buat diskon per buku, per kategori, atau global. Tentukan tipe promosi (diskon % atau nominal), tanggal mulai & selesai.',
            '<strong>Tier Discount</strong>: Diskon bertingkat berdasarkan jumlah pembelian (misal: beli 5 diskon 5%, beli 10 diskon 10%).',
        ],
        tips: [
            'Download template CSV sebelum import agar format data sesuai.',
            'Promo yang sudah lewat tanggal otomatis tidak berlaku di katalog.',
            'Tier Discount dihitung paling akhir — setelah diskon promo & bundle.',
        ],
    },
    {
        id: 'penjualan',
        icon: ShoppingCart,
        title: 'Penjualan — Pesanan, Dropship, Piutang, Laporan, Retur & Rekap',
        content: [
            '<strong>Pesanan</strong>: Daftar semua pesanan dari customer & admin. Filter per status, dropship, dan sumber pembelian. Klik nomor order untuk detail & update status.',
            '<strong>Buat Pesanan (admin)</strong>: Admin bisa membuat pesanan manual — pilih customer, tambah buku, hitung ongkir, tentukan sumber pembelian (toko, Shopee, dll), submit.',
            '<strong>Dropship</strong>: Pesanan dengan nama pengirim berbeda dari nama pembeli. Cek checkbox "Dropship" di form pesanan.',
            '<strong>Piutang</strong>: Catat pembayaran cicilan dari pelanggan yang belum lunas.',
            '<strong>Laporan Penjualan</strong>: Export .xlsx dengan filter periode, status, metode bayar, dan sumber penjualan.',
            '<strong>Retur Penjualan</strong>: Proses barang yang dikembalikan pembeli — stok akan otomatis bertambah.',
            '<strong>Rekap Harian</strong>: Ringkasan omzet, cash/transfer/COD, HPP & laba per tanggal. Bisa difilter per periode dan sumber penjualan, plus export .xlsx.',
        ],
        tips: [
            'Konfirmasi pembayaran sebelum memproses pesanan — cek bukti transfer WA.',
            'Gunakan filter status "menunggu_konfirmasi" untuk melihat pesanan baru.',
        ],
    },
    {
        id: 'pembelian',
        icon: ArrowDownToLine,
        title: 'Pembelian — Supplier, Barang Masuk, Retur & Hutang',
        content: [
            '<strong>Supplier</strong>: Data supplier/penerbit. Setiap barang masuk harus terkait dengan satu supplier.',
            '<strong>Barang Masuk</strong>: Input pembelian dari supplier — tambah buku, qty, harga beli. Stok inventori otomatis bertambah.',
            '<strong>Retur Supplier</strong>: Catat barang yang dikembalikan ke supplier beserta alasan. Stok otomatis berkurang.',
            '<strong>Hutang Supplier</strong>: Catat pembayaran ke supplier jika pembelian dilakukan secara kredit.',
            '<strong>Laporan Supplier</strong>: Export .xlsx rekap barang masuk & retur per supplier.',
        ],
        tips: [
            'Pastikan supplier sudah terdaftar sebelum input barang masuk.',
            'Harga beli di barang masuk mempengaruhi laporan laba/rugi.',
        ],
    },
    {
        id: 'kas',
        icon: Banknote,
        title: 'Kas — Pencatatan & Laporan',
        content: [
            '<strong>Pencatatan Kas</strong>: Catat transaksi harian (debit/kredit) per bulan. Tambah bulan baru dengan klik tombol di halaman daftar bulan.',
            '<strong>Detail Kas</strong>: Dua tabel dalam satu halaman — <em>Kas Masuk</em> (debit) dan <em>Kas Keluar</em> (kredit). Gunakan dialog untuk entri baru.',
            '<strong>Laporan Kas</strong>: Ringkasan per bulan dengan saldo awal, total debit, total kredit, dan saldo akhir.',
            '<strong>Laba/Rugi</strong>: Laporan laba/rugi berdasarkan data pesanan & pembelian.',
        ],
        tips: [
            'Format bulan: YYYY-MM (contoh: 2025-08).',
            'Saldo akhir bulan lalu otomatis menjadi saldo awal bulan berikutnya.',
        ],
    },
    {
        id: 'stok',
        icon: Boxes,
        title: 'Stok — Inventori, Gudang, Mutasi & Adjustment',
        content: [
            '<strong>Inventori</strong>: Lihat stok terkini per buku per gudang. Gunakan filter gudang & pencarian judul.',
            '<strong>Gudang</strong>: Kelola lokasi gudang (nama, tipe: sellable/non-sellable, alamat).',
            '<strong>Laporan Mutasi</strong>: Export .xlsx riwayat semua mutasi stok (in, out, transfer, adjustment). Filter per periode, tipe, buku, gudang.',
            '<strong>Stok Adjustment</strong>: Koreksi stok fisik — input selisih antara stok sistem dan stok riil. Gunakan jenis <em>adjustment</em> untuk opname.',
        ],
        tips: [
            'Gudang tipe "sellable" digunakan untuk menghitung stok yang bisa dijual di katalog.',
            'Mutasi stok otomatis tercatat setiap ada pesanan, pembelian, retur, atau adjustment.',
        ],
    },
    {
        id: 'pengaturan',
        icon: Settings,
        title: 'Pengaturan — Lembaga, Rekening, Ekspedisi, Pembayaran, Sumber Penjualan & Staf',
        content: [
            '<strong>Lembaga</strong>: Identitas toko — nama, logo, tagline, visi, misi, kontak, alamat. Data ini tampil di halaman Tentang Kami (publik).',
            '<strong>Rekening Bank</strong>: Daftar rekening untuk pembayaran transfer. Tampil di halaman checkout & invoice.',
            '<strong>Ekspedisi</strong>: Kurir yang tersedia untuk pengiriman (terintegrasi Biteship API).',
            '<strong>Metode Pembayaran</strong>: Pilihan bayar yang muncul di checkout (transfer, COD, dll).',
            '<strong>Sumber Penjualan</strong>: Channel tempat pembelian terjadi — toko, Shopee, Tokopedia, TikTok Shop, dll. Channel aktif muncul di form pesanan manual dan dipakai sebagai filter laporan.',
            '<strong>Staf</strong>: Manajemen user admin — tambah, edit, nonaktifkan, reset password. Hanya bisa diakses oleh admin.',
        ],
        tips: [
            'Nonaktifkan ekspedisi lewat soft-delete, bukan hapus permanen — data historis pesanan tetap utuh.',
            'API Key Biteship disetting di halaman API Key (menu Pengaturan).',
            'Sumber penjualan dipakai sebagai filter di laporan penjualan & rekap harian — kelola di Pengaturan → Sumber Penjualan.',
        ],
    },
    {
        id: 'import',
        icon: FileUp,
        title: 'Import CSV',
        content: [
            'Fitur import CSV tersedia di: Buku, Kategori, Pelanggan, Promosi, dan Tier Discount.',
            '<strong>Langkah</strong>: (1) Download template dari tombol <em>Download Template</em>, (2) Isi data sesuai kolom template, (3) Klik <em>Import CSV</em> lalu pilih file.',
            'Setiap halaman import punya template berbeda — pastikan download template yang sesuai.',
            'Header CSV divalidasi otomatis — jika kolom tidak cocok, import akan ditolak dengan pesan error yang jelas.',
        ],
        tips: [
            'Jangan ubah nama kolom header di template.',
            'Import buku otomatis membuat cover placeholder jika tidak ada URL cover.',
            'Gunakan fitur restore (soft delete) jika tidak sengaja menghapus data.',
        ],
    },
    {
        id: 'aktivitas',
        icon: ScrollText,
        title: 'Log Aktivitas',
        content: [
            'Mencatat semua perubahan penting: create, update, delete, restore pada data utama (buku, pesanan, stok, supplier, dll).',
            'Setiap entri mencatat: siapa (user), apa (model + ID), kapan (timestamp), dan deskripsi perubahan.',
            'Gunakan log ini untuk audit — lacak siapa yang mengubah harga, stok, atau status pesanan.',
        ],
        tips: [
            'Log tidak bisa dihapus — ini fitur keamanan.',
            'Filter log berdasarkan model atau user untuk pencarian cepat.',
        ],
    },
];

const openSections = ref<Set<string>>(new Set(['dashboard']));

function toggleSection(id: string): void {
    if (openSections.value.has(id)) {
        openSections.value.delete(id);
    } else {
        openSections.value.add(id);
    }
}

function isOpen(id: string): boolean {
    return openSections.value.has(id);
}
</script>

<template>
    <Head title="Panduan" />

    <div class="flex flex-col gap-4 p-4 md:p-6">
        <div>
            <h1 class="text-xl font-semibold tracking-tight">Panduan</h1>
            <p class="text-sm text-muted-foreground">
                Pelajari cara menggunakan semua fitur di panel admin.
            </p>
        </div>

        <div class="grid gap-3">
            <div
                v-for="section in sections"
                :key="section.id"
                class="rounded-xl border"
            >
                <button
                    type="button"
                    class="flex w-full items-center gap-3 px-5 py-4 text-left transition-colors hover:bg-muted/50"
                    @click="toggleSection(section.id)"
                >
                    <component
                        :is="section.icon"
                        class="size-5 shrink-0 text-muted-foreground"
                    />
                    <span class="flex-1 text-sm font-semibold">
                        {{ section.title }}
                    </span>
                    <ChevronDown
                        class="size-4 shrink-0 text-muted-foreground transition-transform duration-200"
                        :class="isOpen(section.id) && 'rotate-180'"
                    />
                </button>
                <div v-if="isOpen(section.id)" class="border-t px-5 py-4">
                    <ul
                        class="flex flex-col gap-2 text-sm text-muted-foreground"
                    >
                        <li
                            v-for="(line, i) in section.content"
                            :key="i"
                            class="leading-relaxed"
                            v-html="line"
                        />
                    </ul>
                    <div
                        v-if="section.tips.length"
                        class="mt-4 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 dark:border-amber-800 dark:bg-amber-950"
                    >
                        <p
                            class="flex items-center gap-1.5 text-xs font-semibold text-amber-800 dark:text-amber-200"
                        >
                            <Lightbulb class="size-3.5" />
                            Tips
                        </p>
                        <ul class="mt-1 flex flex-col gap-1">
                            <li
                                v-for="(tip, i) in section.tips"
                                :key="i"
                                class="text-xs text-amber-700 dark:text-amber-300"
                            >
                                {{ tip }}
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

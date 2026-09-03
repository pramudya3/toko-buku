<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import {
    ArrowDownToLine,
    Banknote,
    BookOpen,
    Boxes,
    ChevronDown,
    FileUp,
    KeyRound,
    LayoutGrid,
    Lightbulb,
    LogIn,
    ScrollText,
    Search,
    Settings,
    ShoppingCart,
} from '@lucide/vue';
import type { LucideIcon } from '@lucide/vue';
import { computed, ref } from 'vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';

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
            '<strong>Buku</strong>: Kelola semua data buku (judul, harga, stok, cover, ISBN, dll). Gunakan tombol <em>Tambah Buku</em> untuk input manual, atau <em>Import CSV</em> untuk massal. Setiap buku bisa punya banyak <em>Cetakan</em> dengan <strong>Harga Guru</strong> berbeda per cetakan (tipe % atau nominal).',
            '<strong>Kategori</strong>: Buat dan atur kategori buku (fiksi, non-fiksi, religi, dll). Setiap buku wajib punya satu kategori.',
            '<strong>Promosi</strong>: Buat diskon per buku, per kategori, atau global. Tentukan tipe promosi (diskon % atau nominal), tanggal mulai & selesai.',
            '<strong>Tier Discount</strong>: Diskon bertingkat berdasarkan jumlah pembelian (misal: beli 5 diskon 5%, beli 10 diskon 10%). Tier <em>Guru</em> otomatis pakai Harga Guru per cetakan, bukan diskon % global.',
        ],
        tips: [
            'Download template CSV sebelum import agar format data sesuai.',
            'Promo yang sudah lewat tanggal otomatis tidak berlaku di katalog.',
            'Tier Discount dihitung paling akhir — setelah diskon promo & bundle. Untuk Guru, harga dari master Cetakan.',
        ],
    },
    {
        id: 'penjualan',
        icon: ShoppingCart,
        title: 'Penjualan — Pesanan, Dropship, Piutang, Laporan, Retur & Rekap',
        content: [
            '<strong>Pesanan</strong>: Daftar semua pesanan dari customer & admin. Filter per status, dropship, dan sumber pembelian. Klik nomor order untuk detail & update status.',
            '<strong>Buat Pesanan (admin)</strong>: Admin bisa membuat pesanan manual — pilih customer, tambah buku, hitung ongkir, tentukan sumber pembelian (toko, Shopee, dll), pilih cetakan & harga (Normal / Promo Expired / Custom Insidentil), submit.',
            '<strong>Dropship</strong>: Pesanan dengan nama pengirim berbeda dari nama pembeli. Cek checkbox "Dropship" di form pesanan.',
            '<strong>Piutang</strong>: Catat pembayaran cicilan dari pelanggan yang belum lunas.',
            '<strong>Laporan Penjualan</strong>: Export .xlsx dengan filter periode, status, metode bayar, dan sumber penjualan. Omzet tanpa ongkir.',
            '<strong>Retur Penjualan</strong>: Proses barang yang dikembalikan pembeli — stok akan otomatis bertambah.',
            '<strong>Laporan Harian</strong>: Ringkasan omzet (tanpa ongkir), cash/transfer/COD, HPP & laba per tanggal. Bisa difilter per periode dan sumber penjualan, plus export .xlsx.',
        ],
        tips: [
            'Konfirmasi pembayaran sebelum memproses pesanan — cek bukti transfer WA.',
            'Gunakan filter status "menunggu_konfirmasi" untuk melihat pesanan baru.',
            'Harga type <em>Custom</em> & <em>Promo Expired</em> butuh keterangan wajib — dipakai untuk audit insidentil.',
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
        id: 'akun',
        icon: LogIn,
        title: 'Akun & Keamanan — Login, Google OAuth & Reset Password',
        content: [
            '<strong>Login</strong>: Masuk dengan email & password. Tersedia <em>Lanjutkan dengan Google</em> (1-klik) — akun Google akan otomatis ter-link jika email sama.',
            '<strong>Lupa Password</strong>: Klik <em>Forgot your password?</em> di halaman login → masukkan email → link reset dikirim via Brevo (<code>admin@miniapps.id</code>). Link berlaku 60 menit.',
            '<strong>Buat Password Baru</strong>: Buka link dari email → isi password baru & konfirmasi → otomatis redirect ke login.',
            '<strong>Akun Google</strong>: User Google tanpa password bisa tetap login via Google. Jika butuh login manual, buat password lewat <em>Lupa Password</em> atau di <em>Profil → Keamanan</em>.',
            '<strong>Keamanan</strong>: Ganti password di <em>Pengaturan → Keamanan</em> (butuh password lama, kecuali akun Google yang belum punya password).',
        ],
        tips: [
            'Pastikan <code>admin@miniapps.id</code> sudah verified di Brevo — jika tidak, email reset tidak akan terkirim.',
            'Jika <em>redirect_uri_mismatch</em> saat login Google, tambahkan <code>https://penerbitpcp.com/auth/google/callback</code> di Google Console.',
            'Akun nonaktif (<code>is_active=false</code>) tidak bisa login via email maupun Google.',
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
        id: 'notifikasi',
        icon: KeyRound,
        title: 'Notifikasi & Email (Brevo)',
        content: [
            '<strong>Brevo API</strong>: Semua email transaksi (reset password, notifikasi) dikirim via Brevo API (<code>admin@miniapps.id</code>).',
            '<strong>Status Pengiriman</strong>: Cek log di <code>storage/logs/laravel.log</code> jika email tidak masuk — biasanya sender belum verified atau API key salah.',
            '<strong>Template</strong>: Email reset menggunakan template default Laravel + brand Pustaka Cahaya Peradaban.',
        ],
        tips: [
            'Verifikasi domain <code>miniapps.id</code> di Brevo (SPF/DKIM) agar tidak masuk spam.',
            'Jangan commit <code>BREVO_API_KEY</code> ke git — isi di <code>.env</code> server saja.',
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

const searchQuery = ref('');
const openSections = ref<Set<string>>(new Set(['dashboard']));

const filteredSections = computed(() => {
    const q = searchQuery.value.trim().toLowerCase();

    if (!q) {
        return sections;
    }

    return sections.filter((s) => {
        const hay = [s.title, ...s.content, ...s.tips].join(' ').toLowerCase();

        return hay.includes(q);
    });
});

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

function expandAll(): void {
    filteredSections.value.forEach((s) => openSections.value.add(s.id));
}

function collapseAll(): void {
    openSections.value.clear();
}

function scrollToSection(id: string): void {
    if (!openSections.value.has(id)) {
        openSections.value.add(id);
    }

    requestAnimationFrame(() => {
        document
            .getElementById(`panduan-${id}`)
            ?.scrollIntoView({ behavior: 'smooth', block: 'start' });
    });
}
</script>

<template>
    <Head title="Panduan" />

    <div class="mx-auto flex max-w-6xl flex-col gap-6 p-4 md:p-6">
        <!-- Header -->
        <div class="space-y-1">
            <h1 class="text-2xl font-bold tracking-tight">
                Panduan Penggunaan
            </h1>
            <p class="max-w-2xl text-sm leading-relaxed text-muted-foreground">
                Panduan ringkas untuk admin mengelola toko — dari katalog &
                penjualan hingga kas, stok, dan pengaturan. Klik daftar isi
                untuk lompat ke bagian, atau gunakan pencarian.
            </p>
        </div>

        <!-- Toolbar: search + expand -->
        <Card class="shadow-sm">
            <CardContent
                class="flex flex-col gap-3 p-4 sm:flex-row sm:items-center sm:justify-between"
            >
                <div class="relative flex-1 sm:max-w-sm">
                    <Search
                        class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
                    />
                    <Input
                        v-model="searchQuery"
                        placeholder="Cari panduan (mis: Google, Brevo, retur, ongkir)..."
                        class="pl-9"
                    />
                </div>
                <div class="flex items-center gap-2">
                    <Button variant="outline" size="sm" @click="expandAll"
                        >Buka semua</Button
                    >
                    <Button variant="ghost" size="sm" @click="collapseAll"
                        >Tutup semua</Button
                    >
                    <span
                        class="hidden text-xs text-muted-foreground sm:inline"
                    >
                        {{ filteredSections.length }} topik
                    </span>
                </div>
            </CardContent>
        </Card>

        <div class="grid gap-6 lg:grid-cols-[260px_1fr]">
            <!-- Daftar Isi (sticky di desktop) -->
            <div class="order-2 lg:order-1">
                <div class="sticky top-6 space-y-3">
                    <Card class="shadow-sm">
                        <CardContent class="p-4">
                            <h2
                                class="mb-3 text-xs font-semibold tracking-widest text-muted-foreground uppercase"
                            >
                                Daftar Isi
                            </h2>
                            <nav class="flex flex-col gap-1">
                                <button
                                    v-for="section in filteredSections"
                                    :key="section.id"
                                    type="button"
                                    class="flex items-center gap-2.5 rounded-md px-2.5 py-2 text-left text-sm transition-colors hover:bg-muted"
                                    :class="
                                        isOpen(section.id)
                                            ? 'bg-muted font-medium'
                                            : 'text-muted-foreground'
                                    "
                                    @click="scrollToSection(section.id)"
                                >
                                    <component
                                        :is="section.icon"
                                        class="size-4 shrink-0"
                                    />
                                    <span class="line-clamp-1 flex-1">{{
                                        section.title.split(' — ')[0]
                                    }}</span>
                                </button>
                                <p
                                    v-if="filteredSections.length === 0"
                                    class="px-2 py-2 text-xs text-muted-foreground"
                                >
                                    Tidak ada topik yang cocok.
                                </p>
                            </nav>
                        </CardContent>
                    </Card>
                    <div
                        class="hidden rounded-lg border border-dashed bg-muted/30 px-4 py-3 text-xs leading-relaxed text-muted-foreground lg:block"
                    >
                        <span class="font-medium text-foreground"
                            >Tips membaca:</span
                        >
                        Mulai dari <strong>Dashboard</strong> untuk overview,
                        lalu
                        <strong>Katalog → Penjualan → Stok → Pengaturan</strong
                        >. Bagian <strong>Akun & Keamanan</strong> wajib dibaca
                        untuk login Google & reset password.
                    </div>
                </div>
            </div>

            <!-- Konten -->
            <div class="order-1 flex flex-col gap-3 lg:order-2">
                <div
                    v-for="(section, idx) in filteredSections"
                    :id="`panduan-${section.id}`"
                    :key="section.id"
                    class="scroll-mt-24 rounded-xl border bg-card shadow-sm transition-shadow hover:shadow"
                >
                    <button
                        type="button"
                        class="flex w-full items-start gap-4 px-5 py-4 text-left"
                        @click="toggleSection(section.id)"
                    >
                        <span
                            class="flex size-9 shrink-0 items-center justify-center rounded-full border bg-muted"
                        >
                            <component
                                :is="section.icon"
                                class="size-4.5 text-muted-foreground"
                            />
                        </span>
                        <span class="flex flex-1 flex-col gap-1 pt-0.5">
                            <span class="flex items-center gap-2">
                                <span
                                    class="text-sm leading-tight font-semibold"
                                    >{{ idx + 1 }}. {{ section.title }}</span
                                >
                                <ChevronDown
                                    class="size-4 shrink-0 text-muted-foreground transition-transform duration-200"
                                    :class="isOpen(section.id) && 'rotate-180'"
                                />
                            </span>
                            <span
                                v-if="!isOpen(section.id)"
                                class="line-clamp-2 text-xs leading-relaxed text-muted-foreground"
                                v-html="section.content[0]"
                            />
                        </span>
                    </button>

                    <div v-if="isOpen(section.id)" class="border-t">
                        <div class="space-y-4 px-5 py-5">
                            <ul class="space-y-3">
                                <li
                                    v-for="(line, i) in section.content"
                                    :key="i"
                                    class="flex gap-3 text-[14px] leading-7 text-muted-foreground"
                                >
                                    <span
                                        class="mt-2.5 size-1.5 shrink-0 rounded-full bg-primary/70"
                                    />
                                    <span
                                        class="[&>code]:rounded [&>code]:bg-muted [&>code]:px-1.5 [&>code]:py-0.5 [&>code]:text-xs [&>em]:text-foreground [&>strong]:font-semibold [&>strong]:text-foreground"
                                        v-html="line"
                                    />
                                </li>
                            </ul>

                            <div
                                v-if="section.tips.length"
                                class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 dark:border-amber-900/50 dark:bg-amber-950/40"
                            >
                                <p
                                    class="flex items-center gap-1.5 text-xs font-bold tracking-widest text-amber-800 uppercase dark:text-amber-200"
                                >
                                    <Lightbulb class="size-3.5" />
                                    Tips
                                </p>
                                <ul class="mt-2 flex flex-col gap-1.5">
                                    <li
                                        v-for="(tip, i) in section.tips"
                                        :key="i"
                                        class="flex gap-2 text-xs leading-5 text-amber-800 dark:text-amber-100/90"
                                    >
                                        <span
                                            class="mt-1 size-1 shrink-0 rounded-full bg-amber-500"
                                        />
                                        <span
                                            class="[&>code]:rounded [&>code]:bg-amber-100 dark:[&>code]:bg-amber-900 [&>em]:font-medium"
                                            v-html="tip"
                                        />
                                    </li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>

                <div
                    v-if="filteredSections.length === 0"
                    class="rounded-xl border border-dashed bg-muted/30 px-6 py-12 text-center"
                >
                    <p class="text-sm font-medium">
                        Tidak ada hasil untuk "{{ searchQuery }}"
                    </p>
                    <p class="mt-1 text-xs text-muted-foreground">
                        Coba kata kunci lain seperti “ongkir”, “Google”, atau
                        “Brevo”.
                    </p>
                    <Button
                        variant="outline"
                        size="sm"
                        class="mt-4"
                        @click="searchQuery = ''"
                        >Hapus pencarian</Button
                    >
                </div>
            </div>
        </div>
    </div>
</template>

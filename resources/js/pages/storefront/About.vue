<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import CustomerLayout from '@/layouts/customer/CustomerLayout.vue';

defineOptions({
    layout: CustomerLayout,
});
</script>

<template>
    <Head title="Tentang Kami">
        <meta
            name="description"
            content="Tentang Toko Buku Online — cerita, visi, misi, dan kontak toko buku kami."
        />
    </Head>

    <div class="mx-auto flex max-w-3xl flex-col gap-8">
        <div class="text-center">
            <h1 class="text-3xl font-bold tracking-tight">Tentang Kami</h1>
            <p class="mt-2 text-muted-foreground">
                Toko buku yang berfokus menyediakan bacaan berkualitas untuk
                semua kalangan.
            </p>
        </div>

        <div class="flex flex-col gap-4">
            <section class="rounded-xl border p-6">
                <h2 class="font-semibold">Cerita Kami</h2>
                <p class="mt-2 text-sm leading-relaxed text-muted-foreground">
                    Kami memulai sebagai toko buku kecil yang ingin
                    mempermudah masyarakat mendapatkan buku — dari fiksi,
                    non-fiksi, buku pendidikan, hingga buku religi. Dengan
                    layanan yang ramah dan harga yang bersahabat, kami
                    melayani pelanggan dari berbagai kalangan, termasuk
                    reseller, guru, dan lembaga pendidikan.
                </p>
            </section>

            <section class="rounded-xl border p-6">
                <h2 class="font-semibold">Visi</h2>
                <p class="mt-2 text-sm leading-relaxed text-muted-foreground">
                    Menjadi toko buku pilihan yang menumbuhkan budaya membaca
                    di Indonesia.
                </p>
            </section>

            <section class="rounded-xl border p-6">
                <h2 class="font-semibold">Misi</h2>
                <ul class="mt-2 list-disc space-y-1 pl-5 text-sm text-muted-foreground">
                    <li>Menyediakan koleksi buku berkualitas dengan harga terjangkau.</li>
                    <li>Melayani pembelian grosir untuk reseller dan lembaga.</li>
                    <li>Memberikan layanan cepat dan ramah bagi setiap pelanggan.</li>
                </ul>
            </section>

            <section class="rounded-xl border p-6">
                <h2 class="font-semibold">Kontak</h2>
                <div class="mt-2 grid gap-1 text-sm text-muted-foreground">
                    <p>WhatsApp: <span class="font-medium text-foreground">08xxxxxxxxxx</span></p>
                    <p>Email: <span class="font-medium text-foreground">halo@tokobuku.test</span></p>
                    <p>Alamat toko: <span class="font-medium text-foreground">Jl. Contoh No. 1, Malang, Jawa Timur</span></p>
                </div>
            </section>
        </div>
    </div>
</template>

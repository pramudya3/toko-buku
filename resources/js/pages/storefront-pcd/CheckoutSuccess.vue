<script setup lang="ts">
/**
 * Halaman sukses checkout proto-d — /pcd/checkout/sukses.
 * Data nyata: order yang baru dibuat + rekening bank aktif.
 */
import { Head, Link } from '@inertiajs/vue3';
import { CheckCircle2, Landmark } from '@lucide/vue';
import { computed } from 'vue';
import Money from '@/components/Money.vue';
import FlatSection from '@/components/storefront/FlatSection.vue';
import StorefrontPcdLayout from '@/layouts/customer/StorefrontPcdLayout.vue';
import { home as homeRoute } from '@/routes/pcd';
import { index as myOrdersRoute } from '@/routes/pcd/my-orders';

type OrderItem = {
    id: string;
    judul_snapshot: string;
    edition_snapshot: string | null;
    qty: number;
    price_final: number;
};

type Order = {
    id: string;
    no_order: string;
    nama_pembeli: string;
    total: number;
    metode_bayar: string | null;
    metode_pengambilan: string | null;
    ekspedisi: string | null;
    payment_status: string;
    created_at: string;
    items: OrderItem[];
};

type BankAccount = {
    id: string;
    bank_name: string;
    account_number: string;
    account_holder: string;
};

const props = defineProps<{
    order: Order;
    bankAccounts: BankAccount[];
}>();

defineOptions({ layout: StorefrontPcdLayout });

const items = computed(() => props.order.items);

const needsTransfer = computed(() => props.order.metode_bayar === 'transfer');
const isPickup = computed(() => props.order.metode_pengambilan === 'ambil');
</script>

<template>
    <Head title="Pesanan Diterima — Pustaka Cahaya Peradaban" />

    <FlatSection variant="secondary" decoration>
        <div class="mx-auto max-w-2xl py-6 text-center md:py-10">
            <CheckCircle2
                class="mx-auto size-14 text-white"
                aria-hidden="true"
            />
            <p
                class="mt-6 text-xs font-semibold tracking-[0.16em] text-white/80 uppercase"
            >
                Pesanan Diterima
            </p>
            <h1 class="mt-3 text-3xl font-extrabold tracking-tight text-white md:text-4xl">
                Terima kasih!
            </h1>
            <p class="mt-4 text-sm leading-relaxed text-white/80">
                Pesanan
                <span class="font-mono text-sm font-bold text-white">{{
                    order.no_order
                }}</span>
                sudah kami terima dan akan dikonfirmasi melalui WhatsApp dalam
                1×24 jam.
            </p>
        </div>
    </FlatSection>

    <div class="mx-auto max-w-2xl px-4 pt-12 pb-24 md:pb-32">
        <!-- Ringkasan -->
        <div class="rounded-lg bg-flat-muted p-6">
            <h2 class="text-base font-bold">Ringkasan Pesanan</h2>
            <ul class="mt-5 space-y-3 text-sm">
                <li
                    v-for="item in items"
                    :key="item.id"
                    class="flex items-baseline justify-between gap-4"
                >
                    <span class="min-w-0 truncate">
                        {{ item.judul_snapshot }}
                        <span v-if="item.edition_snapshot" class="text-gray-500"
                            >· {{ item.edition_snapshot }}</span
                        >
                        <span class="text-gray-500"> × {{ item.qty }}</span>
                    </span>
                    <span class="shrink-0 font-medium tabular-nums"
                        ><Money :value="item.price_final * item.qty"
                    /></span>
                </li>
            </ul>
            <div
                class="mt-5 flex items-center justify-between border-t-2 border-flat-border pt-4"
            >
                <span class="font-bold">Total</span>
                <span class="text-lg font-extrabold tabular-nums text-flat-primary"
                    ><Money :value="order.total"
                /></span>
            </div>
        </div>

        <!-- Instruksi pembayaran -->
        <div v-if="needsTransfer" class="mt-6 rounded-lg bg-flat-muted p-6">
            <h2 class="flex items-center gap-2 text-base font-bold">
                <Landmark class="size-4 text-flat-primary" aria-hidden="true" />
                Transfer ke Rekening
            </h2>
            <p class="mt-3 text-sm leading-relaxed text-gray-500">
                Transfer sesuai total ke salah satu rekening di bawah, lalu
                kirim bukti via WhatsApp.
            </p>
            <ul class="mt-4 space-y-3">
                <li
                    v-for="bank in bankAccounts"
                    :key="bank.id"
                    class="flex flex-wrap items-center justify-between gap-2 rounded-lg bg-white px-4 py-3 text-sm"
                >
                    <span class="font-bold">{{ bank.bank_name }}</span>
                    <span class="font-mono text-xs tracking-wide">{{
                        bank.account_number
                    }}</span>
                    <span class="text-gray-500"
                        >a.n. {{ bank.account_holder }}</span
                    >
                </li>
            </ul>
        </div>

        <div v-else class="mt-6 rounded-lg bg-flat-muted p-6">
            <h2 class="text-base font-bold">Pembayaran Tunai</h2>
            <p class="mt-3 text-sm leading-relaxed text-gray-500">
                {{
                    isPickup
                        ? 'Bayar tunai saat mengambil buku di toko.'
                        : 'Bayar tunai saat buku tiba di alamat Anda.'
                }}
            </p>
        </div>

        <div class="mt-10 flex flex-col items-center gap-3 sm:flex-row sm:justify-center">
            <Link
                :href="myOrdersRoute().url"
                class="inline-flex min-h-12 items-center justify-center rounded-md bg-flat-primary px-8 text-sm font-semibold text-white transition-all duration-200 hover:scale-105 hover:bg-flat-primary-dark focus-visible:ring-2 focus-visible:ring-flat-primary focus-visible:ring-offset-2 focus-visible:outline-none"
                >Lihat Pesanan Saya</Link
            >
            <Link
                :href="homeRoute().url"
                class="inline-flex min-h-12 items-center justify-center rounded-md border-2 border-flat-border bg-white px-8 text-sm font-medium transition-all duration-200 hover:bg-flat-muted focus-visible:ring-2 focus-visible:ring-flat-primary focus-visible:outline-none"
                >Kembali ke Beranda</Link
            >
        </div>
    </div>
</template>

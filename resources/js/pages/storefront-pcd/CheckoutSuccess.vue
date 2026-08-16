<script setup lang="ts">
/**
 * Halaman sukses checkout proto-d — /pcd/checkout/sukses.
 * Data nyata: order yang baru dibuat + rekening bank aktif.
 */
import { Head, Link } from '@inertiajs/vue3';
import { CheckCircle2, Landmark } from '@lucide/vue';
import { computed } from 'vue';
import Money from '@/components/Money.vue';
import StorefrontPcdLayout from '@/layouts/customer/StorefrontPcdLayout.vue';
import { home as homeRoute } from '@/routes/pcd';

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

    <div class="mx-auto max-w-2xl px-4 pt-20 pb-24 md:pt-28 md:pb-32">
        <div class="text-center">
            <CheckCircle2
                class="mx-auto size-12 text-pcd-accent"
                aria-hidden="true"
            />
            <p
                class="mt-6 text-xs font-semibold tracking-[0.16em] text-pcd-accent uppercase"
            >
                Pesanan Diterima
            </p>
            <h1
                class="mt-3 font-serif text-3xl font-semibold tracking-tight md:text-4xl"
            >
                Terima kasih!
            </h1>
            <p class="mt-4 text-sm leading-relaxed text-pcd-muted">
                Pesanan
                <span class="font-mono text-xs font-semibold text-pcd-ink">{{
                    order.no_order
                }}</span>
                sudah kami terima dan akan dikonfirmasi melalui WhatsApp dalam
                1×24 jam.
            </p>
        </div>

        <!-- Ringkasan -->
        <div
            class="mt-12 rounded-xl border border-pcd-hairline bg-pcd-surface p-6"
        >
            <h2 class="text-base font-semibold">Ringkasan Pesanan</h2>
            <ul class="mt-5 space-y-3 text-sm">
                <li
                    v-for="item in items"
                    :key="item.id"
                    class="flex items-baseline justify-between gap-4"
                >
                    <span class="min-w-0 truncate">
                        {{ item.judul_snapshot }}
                        <span
                            v-if="item.edition_snapshot"
                            class="text-pcd-muted"
                            >· {{ item.edition_snapshot }}</span
                        >
                        <span class="text-pcd-muted"> × {{ item.qty }}</span>
                    </span>
                    <span class="shrink-0 font-medium tabular-nums"
                        ><Money :value="item.price_final * item.qty"
                    /></span>
                </li>
            </ul>
            <div
                class="mt-5 flex items-center justify-between border-t border-pcd-hairline pt-4"
            >
                <span class="font-semibold">Total</span>
                <span class="text-lg font-semibold tabular-nums"
                    ><Money :value="order.total"
                /></span>
            </div>
        </div>

        <!-- Instruksi pembayaran -->
        <div
            v-if="needsTransfer"
            class="mt-6 rounded-xl border border-pcd-hairline bg-pcd-surface p-6"
        >
            <h2 class="flex items-center gap-2 text-base font-semibold">
                <Landmark class="size-4 text-pcd-accent" aria-hidden="true" />
                Transfer ke Rekening
            </h2>
            <p class="mt-3 text-sm leading-relaxed text-pcd-muted">
                Transfer sesuai total ke salah satu rekening di bawah, lalu
                kirim bukti via WhatsApp.
            </p>
            <ul class="mt-4 space-y-3">
                <li
                    v-for="bank in bankAccounts"
                    :key="bank.id"
                    class="flex items-center justify-between rounded-lg border border-pcd-hairline px-4 py-3 text-sm"
                >
                    <span class="font-medium">{{ bank.bank_name }}</span>
                    <span class="font-mono text-xs tracking-wide">{{
                        bank.account_number
                    }}</span>
                    <span class="text-pcd-muted"
                        >a.n. {{ bank.account_holder }}</span
                    >
                </li>
            </ul>
        </div>

        <div
            v-else
            class="mt-6 rounded-xl border border-pcd-hairline bg-pcd-surface p-6"
        >
            <h2 class="text-base font-semibold">Pembayaran Tunai</h2>
            <p class="mt-3 text-sm leading-relaxed text-pcd-muted">
                {{
                    isPickup
                        ? 'Bayar tunai saat mengambil buku di toko.'
                        : 'Bayar tunai saat buku tiba di alamat Anda.'
                }}
            </p>
        </div>

        <div class="mt-10 text-center">
            <Link
                :href="homeRoute().url"
                class="inline-flex min-h-12 items-center justify-center rounded-lg border border-pcd-hairline bg-pcd-surface px-8 text-sm font-medium transition-colors hover:border-pcd-ink focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-pcd-accent-strong"
                >Kembali ke Beranda</Link
            >
        </div>
    </div>
</template>

<script setup lang="ts">
/**
 * Pesanan Saya proto-d — /pcd/pesanan-saya. Desain Flat.
 */
import { Head, Link, router } from '@inertiajs/vue3';
import { Printer } from '@lucide/vue';
import EmptyState from '@/components/EmptyState.vue';
import Money from '@/components/Money.vue';
import Pagination from '@/components/Pagination.vue';
import StatusBadge from '@/components/StatusBadge.vue';
import StorefrontPcdLayout from '@/layouts/customer/StorefrontPcdLayout.vue';
import {
    invoice as invoiceRoute,
    show as showRoute,
} from '@/routes/pcd/my-orders';

type Order = {
    id: string;
    no_order: string;
    total: number;
    status: string;
    payment_status: string;
    metode_pengambilan: string | null;
    awb: string | null;
    biteship_courier_link: string | null;
    created_at: string;
    items_count: number;
    preorder_items_count: number;
};

defineProps<{
    orders: {
        data: Order[];
        current_page: number;
        last_page: number;
        total: number;
        per_page: number;
        links: Array<{ url: string | null; label: string; active: boolean }>;
    };
    statusOptions: Record<string, string>;
}>();

defineOptions({
    layout: StorefrontPcdLayout,
});

function openDetail(orderId: string): void {
    router.get(showRoute(orderId).url);
}

function statusVariant(
    status: string,
): 'success' | 'warning' | 'danger' | 'info' {
    if (status === 'menunggu_konfirmasi') {
        return 'warning';
    }

    if (status === 'selesai') {
        return 'success';
    }

    if (status === 'batal') {
        return 'danger';
    }

    return 'info';
}

function paymentVariant(paymentStatus: string): 'success' | 'warning' {
    return paymentStatus === 'lunas' ? 'success' : 'warning';
}

function formatDate(createdAt: string): string {
    return new Date(createdAt).toLocaleDateString('id-ID', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
        timeZone: 'Asia/Jakarta',
    });
}
</script>

<template>
    <Head title="Pesanan Saya" />

    <div class="mx-auto max-w-5xl px-4 pt-14 pb-24 md:px-6 md:pt-20 md:pb-32">
        <p
            class="text-xs font-semibold tracking-[0.16em] text-flat-primary uppercase"
        >
            Akun
        </p>
        <h1 class="mt-3 text-3xl font-extrabold tracking-tight md:text-4xl">
            Pesanan Saya
        </h1>
        <p class="mt-3 text-sm text-gray-500">
            Riwayat pesanan yang pernah Anda buat.
        </p>

        <div v-if="orders.data.length" class="mt-10 flex flex-col gap-4">
            <div
                v-for="order in orders.data"
                :key="order.id"
                class="cursor-pointer rounded-lg border-2 border-flat-border bg-white p-5 transition-all duration-200 hover:scale-[1.01] hover:bg-flat-muted"
                @click="openDetail(order.id)"
            >
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div class="min-w-0">
                        <p class="font-mono text-sm font-bold">
                            {{ order.no_order }}
                        </p>
                        <p class="mt-0.5 text-xs text-gray-500">
                            {{ order.items_count }} item ·
                            {{ formatDate(order.created_at) }}
                        </p>
                        <p
                            v-if="order.preorder_items_count > 0"
                            class="mt-1 inline-flex items-center gap-1 rounded-md bg-sky-100 px-1.5 py-0.5 text-[10px] font-bold text-sky-800"
                        >
                            Pre-Order — menunggu stok
                        </p>
                    </div>
                    <div class="flex shrink-0 flex-wrap items-center gap-1.5">
                        <StatusBadge
                            :variant="statusVariant(order.status)"
                            :label="statusOptions[order.status] ?? order.status"
                        />
                        <StatusBadge
                            v-if="order.metode_pengambilan === 'ambil'"
                            variant="neutral"
                            label="Ambil Sendiri"
                        />
                    </div>
                </div>

                <div
                    class="mt-3 flex flex-wrap items-end justify-between gap-3 border-t-2 border-flat-border pt-3"
                >
                    <div class="flex flex-col gap-1">
                        <StatusBadge
                            :variant="paymentVariant(order.payment_status)"
                            :label="
                                order.payment_status === 'lunas'
                                    ? 'Lunas'
                                    : 'Menunggu Pembayaran'
                            "
                        />
                        <a
                            v-if="order.awb && order.biteship_courier_link"
                            :href="order.biteship_courier_link"
                            target="_blank"
                            rel="noopener"
                            class="font-mono text-xs font-medium underline decoration-dotted underline-offset-2 transition-colors hover:text-flat-primary"
                            :title="`Lacak ${order.awb}`"
                            @click.stop
                        >
                            AWB: {{ order.awb }}
                        </a>
                        <span
                            v-else-if="order.awb"
                            class="font-mono text-xs font-medium text-gray-500"
                        >
                            AWB: {{ order.awb }}
                        </span>
                    </div>
                    <div class="text-right">
                        <p class="text-xs text-gray-500">Total</p>
                        <Money
                            :value="order.total"
                            class="font-bold text-flat-primary"
                        />
                    </div>
                </div>

                <div
                    class="mt-3 flex items-center justify-end gap-2 border-t-2 border-flat-border pt-3"
                    @click.stop
                >
                    <button
                        type="button"
                        class="inline-flex min-h-10 items-center rounded-md border-2 border-flat-border bg-white px-4 text-sm font-semibold transition-all duration-200 hover:bg-flat-muted focus-visible:ring-2 focus-visible:ring-flat-primary focus-visible:outline-none"
                        @click="openDetail(order.id)"
                    >
                        Detail
                    </button>
                    <a
                        :href="invoiceRoute(order.id).url"
                        target="_blank"
                        rel="noopener"
                        class="inline-flex size-10 items-center justify-center rounded-md border-2 border-flat-border bg-white text-gray-500 transition-all duration-200 hover:bg-flat-muted hover:text-flat-ink"
                        title="Cetak Invoice"
                    >
                        <Printer class="size-3.5" aria-hidden="true" />
                    </a>
                </div>
            </div>

            <div class="overflow-hidden rounded-lg border-2 border-flat-border">
                <Pagination :paginator="orders" />
            </div>
        </div>

        <EmptyState
            v-else
            icon="/img/empty-orders.png"
            title="Belum ada pesanan"
            description="Yuk belanja buku pertama Anda!"
        >
            <Link
                :href="'/pcd/buku'"
                class="mt-2 inline-flex min-h-11 items-center rounded-md bg-flat-primary px-5 text-sm font-semibold text-white transition-all duration-200 hover:scale-105 hover:bg-flat-primary-dark"
            >
                Lihat Katalog
            </Link>
        </EmptyState>
    </div>
</template>

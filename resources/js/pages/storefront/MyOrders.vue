<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { Clock, Printer } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import EmptyState from '@/components/EmptyState.vue';
import Money from '@/components/Money.vue';
import Pagination from '@/components/Pagination.vue';
import StatusBadge from '@/components/StatusBadge.vue';
import { Button } from '@/components/ui/button';
import EditorialLayout from '@/layouts/customer/EditorialLayout.vue';
import {
    index as indexRoute,
    invoice as invoiceRoute,
    show as showRoute,
} from '@/routes/my-orders';

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

const props = defineProps<{
    orders: {
        data: Order[];
        current_page: number;
        last_page: number;
        total: number;
        per_page: number;
        links: Array<{ url: string | null; label: string; active: boolean }>;
    };
    statusOptions: Record<string, string>;
    filters: { status?: string; belum_dibayar?: string };
}>();

defineOptions({
    layout: EditorialLayout,
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

// ── Filter status transaksi + quick-filter "Belum Dibayar" ──
// Sinkron ke query string (preserveState) agar back/forward tetap konsisten.
const activeStatus = ref(props.filters.status ?? '');
const onlyUnpaid = ref(props.filters.belum_dibayar === '1');

watch([activeStatus, onlyUnpaid], applyFilters);

function applyFilters(): void {
    router.get(
        indexRoute().url,
        {
            status: activeStatus.value || undefined,
            belum_dibayar: onlyUnpaid.value ? '1' : undefined,
        },
        { preserveState: true, replace: true },
    );
}

// Ada filter aktif → empty state menawarkan reset, bukan CTA belanja.
const hasActiveFilter = computed(
    () => activeStatus.value !== '' || onlyUnpaid.value,
);
</script>

<template>
    <Head title="Pesanan Saya" />

    <div class="mx-auto max-w-6xl px-4 py-8 md:px-6 md:py-12">
        <div class="flex flex-col gap-6">
            <div>
                <h1
                    class="font-serif text-2xl font-bold tracking-tight text-article-ink md:text-3xl"
                >
                    Pesanan Saya
                </h1>
                <p class="mt-1 text-sm text-article-muted">
                    Riwayat pesanan yang pernah Anda buat.
                </p>
            </div>

            <!-- Filter: status transaksi + quick-filter belum dibayar -->
            <div class="flex flex-wrap items-center gap-2">
                <div
                    class="flex [scrollbar-width:none] items-center gap-1 overflow-x-auto rounded-lg border border-article-border bg-article-surface p-1 [&::-webkit-scrollbar]:hidden"
                >
                    <button
                        v-for="option in [
                            { value: '', label: 'Semua' },
                            ...Object.entries(statusOptions).map(
                                ([value, label]) => ({ value, label }),
                            ),
                        ]"
                        :key="option.value || 'all'"
                        type="button"
                        class="shrink-0 rounded-md px-3 py-1.5 text-sm font-medium transition-colors"
                        :class="
                            activeStatus === option.value
                                ? 'bg-article-primary text-white'
                                : 'text-article-muted hover:text-article-ink'
                        "
                        @click="activeStatus = option.value"
                    >
                        {{ option.label }}
                    </button>
                </div>
                <button
                    type="button"
                    class="flex shrink-0 items-center gap-1.5 rounded-full border px-3 py-1.5 text-sm font-medium transition-colors"
                    :class="
                        onlyUnpaid
                            ? 'border-amber-300 bg-amber-50 text-amber-700'
                            : 'border-article-border bg-article-surface text-article-muted hover:text-article-ink'
                    "
                    :aria-pressed="onlyUnpaid"
                    @click="onlyUnpaid = !onlyUnpaid"
                >
                    <Clock class="size-3.5" />
                    Belum Dibayar
                </button>
            </div>

            <EmptyState
                v-if="hasActiveFilter && orders.data.length === 0"
                icon="/img/empty-orders.png"
                title="Tidak ada pesanan"
                description="Tidak ada pesanan dengan filter ini — coba ubah filter."
            >
                <Button
                    size="sm"
                    variant="outline"
                    @click="((activeStatus = ''), (onlyUnpaid = false))"
                >
                    Reset Filter
                </Button>
            </EmptyState>

            <div v-else-if="orders.data.length" class="flex flex-col gap-3">
                <div
                    v-for="order in orders.data"
                    :key="order.id"
                    class="cursor-pointer rounded-xl border p-4 transition-colors hover:bg-muted/40"
                    @click="openDetail(order.id)"
                >
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <p class="font-mono text-sm font-semibold">
                                {{ order.no_order }}
                            </p>
                            <p class="mt-0.5 text-xs text-muted-foreground">
                                {{ order.items_count }} item ·
                                {{ formatDate(order.created_at) }}
                            </p>
                            <p
                                v-if="order.preorder_items_count > 0"
                                class="mt-1 inline-flex items-center gap-1 rounded-full bg-sky-100 px-1.5 py-0.5 text-[10px] font-semibold text-sky-800"
                            >
                                Pre-Order — menunggu stok
                            </p>
                        </div>
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

                    <div
                        class="mt-3 flex items-end justify-between gap-3 border-t pt-3"
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
                                class="font-mono text-xs font-medium underline decoration-dotted underline-offset-2 transition-colors hover:text-primary"
                                :title="`Lacak ${order.awb}`"
                                @click.stop
                            >
                                AWB: {{ order.awb }}
                            </a>
                            <span
                                v-else-if="order.awb"
                                class="font-mono text-xs font-medium text-muted-foreground"
                            >
                                AWB: {{ order.awb }}
                            </span>
                        </div>
                        <div class="text-right">
                            <p class="text-xs text-muted-foreground">Total</p>
                            <Money :value="order.total" class="font-semibold" />
                        </div>
                    </div>

                    <div
                        class="mt-3 flex items-center justify-end gap-2 border-t pt-3"
                        @click.stop
                    >
                        <Button
                            variant="outline"
                            size="sm"
                            @click="openDetail(order.id)"
                        >
                            Detail
                        </Button>
                        <Button
                            variant="ghost"
                            size="sm"
                            class="size-8 p-0"
                            title="Cetak Invoice"
                            as-child
                        >
                            <a
                                :href="invoiceRoute(order.id).url"
                                target="_blank"
                                rel="noopener"
                            >
                                <Printer class="size-3.5" />
                            </a>
                        </Button>
                    </div>
                </div>

                <div class="overflow-hidden rounded-xl border">
                    <Pagination :paginator="orders" />
                </div>
            </div>

            <EmptyState
                v-else
                icon="/img/empty-orders.png"
                title="Belum ada pesanan"
                description="Yuk belanja buku pertama Anda!"
            >
                <Button size="sm" as-child>
                    <Link :href="'/buku'">Lihat Katalog</Link>
                </Button>
            </EmptyState>
        </div>
    </div>
</template>

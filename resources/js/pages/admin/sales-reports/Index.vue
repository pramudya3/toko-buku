<script setup lang="ts">
defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Beranda', href: '/admin/dashboard' },
            { title: 'Laporan Penjualan', href: '/admin/sales-reports' },
        ],
    },
});

import { Head, router } from '@inertiajs/vue3';
import { Download, X } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import DataTable from '@/components/DataTable.vue';
import type { DataTableColumn } from '@/components/DataTable.vue';
import Money from '@/components/Money.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import {
    index as indexRoute,
    exportMethod,
} from '@/routes/admin/sales-reports';

type OrderItem = {
    buku: string;
    cetakan: string;
    qty: number;
    harga_asli: number;
    diskon: number;
    harga_final: number;
    total: number;
    hpp: number;
    laba: number;
};

type OrderGroup = {
    id: string;
    no_order: string;
    tanggal: string;
    sort_date: string;
    pembeli: string;
    sumber: string;
    metode_bayar: string;
    status: string;
    shipping_cost: number;
    voucher_discount: number;
    qty_total: number;
    total: number;
    hpp_total: number;
    laba_total: number;
    items: OrderItem[];
    is_retur: boolean;
};

type Props = {
    orders: {
        data: OrderGroup[];
        current_page: number;
        last_page: number;
        total: number;
        per_page: number;
        links: Array<{ url: string | null; label: string; active: boolean }>;
    };
    rows: {
        data: unknown[];
        current_page: number;
        last_page: number;
        total: number;
        per_page: number;
        links: Array<{ url: string | null; label: string; active: boolean }>;
    };
    summary: {
        order_count: number;
        omzet: number;
        hpp: number;
        laba: number;
        shipping: number;
        voucher_discount: number;
        item_count: number;
    };
    filters: {
        from: string;
        to: string;
        metode_bayar: string | null;
        status: string | null;
        sumber_pembelian: string | null;
    };
    paymentOptions: Record<string, string>;
    statusOptions: Record<string, string>;
    salesChannels: Record<string, string>;
};

const props = defineProps<Props>();

const expandedIds = ref<Set<string | number>>(new Set());

const columns: DataTableColumn[] = [
    { key: 'expand', header: '', expandable: true, cellClass: 'w-10' },
    {
        key: 'tanggal',
        header: 'Tanggal',
        cellClass: 'whitespace-nowrap text-sm',
    },
    { key: 'no_order', header: 'No. Order', cellClass: 'font-mono text-sm' },
    {
        key: 'pembeli',
        header: 'Pembeli',
        cellClass: 'max-w-40 truncate text-sm',
    },
    { key: 'sumber', header: 'Sumber', cellClass: 'text-sm' },
    { key: 'metode_bayar', header: 'Bayar', cellClass: 'text-sm' },
    { key: 'status', header: 'Status', cellClass: 'text-sm' },
    {
        key: 'qty_total',
        header: 'Qty',
        cellClass: 'text-right tabular-nums text-sm',
    },
    {
        key: 'total',
        header: 'Total',
        cellClass: 'text-right font-medium tabular-nums text-sm',
    },
    {
        key: 'shipping_cost',
        header: 'Ongkir',
        cellClass: 'text-right tabular-nums text-sm',
    },
    {
        key: 'laba_total',
        header: 'Laba',
        cellClass: 'text-right tabular-nums text-sm',
    },
];

const from = ref(props.filters.from);
const to = ref(props.filters.to);
const metodeBayar = ref(props.filters.metode_bayar ?? 'all');
const status = ref(props.filters.status ?? 'all');
const sumberPembelian = ref(props.filters.sumber_pembelian ?? 'all');

const initialFrom = props.filters.from;
const initialTo = props.filters.to;
const initialMetodeBayar = props.filters.metode_bayar ?? 'all';
const initialStatus = props.filters.status ?? 'all';
const initialSumberPembelian = props.filters.sumber_pembelian ?? 'all';

const hasActiveFilters = computed(
    () =>
        from.value !== initialFrom ||
        to.value !== initialTo ||
        metodeBayar.value !== initialMetodeBayar ||
        status.value !== initialStatus ||
        sumberPembelian.value !== initialSumberPembelian,
);

let filterTimer: ReturnType<typeof setTimeout> | undefined;
function applyFilters() {
    clearTimeout(filterTimer);
    filterTimer = setTimeout(() => {
        router.get(
            indexRoute().url,
            {
                from: from.value || undefined,
                to: to.value || undefined,
                metode_bayar:
                    metodeBayar.value === 'all' ? undefined : metodeBayar.value,
                status: status.value === 'all' ? undefined : status.value,
                sumber_pembelian:
                    sumberPembelian.value === 'all'
                        ? undefined
                        : sumberPembelian.value,
                per_page:
                    new URLSearchParams(window.location.search).get(
                        'per_page',
                    ) || undefined,
            },
            { preserveState: true, replace: true },
        );
    }, 350);
}
function resetFilters() {
    from.value = initialFrom;
    to.value = initialTo;
    metodeBayar.value = initialMetodeBayar;
    status.value = initialStatus;
    sumberPembelian.value = initialSumberPembelian;
    applyFilters();
}
watch([from, to, metodeBayar, status, sumberPembelian], applyFilters);

function exportUrl() {
    const params = new URLSearchParams({ from: from.value, to: to.value });

    if (metodeBayar.value && metodeBayar.value !== 'all') {
        params.set('metode_bayar', metodeBayar.value);
    }

    if (status.value && status.value !== 'all') {
        params.set('status', status.value);
    }

    if (sumberPembelian.value && sumberPembelian.value !== 'all') {
        params.set('sumber_pembelian', sumberPembelian.value);
    }

    return `${exportMethod().url}?${params.toString()}`;
}
</script>

<template>
    <Head title="Laporan Penjualan" />

    <div class="mx-auto flex w-full max-w-7xl flex-col gap-3 p-3 md:p-4">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div>
                <h1 class="text-xl font-semibold tracking-tight">
                    Laporan Penjualan
                </h1>
                <p class="text-sm text-muted-foreground">
                    Rekap per faktur — expand untuk detail buku ({{
                        summary.order_count
                    }}
                    faktur, {{ summary.item_count }} item)
                </p>
            </div>
            <Button variant="outline" as-child>
                <a :href="exportUrl()" target="_blank" rel="noopener">
                    <Download class="size-4" /> Export .xlsx
                </a>
            </Button>
        </div>

        <!-- Summary strip -->
        <div class="grid grid-cols-2 gap-2 md:grid-cols-5">
            <div class="rounded-md border bg-card p-3">
                <p class="text-xs text-muted-foreground">Omzet</p>
                <p class="text-sm font-semibold tabular-nums">
                    <Money :value="summary.omzet" />
                </p>
            </div>
            <div class="rounded-md border bg-card p-3">
                <p class="text-xs text-muted-foreground">HPP</p>
                <p class="text-sm font-medium tabular-nums">
                    <Money :value="summary.hpp" />
                </p>
            </div>
            <div class="rounded-md border bg-card p-3">
                <p class="text-xs text-muted-foreground">Laba</p>
                <p
                    class="text-sm font-semibold tabular-nums"
                    :class="
                        summary.laba >= 0
                            ? 'text-green-600'
                            : 'text-destructive'
                    "
                >
                    <Money :value="summary.laba" />
                </p>
            </div>
            <div class="rounded-md border bg-card p-3">
                <p class="text-xs text-muted-foreground">Ongkir</p>
                <p class="text-sm tabular-nums">
                    <Money :value="summary.shipping" />
                </p>
            </div>
            <div class="rounded-md border bg-card p-3">
                <p class="text-xs text-muted-foreground">Voucher</p>
                <p class="text-sm text-destructive tabular-nums">
                    <Money :value="summary.voucher_discount" />
                </p>
            </div>
        </div>

        <!-- Filter -->
        <div
            class="flex w-full flex-col divide-y divide-border overflow-hidden rounded-md border bg-card md:w-fit md:flex-row md:items-stretch md:divide-x md:divide-y-0"
        >
            <div class="md:flex md:items-center">
                <p
                    class="px-3 pt-2 text-xs font-medium text-muted-foreground md:hidden"
                >
                    Dari
                </p>
                <Input
                    v-model="from"
                    type="date"
                    class="h-11 w-full rounded-none border-0 bg-transparent px-3 shadow-none focus-visible:border-transparent focus-visible:ring-0 md:h-9 md:w-36"
                    aria-label="Dari tanggal"
                />
            </div>
            <div class="md:flex md:items-center">
                <p
                    class="px-3 pt-2 text-xs font-medium text-muted-foreground md:hidden"
                >
                    Sampai
                </p>
                <Input
                    v-model="to"
                    type="date"
                    class="h-11 w-full rounded-none border-0 bg-transparent px-3 shadow-none focus-visible:border-transparent focus-visible:ring-0 md:h-9 md:w-36"
                    aria-label="Sampai tanggal"
                />
            </div>
            <div class="md:flex md:items-center">
                <p
                    class="px-3 pt-2 text-xs font-medium text-muted-foreground md:hidden"
                >
                    Metode Bayar
                </p>
                <Select v-model="metodeBayar" name="metode_bayar">
                    <SelectTrigger
                        class="h-11 w-full rounded-none border-0 bg-transparent px-3 shadow-none focus-visible:border-transparent focus-visible:ring-0 md:h-9 md:w-40"
                    >
                        <SelectValue placeholder="Semua metode" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value="all">Semua metode</SelectItem>
                        <SelectItem
                            v-for="(label, value) in paymentOptions"
                            :key="value"
                            :value="value"
                            >{{ label }}</SelectItem
                        >
                    </SelectContent>
                </Select>
            </div>
            <div class="md:flex md:items-center">
                <p
                    class="px-3 pt-2 text-xs font-medium text-muted-foreground md:hidden"
                >
                    Status
                </p>
                <Select v-model="status" name="status">
                    <SelectTrigger
                        class="h-11 w-full rounded-none border-0 bg-transparent px-3 shadow-none focus-visible:border-transparent focus-visible:ring-0 md:h-9 md:w-40"
                    >
                        <SelectValue placeholder="Semua status" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value="all">Semua status</SelectItem>
                        <SelectItem
                            v-for="(label, value) in statusOptions"
                            :key="value"
                            :value="value"
                            >{{ label }}</SelectItem
                        >
                    </SelectContent>
                </Select>
            </div>
            <div class="md:flex md:items-center">
                <p
                    class="px-3 pt-2 text-xs font-medium text-muted-foreground md:hidden"
                >
                    Sumber
                </p>
                <Select v-model="sumberPembelian" name="sumber_pembelian">
                    <SelectTrigger
                        class="h-11 w-full rounded-none border-0 bg-transparent px-3 shadow-none focus-visible:border-transparent focus-visible:ring-0 md:h-9 md:w-40"
                    >
                        <SelectValue placeholder="Semua sumber" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value="all">Semua sumber</SelectItem>
                        <SelectItem
                            v-for="(label, value) in salesChannels"
                            :key="value"
                            :value="value"
                            >{{ label }}</SelectItem
                        >
                    </SelectContent>
                </Select>
            </div>
            <button
                v-if="hasActiveFilters"
                type="button"
                class="flex h-11 w-full items-center justify-center gap-2 text-sm text-muted-foreground transition-colors hover:bg-accent hover:text-destructive md:h-9 md:w-9"
                title="Hapus filter"
                @click="resetFilters"
            >
                <X class="size-4" /><span class="md:hidden">Hapus filter</span>
            </button>
        </div>

        <!-- Tabel per faktur (expand untuk detail item) -->
        <DataTable
            :data="orders.data"
            :columns="columns"
            :paginator="orders"
            key-field="id"
            expandable
            :expanded-ids="expandedIds"
            empty-title="Gunakan filter tanggal"
            empty-description="Pilih rentang tanggal untuk menampilkan data laporan"
            @update:expanded-ids="expandedIds = $event"
        >
            <template #cell-status="{ row }">
                <Badge
                    :variant="row.is_retur ? 'destructive' : 'secondary'"
                    class="text-xs"
                    >{{ row.status }}</Badge
                >
            </template>
            <template #cell-total="{ row }">
                <Money :value="row.total" />
            </template>
            <template #cell-shipping_cost="{ row }">
                <Money :value="row.shipping_cost" />
            </template>
            <template #cell-laba_total="{ row }">
                <span
                    :class="
                        row.laba_total >= 0
                            ? 'text-green-600'
                            : 'text-destructive'
                    "
                    ><Money :value="row.laba_total"
                /></span>
            </template>

            <template #expanded-row="{ row }">
                <div class="p-3">
                    <div
                        v-if="row.voucher_discount"
                        class="mb-2 text-xs text-muted-foreground"
                    >
                        Voucher: -<Money :value="row.voucher_discount" />
                    </div>
                    <div class="overflow-hidden rounded-md border">
                        <table class="w-full text-sm">
                            <thead
                                class="bg-muted/50 text-xs text-muted-foreground"
                            >
                                <tr>
                                    <th class="px-3 py-2 text-left font-medium">
                                        Buku
                                    </th>
                                    <th class="px-3 py-2 text-left font-medium">
                                        Cetakan
                                    </th>
                                    <th class="px-3 py-2 text-left font-medium">
                                        Qty
                                    </th>
                                    <th
                                        class="px-3 py-2 text-right font-medium"
                                    >
                                        Harga Asli
                                    </th>
                                    <th
                                        class="px-3 py-2 text-right font-medium"
                                    >
                                        Diskon
                                    </th>
                                    <th
                                        class="px-3 py-2 text-right font-medium"
                                    >
                                        Final
                                    </th>
                                    <th
                                        class="px-3 py-2 text-right font-medium"
                                    >
                                        Total
                                    </th>
                                    <th
                                        class="px-3 py-2 text-right font-medium"
                                    >
                                        HPP
                                    </th>
                                    <th
                                        class="px-3 py-2 text-right font-medium"
                                    >
                                        Laba
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr
                                    v-for="(it, idx) in row.items"
                                    :key="idx"
                                    class="border-t"
                                >
                                    <td class="max-w-56 truncate px-3 py-2">
                                        {{ it.buku }}
                                    </td>
                                    <td class="px-3 py-2">
                                        {{ it.cetakan || '—' }}
                                    </td>
                                    <td
                                        class="px-3 py-2 text-left tabular-nums"
                                        :class="
                                            it.qty < 0 ? 'text-destructive' : ''
                                        "
                                    >
                                        {{ it.qty }}
                                    </td>
                                    <td
                                        class="px-3 py-2 text-right tabular-nums"
                                    >
                                        <Money :value="it.harga_asli" />
                                    </td>
                                    <td
                                        class="px-3 py-2 text-right text-destructive tabular-nums"
                                    >
                                        <Money :value="it.diskon" />
                                    </td>
                                    <td
                                        class="px-3 py-2 text-right tabular-nums"
                                    >
                                        <Money :value="it.harga_final" />
                                    </td>
                                    <td
                                        class="px-3 py-2 text-right font-medium tabular-nums"
                                    >
                                        <Money :value="it.total" />
                                    </td>
                                    <td
                                        class="px-3 py-2 text-right text-muted-foreground tabular-nums"
                                    >
                                        <Money :value="it.hpp" />
                                    </td>
                                    <td
                                        class="px-3 py-2 text-right tabular-nums"
                                        :class="
                                            it.laba >= 0
                                                ? 'text-green-600'
                                                : 'text-destructive'
                                        "
                                    >
                                        <Money :value="it.laba" />
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </template>
        </DataTable>
    </div>
</template>

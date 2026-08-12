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

type Row = {
    tanggal: string;
    no_order: string;
    pembeli: string;
    sumber: string;
    metode_bayar: string;
    status: string;
    buku: string;
    cetakan: string;
    qty: number;
    harga_asli: number;
    diskon: number;
    harga_final: number;
    hpp: number;
    laba: number;
};

type Props = {
    rows: {
        data: Row[];
        current_page: number;
        last_page: number;
        total: number;
        per_page: number;
        links: Array<{ url: string | null; label: string; active: boolean }>;
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

const columns: DataTableColumn[] = [
    {
        key: 'tanggal',
        header: 'Tanggal',
        cellClass: 'font-medium whitespace-nowrap',
    },
    { key: 'no_order', header: 'No. Order', cellClass: 'font-mono text-xs' },
    { key: 'pembeli', header: 'Pembeli' },
    { key: 'sumber', header: 'Sumber' },
    { key: 'metode_bayar', header: 'Metode Bayar' },
    { key: 'status', header: 'Status' },
    { key: 'buku', header: 'Buku', cellClass: 'max-w-56 truncate' },
    { key: 'cetakan', header: 'Cetakan' },
    { key: 'qty', header: 'Qty', cellClass: 'text-right tabular-nums' },
    {
        key: 'harga_asli',
        header: 'Harga Asli',
        cellClass: 'text-right tabular-nums',
    },
    {
        key: 'diskon',
        header: 'Diskon',
        cellClass: 'text-right tabular-nums text-destructive',
    },
    {
        key: 'harga_final',
        header: 'Harga Final',
        cellClass: 'text-right font-medium tabular-nums',
    },
    {
        key: 'hpp',
        header: 'HPP',
        cellClass: 'text-right tabular-nums text-muted-foreground',
    },
    {
        key: 'laba',
        header: 'Laba',
        cellClass: 'text-right font-medium tabular-nums',
    },
];

const from = ref(props.filters.from);
const to = ref(props.filters.to);
const metodeBayar = ref(props.filters.metode_bayar ?? 'all');
const status = ref(props.filters.status ?? 'all');
const sumberPembelian = ref(props.filters.sumber_pembelian ?? 'all');

// Snapshot awal (nilai server saat load) untuk tombol Reset — laporan punya
// default periode dari server (bulan berjalan), jadi reset kembali ke sana.
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
    const params = new URLSearchParams({
        from: from.value,
        to: to.value,
    });

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

    <div class="flex flex-col gap-4 p-4 md:p-6">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div>
                <h1 class="text-xl font-semibold tracking-tight">
                    Laporan Penjualan
                </h1>
                <p class="text-sm text-muted-foreground">
                    Rekap penjualan dan laba per buku
                </p>
            </div>
            <Button variant="outline" as-child>
                <a :href="exportUrl()" target="_blank" rel="noopener">
                    <Download class="size-4" />
                    Export .xlsx
                </a>
            </Button>
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
                        >
                            {{ label }}
                        </SelectItem>
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
                        >
                            {{ label }}
                        </SelectItem>
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
                        >
                            {{ label }}
                        </SelectItem>
                    </SelectContent>
                </Select>
            </div>
            <button
                v-if="hasActiveFilters"
                type="button"
                class="flex h-11 w-full items-center justify-center gap-2 text-sm text-muted-foreground transition-colors hover:bg-accent hover:text-destructive md:h-9 md:w-9"
                title="Hapus filter"
                aria-label="Hapus filter"
                @click="resetFilters"
            >
                <X class="size-4" />
                <span class="md:hidden">Hapus filter</span>
            </button>
        </div>

        <!-- Tabel per item (kolom = .xlsx) -->
        <DataTable
            :data="rows.data"
            :columns="columns"
            :paginator="rows"
            empty-title="Tidak ada data"
            empty-description="Tidak ada penjualan pada periode & filter ini."
        >
            <template #cell-cetakan="{ row }">
                {{ row.cetakan || '—' }}
            </template>
            <template #cell-harga_asli="{ row }">
                <Money :value="row.harga_asli" />
            </template>
            <template #cell-diskon="{ row }">
                <Money :value="row.diskon" />
            </template>
            <template #cell-harga_final="{ row }">
                <Money :value="row.harga_final" />
            </template>
            <template #cell-hpp="{ row }">
                <Money :value="row.hpp" />
            </template>
            <template #cell-laba="{ row }">
                <span
                    :class="
                        row.laba >= 0 ? 'text-green-600' : 'text-destructive'
                    "
                >
                    <Money :value="row.laba" />
                </span>
            </template>
        </DataTable>
    </div>
</template>

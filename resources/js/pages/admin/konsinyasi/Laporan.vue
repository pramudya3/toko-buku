<script setup lang="ts">
defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Beranda', href: '/admin/dashboard' },
            { title: 'Konsinyasi', href: '/admin/konsinyasi' },
            { title: 'Laporan' },
        ],
    },
});

import { Head, router } from '@inertiajs/vue3';
import { FileSpreadsheet, X } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import ConsignmentController from '@/actions/App/Http/Controllers/Admin/ConsignmentController';
import DataTable from '@/components/DataTable.vue';
import type { DataTableColumn } from '@/components/DataTable.vue';
import Money from '@/components/Money.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { laporan as laporanRoute } from '@/routes/admin/konsinyasi';

type Partner = {
    id: string;
    name: string;
};

type Row = {
    tanggal: string;
    jenis: string;
    jenis_key: string;
    mitra: string;
    item: string;
    qty: number;
    harga: number;
    subtotal: number;
    keterangan: string;
};

const props = defineProps<{
    rows: {
        data: Row[];
        current_page: number;
        last_page: number;
        total: number;
        per_page: number;
        links: Array<{ url: string | null; label: string; active: boolean }>;
    };
    partners: Partner[];
    stockSummary: {
        delivered_qty: number;
        sold_qty: number;
        returned_qty: number;
        remaining_qty: number;
    };
    filters: {
        from: string;
        to: string;
        customer_id: string | null;
        jenis: string;
    };
}>();

const columns: DataTableColumn[] = [
    {
        key: 'tanggal',
        header: 'Tanggal',
        cellClass: 'whitespace-nowrap text-muted-foreground',
    },
    { key: 'jenis', header: 'Jenis', cellClass: 'font-medium' },
    { key: 'mitra', header: 'Mitra' },
    { key: 'item', header: 'Judul', cellClass: 'max-w-56 truncate' },
    { key: 'qty', header: 'Qty', cellClass: 'text-right tabular-nums' },
    { key: 'harga', header: 'Harga', cellClass: 'text-right tabular-nums' },
    {
        key: 'subtotal',
        header: 'Subtotal',
        cellClass: 'text-right font-medium tabular-nums',
    },
    {
        key: 'keterangan',
        header: 'Keterangan',
        cellClass: 'max-w-40 truncate text-muted-foreground',
    },
];

const from = ref(props.filters.from);
const to = ref(props.filters.to);
const customerId = ref(
    props.filters.customer_id !== null
        ? String(props.filters.customer_id)
        : 'all',
);
const jenis = ref(props.filters.jenis);

const jenisLabel = computed(
    () =>
        ({
            semua: 'Semua Jenis',
            serah_terima: 'Serah Terima',
            laku: 'Lapor Laku',
            retur: 'Retur Sisa',
        })[jenis.value] ?? 'Semua Jenis',
);

// Snapshot awal (nilai server saat load) untuk tombol Reset — laporan punya
// default periode dari server (30 hari terakhir), jadi reset kembali ke sana.
const initialFrom = props.filters.from;
const initialTo = props.filters.to;
const initialCustomerId =
    props.filters.customer_id !== null
        ? String(props.filters.customer_id)
        : 'all';
const initialJenis = props.filters.jenis;

const hasActiveFilters = computed(
    () =>
        from.value !== initialFrom ||
        to.value !== initialTo ||
        customerId.value !== initialCustomerId ||
        jenis.value !== initialJenis,
);

let filterTimer: ReturnType<typeof setTimeout> | undefined;

function applyFilters() {
    clearTimeout(filterTimer);
    filterTimer = setTimeout(() => {
        router.get(
            laporanRoute().url,
            {
                from: from.value || undefined,
                to: to.value || undefined,
                customer_id:
                    customerId.value === 'all' ? undefined : customerId.value,
                jenis: jenis.value === 'semua' ? undefined : jenis.value,
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
    customerId.value = initialCustomerId;
    jenis.value = initialJenis;
    applyFilters();
}

watch([from, to, customerId, jenis], applyFilters);

function download() {
    const query: Record<string, string> = {};

    if (from.value) {
        query.from = from.value;
    }

    if (to.value) {
        query.to = to.value;
    }

    if (customerId.value && customerId.value !== 'all') {
        query.customer_id = customerId.value;
    }

    if (jenis.value !== 'semua') {
        query.jenis = jenis.value;
    }

    window.location.href = ConsignmentController.exportLaporan.url({ query });
}
</script>

<template>
    <Head title="Laporan Konsinyasi" />

    <div class="mx-auto flex w-full max-w-7xl flex-col gap-3 p-3 md:p-4">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div>
                <h1 class="text-xl font-semibold tracking-tight">
                    Laporan Konsinyasi
                </h1>
                <p class="text-sm text-muted-foreground">
                    Rekap titipan, penjualan & retur barang di mitra Bazaf
                </p>
            </div>
            <Button @click="download">
                <FileSpreadsheet class="size-4" />
                Export .xlsx
            </Button>
        </div>

        <!-- Ringkasan posisi titipan (dari seluruh data) -->
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <Card>
                <CardContent class="pt-4">
                    <p class="text-xs text-muted-foreground">Diserahkan</p>
                    <p class="mt-1 text-xl font-semibold tabular-nums">
                        {{ stockSummary.delivered_qty }}
                        <span class="text-sm font-normal text-muted-foreground"
                            >eks</span
                        >
                    </p>
                </CardContent>
            </Card>
            <Card>
                <CardContent class="pt-4">
                    <p class="text-xs text-muted-foreground">Laku</p>
                    <p class="mt-1 text-xl font-semibold tabular-nums">
                        {{ stockSummary.sold_qty }}
                        <span class="text-sm font-normal text-muted-foreground"
                            >eks</span
                        >
                    </p>
                </CardContent>
            </Card>
            <Card>
                <CardContent class="pt-4">
                    <p class="text-xs text-muted-foreground">Retur</p>
                    <p class="mt-1 text-xl font-semibold tabular-nums">
                        {{ stockSummary.returned_qty }}
                        <span class="text-sm font-normal text-muted-foreground"
                            >eks</span
                        >
                    </p>
                </CardContent>
            </Card>
            <Card>
                <CardContent class="pt-4">
                    <p class="text-xs text-muted-foreground">
                        Sisa Titipan Saat Ini
                    </p>
                    <p
                        class="mt-1 text-xl font-semibold text-primary tabular-nums"
                    >
                        {{ stockSummary.remaining_qty }}
                        <span class="text-sm font-normal text-muted-foreground"
                            >eks</span
                        >
                    </p>
                </CardContent>
            </Card>
        </div>

        <!-- Filter -->
        <div
            class="flex w-full flex-col divide-y divide-border overflow-hidden rounded-md border bg-card md:w-fit md:flex-row md:items-stretch md:divide-x md:divide-y-0"
        >
            <div class="md:flex md:items-center">
                <p
                    class="px-3 pt-2 text-xs font-medium text-muted-foreground md:hidden"
                >
                    Jenis
                </p>
                <Select v-model="jenis">
                    <SelectTrigger
                        class="h-11 w-full rounded-none border-0 bg-transparent px-3 shadow-none focus-visible:border-transparent focus-visible:ring-0 md:h-9 md:w-40"
                    >
                        <SelectValue>{{ jenisLabel }}</SelectValue>
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value="semua">Semua Jenis</SelectItem>
                        <SelectItem value="serah_terima"
                            >Serah Terima</SelectItem
                        >
                        <SelectItem value="laku">Lapor Laku</SelectItem>
                        <SelectItem value="retur">Retur</SelectItem>
                    </SelectContent>
                </Select>
            </div>

            <div class="md:flex md:items-center">
                <p
                    class="px-3 pt-2 text-xs font-medium text-muted-foreground md:hidden"
                >
                    Mitra
                </p>
                <Select v-model="customerId">
                    <SelectTrigger
                        class="h-11 w-full rounded-none border-0 bg-transparent px-3 shadow-none focus-visible:border-transparent focus-visible:ring-0 md:h-9 md:w-44"
                    >
                        <SelectValue placeholder="Semua mitra" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value="all">Semua mitra</SelectItem>
                        <SelectItem
                            v-for="partner in partners"
                            :key="partner.id"
                            :value="String(partner.id)"
                        >
                            {{ partner.name }}
                        </SelectItem>
                    </SelectContent>
                </Select>
            </div>

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

        <!-- Preview data (kolom identik dengan export .xlsx) -->
        <DataTable
            :data="rows.data"
            :columns="columns"
            :paginator="rows"
            empty-title="Gunakan filter tanggal"
            empty-description="Gunakan filter tanggal untuk menampilkan data laporan"
        >
            <template #cell-jenis="{ row }">
                <span
                    :class="
                        row.jenis_key === 'retur'
                            ? 'text-destructive'
                            : row.jenis_key === 'laku'
                              ? 'text-green-600'
                              : ''
                    "
                    class="text-sm font-medium"
                >
                    {{ row.jenis }}
                </span>
            </template>
            <template #cell-harga="{ row }">
                <Money :value="row.harga" />
            </template>
            <template #cell-subtotal="{ row }">
                <Money :value="row.subtotal" />
            </template>
            <template #cell-keterangan="{ row }">{{
                row.keterangan || '—'
            }}</template>
        </DataTable>
    </div>
</template>

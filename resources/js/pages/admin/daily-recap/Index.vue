<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { Download, X } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import DataTable from '@/components/DataTable.vue';
import type { DataTableColumn } from '@/components/DataTable.vue';
import Money from '@/components/Money.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { index as indexRoute, exportMethod } from '@/routes/admin/daily-recap';

type RecapRow = {
    tanggal: string;
    tanggal_key: string;
    order_count: number;
    item_count: number;
    omzet: number;
    cash: number;
    transfer: number;
    cod: number;
    hpp: number;
    laba: number;
};

const props = defineProps<{
    rows: RecapRow[];
    filters: { from: string; to: string };
}>();

const from = ref(props.filters.from);
const to = ref(props.filters.to);

// Snapshot awal (nilai server saat load) untuk tombol Reset.
const initialFrom = props.filters.from;
const initialTo = props.filters.to;

const hasActiveFilters = computed(
    () => from.value !== initialFrom || to.value !== initialTo,
);

let filterTimer: ReturnType<typeof setTimeout> | undefined;

function applyFilters() {
    clearTimeout(filterTimer);
    filterTimer = setTimeout(() => {
        router.get(
            indexRoute().url,
            { from: from.value || undefined, to: to.value || undefined },
            { preserveState: true, replace: true },
        );
    }, 350);
}

function resetFilters() {
    from.value = initialFrom;
    to.value = initialTo;
    applyFilters();
}

watch([from, to], applyFilters);

function exportUrl() {
    const params = new URLSearchParams({ from: from.value, to: to.value });

    return `${exportMethod().url}?${params.toString()}`;
}

const isWeekend = (tanggalKey: string) => {
    const day = new Date(tanggalKey + 'T00:00:00').getDay();

    return day === 0 || day === 6;
};

const columns: DataTableColumn[] = [
    {
        key: 'tanggal',
        header: 'Tanggal',
        cellClass: 'font-medium whitespace-nowrap',
    },
    {
        key: 'order_count',
        header: 'Order',
        cellClass: 'text-right tabular-nums',
    },
    {
        key: 'item_count',
        header: 'Item Terjual',
        cellClass: 'text-right tabular-nums',
    },
    {
        key: 'omzet',
        header: 'Omzet',
        cellClass: 'text-right font-medium tabular-nums',
    },
    { key: 'cash', header: 'Cash', cellClass: 'text-right tabular-nums' },
    {
        key: 'transfer',
        header: 'Transfer',
        cellClass: 'text-right tabular-nums',
    },
    { key: 'cod', header: 'COD', cellClass: 'text-right tabular-nums' },
    {
        key: 'hpp',
        header: 'HPP',
        cellClass: 'text-right text-muted-foreground tabular-nums',
    },
    {
        key: 'laba',
        header: 'Laba',
        cellClass: 'text-right font-medium tabular-nums',
    },
];
</script>

<template>
    <Head title="Rekap Harian" />

    <div class="flex flex-col gap-4 p-4 md:p-6">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div>
                <h1 class="text-xl font-semibold tracking-tight">
                    Rekap Harian Penjualan
                </h1>
                <p class="text-sm text-muted-foreground">
                    Ringkasan transaksi toko per tanggal
                </p>
            </div>
            <Button variant="outline" as-child>
                <a :href="exportUrl()" target="_blank" rel="noopener">
                    <Download class="size-4" />
                    Export .xlsx
                </a>
            </Button>
        </div>

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

        <DataTable
            :data="rows"
            :columns="columns"
            :row-class="
                (row) => (isWeekend(row.tanggal_key) ? 'bg-muted/30' : '')
            "
            key-field="tanggal_key"
            empty-title="Tidak ada penjualan"
            empty-description="Tidak ada order pada periode ini."
        >
            <template #cell-tanggal="{ row }">
                {{ row.tanggal }}
                <span
                    v-if="isWeekend(row.tanggal_key)"
                    class="ml-1 rounded bg-neutral-100 px-1 py-0.5 text-xs text-neutral-500"
                >
                    {{
                        ['Minggu', 'Sabtu'][
                            new Date(row.tanggal_key + 'T00:00:00').getDay() ===
                            0
                                ? 0
                                : 1
                        ]
                    }}
                </span>
            </template>
            <template #cell-omzet="{ row }">
                <Money :value="row.omzet" />
            </template>
            <template #cell-cash="{ row }">
                <Money :value="row.cash" />
            </template>
            <template #cell-transfer="{ row }">
                <Money :value="row.transfer" />
            </template>
            <template #cell-cod="{ row }">
                <Money :value="row.cod" />
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

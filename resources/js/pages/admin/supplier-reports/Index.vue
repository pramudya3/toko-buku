<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { FileSpreadsheet, X } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import SupplierReportController from '@/actions/App/Http/Controllers/Admin/SupplierReportController';
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
import { index as indexRoute } from '@/routes/admin/supplier-reports';

type Row = {
    tanggal: string;
    jenis: string;
    ref_code: string;
    supplier: string;
    item: string;
    qty: number;
    harga: number;
    subtotal: number;
    alasan: string;
    keterangan: string;
};

type SupplierOption = { id: string; nama: string };

const props = defineProps<{
    rows: {
        data: Row[];
        current_page: number;
        last_page: number;
        total: number;
        per_page: number;
        links: Array<{ url: string | null; label: string; active: boolean }>;
    };
    suppliers: SupplierOption[];
    filters: {
        from: string;
        to: string;
        supplier_id: string | null;
        jenis: string;
    };
}>();

const columns: DataTableColumn[] = [
    {
        key: 'tanggal',
        header: 'Tanggal',
        cellClass: 'font-medium whitespace-nowrap',
    },
    { key: 'jenis', header: 'Jenis', cellClass: 'font-medium' },
    { key: 'ref_code', header: 'Ref Code', cellClass: 'font-mono text-xs' },
    { key: 'supplier', header: 'Supplier' },
    { key: 'gudang', header: 'Gudang' },
    { key: 'item', header: 'Item', cellClass: 'max-w-56 truncate' },
    { key: 'qty', header: 'Qty', cellClass: 'text-right tabular-nums' },
    { key: 'harga', header: 'Harga', cellClass: 'text-right tabular-nums' },
    {
        key: 'subtotal',
        header: 'Subtotal',
        cellClass: 'text-right font-medium tabular-nums',
    },
    {
        key: 'alasan',
        header: 'Alasan',
        cellClass: 'max-w-40 truncate text-muted-foreground',
    },
    {
        key: 'keterangan',
        header: 'Keterangan',
        cellClass: 'max-w-40 truncate text-muted-foreground',
    },
];

const from = ref(props.filters.from);
const to = ref(props.filters.to);
const supplierId = ref(
    props.filters.supplier_id !== null
        ? String(props.filters.supplier_id)
        : 'all',
);
const jenis = ref(props.filters.jenis);

const jenisLabel = computed(
    () =>
        ({ semua: 'Semua', pembelian: 'Barang Masuk', retur: 'Retur' })[
            jenis.value
        ] ?? 'Semua',
);

// Snapshot awal (nilai server saat load) untuk tombol Reset.
const initialFrom = props.filters.from;
const initialTo = props.filters.to;
const initialSupplierId =
    props.filters.supplier_id !== null
        ? String(props.filters.supplier_id)
        : 'all';
const initialJenis = props.filters.jenis;

const hasActiveFilters = computed(
    () =>
        from.value !== initialFrom ||
        to.value !== initialTo ||
        supplierId.value !== initialSupplierId ||
        jenis.value !== initialJenis,
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
                supplier_id:
                    supplierId.value === 'all' ? undefined : supplierId.value,
                jenis: jenis.value === 'semua' ? undefined : jenis.value,
            },
            { preserveState: true, replace: true },
        );
    }, 350);
}

function resetFilters() {
    from.value = initialFrom;
    to.value = initialTo;
    supplierId.value = initialSupplierId;
    jenis.value = initialJenis;
    applyFilters();
}

watch([from, to, supplierId, jenis], applyFilters);

function download() {
    const query: Record<string, string> = {};

    if (from.value) {
        query.from = from.value;
    }

    if (to.value) {
        query.to = to.value;
    }

    if (supplierId.value && supplierId.value !== 'all') {
        query.supplier_id = supplierId.value;
    }

    if (jenis.value !== 'semua') {
        query.jenis = jenis.value;
    }

    window.location.href = SupplierReportController.exportMethod.url({ query });
}
</script>

<template>
    <Head title="Laporan Supplier" />

    <div class="flex flex-col gap-4 p-4 md:p-6">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div>
                <h1 class="text-xl font-semibold tracking-tight">
                    Laporan Supplier
                </h1>
                <p class="text-sm text-muted-foreground">
                    Rekap pembelian dan retur per supplier
                </p>
            </div>
            <Button @click="download">
                <FileSpreadsheet class="size-4" />
                Export .xlsx
            </Button>
        </div>

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
                        class="h-11 w-full rounded-none border-0 bg-transparent px-3 shadow-none focus-visible:border-transparent focus-visible:ring-0 md:h-9 md:w-44"
                    >
                        <SelectValue>{{ jenisLabel }}</SelectValue>
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value="semua"
                            >Semua (Barang Masuk + Retur)</SelectItem
                        >
                        <SelectItem value="pembelian"
                            >Barang Masuk saja</SelectItem
                        >
                        <SelectItem value="retur">Retur saja</SelectItem>
                    </SelectContent>
                </Select>
            </div>

            <div class="md:flex md:items-center">
                <p
                    class="px-3 pt-2 text-xs font-medium text-muted-foreground md:hidden"
                >
                    Supplier
                </p>
                <Select v-model="supplierId">
                    <SelectTrigger
                        class="h-11 w-full rounded-none border-0 bg-transparent px-3 shadow-none focus-visible:border-transparent focus-visible:ring-0 md:h-9 md:w-44"
                    >
                        <SelectValue placeholder="Semua supplier" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value="all">Semua supplier</SelectItem>
                        <SelectItem
                            v-for="supplier in suppliers"
                            :key="supplier.id"
                            :value="String(supplier.id)"
                        >
                            {{ supplier.nama }}
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

        <DataTable
            :data="rows.data"
            :columns="columns"
            :paginator="rows"
            empty-title="Tidak ada data"
            empty-description="Tidak ada barang masuk/retur pada periode & filter ini."
        >
            <template #cell-jenis="{ row }">
                <span
                    :class="
                        row.jenis === 'Retur'
                            ? 'text-destructive'
                            : 'text-green-600'
                    "
                    class="text-sm font-medium"
                >
                    {{ row.jenis }}
                </span>
            </template>
            <template #cell-ref_code="{ row }">{{
                row.ref_code || '—'
            }}</template>
            <template #cell-harga="{ row }">
                <Money :value="row.harga" />
            </template>
            <template #cell-subtotal="{ row }">
                <Money :value="row.subtotal" />
            </template>
            <template #cell-alasan="{ row }">{{ row.alasan || '—' }}</template>
            <template #cell-keterangan="{ row }">{{
                row.keterangan || '—'
            }}</template>
        </DataTable>
    </div>
</template>

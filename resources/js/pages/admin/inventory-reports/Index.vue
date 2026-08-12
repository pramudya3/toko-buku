<script setup lang="ts">
defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Beranda', href: '/admin/dashboard' },
            { title: 'Laporan Mutasi', href: '/admin/inventory-reports' },
        ],
    },
});

import { Head, router } from '@inertiajs/vue3';
import { Download, X } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import DataTable from '@/components/DataTable.vue';
import type { DataTableColumn } from '@/components/DataTable.vue';
import StatusBadge from '@/components/StatusBadge.vue';
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
} from '@/routes/admin/inventory-reports';

type Movement = {
    id: string;
    type: string;
    qty: number;
    reference: string | null;
    notes: string | null;
    created_at: string;
    book: { id: string; judul: string; kode_sku: string | null } | null;
    edition: { id: string; cetakan_ke: number } | null;
    from_warehouse: { id: string; nama: string } | null;
    to_warehouse: { id: string; nama: string } | null;
    user: { id: string; name: string } | null;
};

type Warehouse = {
    id: string;
    kode: string;
    nama: string;
    is_defect: boolean;
};

type Props = {
    movements: {
        data: Movement[];
        current_page: number;
        last_page: number;
        total: number;
        per_page: number;
        links: Array<{ url: string | null; label: string; active: boolean }>;
    };
    warehouses: Warehouse[];
    movementOptions: Record<string, string>;
    filters: {
        from: string;
        to: string;
        type: string | null;
        warehouse_id: string | null;
        search: string | null;
    };
};

const props = defineProps<Props>();

const columns: DataTableColumn[] = [
    {
        key: 'created_at',
        header: 'Tanggal',
        cellClass: 'font-medium whitespace-nowrap',
    },
    { key: 'reference', header: 'Ref', cellClass: 'font-mono text-xs' },
    { key: 'buku', header: 'Buku', cellClass: 'max-w-52' },
    { key: 'cetakan', header: 'Cetakan' },
    { key: 'type', header: 'Tipe' },
    { key: 'from_warehouse', header: 'Dari Gudang' },
    { key: 'to_warehouse', header: 'Ke Gudang' },
    {
        key: 'qty',
        header: 'Qty',
        cellClass: 'text-right font-medium tabular-nums',
    },
    { key: 'user', header: 'Petugas' },
    {
        key: 'notes',
        header: 'Keterangan',
        cellClass: 'max-w-48 truncate text-muted-foreground',
    },
];

const from = ref(props.filters.from);
const to = ref(props.filters.to);
const type = ref(props.filters.type ?? '');
const warehouseId = ref(
    props.filters.warehouse_id ? String(props.filters.warehouse_id) : '',
);
const search = ref(props.filters.search ?? '');

// Snapshot awal (nilai server saat load) untuk tombol Reset — laporan punya
// default periode dari server (bulan berjalan), jadi reset kembali ke sana.
const initialFrom = props.filters.from;
const initialTo = props.filters.to;
const initialType = props.filters.type ?? '';
const initialWarehouseId = props.filters.warehouse_id
    ? String(props.filters.warehouse_id)
    : '';
const initialSearch = props.filters.search ?? '';

const hasActiveFilters = computed(
    () =>
        from.value !== initialFrom ||
        to.value !== initialTo ||
        type.value !== initialType ||
        warehouseId.value !== initialWarehouseId ||
        search.value !== initialSearch,
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
                type: type.value === 'all' ? undefined : type.value,
                warehouse_id:
                    warehouseId.value === 'all' ? undefined : warehouseId.value,
                search: search.value || undefined,
            },
            { preserveState: true, replace: true },
        );
    }, 350);
}

function resetFilters() {
    from.value = initialFrom;
    to.value = initialTo;
    type.value = initialType;
    warehouseId.value = initialWarehouseId;
    search.value = initialSearch;
    applyFilters();
}

watch([from, to, type, warehouseId, search], applyFilters);

function exportUrl() {
    const params = new URLSearchParams({ from: from.value, to: to.value });

    if (type.value && type.value !== 'all') {
        params.set('type', type.value);
    }

    if (warehouseId.value && warehouseId.value !== 'all') {
        params.set('warehouse_id', warehouseId.value);
    }

    if (search.value) {
        params.set('search', search.value);
    }

    return `${exportMethod().url}?${params.toString()}`;
}

const typeVariant: Record<
    string,
    'success' | 'warning' | 'danger' | 'info' | 'neutral'
> = {
    in: 'success',
    out: 'danger',
    transfer: 'info',
    defect: 'warning',
    return: 'neutral',
    adjustment: 'info',
};

const typeLabel: Record<string, string> = {
    in: 'Masuk',
    out: 'Keluar',
    transfer: 'Transfer',
    defect: 'Defect',
    return: 'Retur',
    adjustment: 'Adjustment',
};
</script>

<template>
    <Head title="Laporan Mutasi" />

    <div class="flex flex-col gap-4 p-4 md:p-6">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div>
                <h1 class="text-xl font-semibold tracking-tight">
                    Laporan Mutasi Stok
                </h1>
                <p class="text-sm text-muted-foreground">
                    Riwayat pergerakan stok di semua gudang
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
                    Tipe
                </p>
                <Select v-model="type" name="type">
                    <SelectTrigger
                        class="h-11 w-full rounded-none border-0 bg-transparent px-3 shadow-none focus-visible:border-transparent focus-visible:ring-0 md:h-9 md:w-40"
                    >
                        <SelectValue placeholder="Semua tipe" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value="all">Semua tipe</SelectItem>
                        <SelectItem
                            v-for="(label, value) in movementOptions"
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
                    Gudang
                </p>
                <Select v-model="warehouseId" name="warehouse_id">
                    <SelectTrigger
                        class="h-11 w-full rounded-none border-0 bg-transparent px-3 shadow-none focus-visible:border-transparent focus-visible:ring-0 md:h-9 md:w-40"
                    >
                        <SelectValue placeholder="Semua gudang" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value="all">Semua gudang</SelectItem>
                        <SelectItem
                            v-for="warehouse in warehouses"
                            :key="warehouse.id"
                            :value="String(warehouse.id)"
                        >
                            {{ warehouse.nama }}
                        </SelectItem>
                    </SelectContent>
                </Select>
            </div>
            <div class="md:flex md:items-center">
                <p
                    class="px-3 pt-2 text-xs font-medium text-muted-foreground md:hidden"
                >
                    Cari
                </p>
                <Input
                    v-model="search"
                    class="h-11 w-full rounded-none border-0 bg-transparent px-3 shadow-none focus-visible:border-transparent focus-visible:ring-0 md:h-9 md:w-48"
                    placeholder="Cari buku / SKU..."
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

        <!-- Tabel mutasi -->
        <DataTable
            :data="movements.data"
            :columns="columns"
            :paginator="movements"
            empty-title="Tidak ada mutasi"
            empty-description="Tidak ada pergerakan stok pada periode & filter ini."
        >
            <template #cell-buku="{ row }">
                <p class="truncate font-medium">{{ row.book?.judul ?? '—' }}</p>
                <p class="text-xs text-muted-foreground">
                    {{ row.book?.kode_sku }}
                </p>
            </template>
            <template #cell-cetakan="{ row }">
                <template v-if="row.edition">
                    Cetakan ke-{{ row.edition.cetakan_ke }}
                </template>
                <span v-else class="text-muted-foreground">—</span>
            </template>
            <template #cell-type="{ row }">
                <StatusBadge
                    :variant="typeVariant[row.type] ?? 'neutral'"
                    :label="typeLabel[row.type] ?? row.type"
                />
            </template>
            <template #cell-from_warehouse="{ row }">
                {{ row.from_warehouse?.nama ?? '—' }}
            </template>
            <template #cell-to_warehouse="{ row }">
                {{ row.to_warehouse?.nama ?? '—' }}
            </template>
            <template #cell-qty="{ row }">
                <span
                    v-if="row.type === 'adjustment'"
                    :class="
                        row.qty > 0
                            ? 'text-green-600'
                            : 'text-destructive'
                    "
                >
                    {{ row.qty > 0 ? `+${row.qty}` : row.qty }}
                </span>
                <template v-else>{{ row.qty }}</template>
            </template>
            <template #cell-user="{ row }">
                {{ row.user?.name ?? '—' }}
            </template>
        </DataTable>
    </div>
</template>

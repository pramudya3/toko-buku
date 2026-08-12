<script setup lang="ts">
defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Beranda', href: '/admin/dashboard' },
            { title: 'Barang Masuk', href: '/admin/purchases' },
        ],
    },
});

import { Head, Link, router } from '@inertiajs/vue3';
import { Plus, Printer, X } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import SupplierPurchaseController from '@/actions/App/Http/Controllers/Admin/SupplierPurchaseController';
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
import { create, index as indexRoute } from '@/routes/admin/purchases';

type Purchase = {
    id: string;
    ref_code: string;
    purchase_date: string;
    total: number;
    paid: number;
    notes: string | null;
    items_count: number;
    supplier: { id: string; nama: string };
    items: Array<{
        qty: number;
        price: number;
        book: { id: string; judul: string; kode_sku: string | null };
    }>;
};

type SupplierOption = { id: string; nama: string };

const props = defineProps<{
    purchases: {
        data: Purchase[];
        current_page: number;
        last_page: number;
        total: number;
        per_page: number;
        links: Array<{ url: string | null; label: string; active: boolean }>;
    };
    suppliers: SupplierOption[];
    filters: { supplier_id?: string; from?: string; to?: string };
}>();

const columns: DataTableColumn[] = [
    { key: 'ref_code', header: 'Ref Code', cellClass: 'font-mono text-xs' },
    { key: 'purchase_date', header: 'Tanggal', cellClass: 'tabular-nums' },
    { key: 'supplier', header: 'Supplier' },
    { key: 'items', header: 'Item', cellClass: 'text-muted-foreground' },
    { key: 'total', header: 'Total', cellClass: 'text-right tabular-nums' },
    {
        key: 'sisa',
        header: 'Sisa Hutang',
        cellClass: 'text-right tabular-nums',
    },
    {
        key: 'aksi',
        header: '',
        cellClass: 'text-right',
        srOnly: true,
    },
];

const supplierId = ref(props.filters.supplier_id ?? '');
const from = ref(props.filters.from ?? '');
const to = ref(props.filters.to ?? '');

// Snapshot awal (nilai server saat load) untuk tombol Reset.
const initialSupplierId = props.filters.supplier_id ?? '';
const initialFrom = props.filters.from ?? '';
const initialTo = props.filters.to ?? '';

const hasActiveFilters = computed(
    () =>
        supplierId.value !== initialSupplierId ||
        from.value !== initialFrom ||
        to.value !== initialTo,
);

let filterTimer: ReturnType<typeof setTimeout> | undefined;

function applyFilters() {
    clearTimeout(filterTimer);
    filterTimer = setTimeout(() => {
        router.get(
            indexRoute().url,
            {
                supplier_id:
                    supplierId.value === 'all' ? undefined : supplierId.value,
                from: from.value || undefined,
                to: to.value || undefined,
            },
            { preserveState: true, replace: true },
        );
    }, 350);
}

function resetFilters() {
    supplierId.value = initialSupplierId;
    from.value = initialFrom;
    to.value = initialTo;
    applyFilters();
}

watch([supplierId, from, to], applyFilters);
</script>

<template>
    <Head title="Barang Masuk" />

    <div class="flex flex-col gap-4 p-4 md:p-6">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div>
                <h1 class="text-xl font-semibold tracking-tight">
                    Barang Masuk
                </h1>
                <p class="text-sm text-muted-foreground">
                    Riwayat pembelian buku dari supplier
                </p>
            </div>
            <Button as-child>
                <Link :href="create()">
                    <Plus class="size-4" />
                    Catat Barang Masuk
                </Link>
            </Button>
        </div>

        <div
            class="flex w-full flex-col divide-y divide-border overflow-hidden rounded-md border bg-card md:w-fit md:flex-row md:items-stretch md:divide-x md:divide-y-0"
        >
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
            :data="purchases.data"
            :columns="columns"
            :paginator="purchases"
            empty-title="Belum ada barang masuk"
            empty-description="Catat pembelian dari supplier melalui tombol di atas."
        >
            <template #cell-supplier="{ row }">
                {{ row.supplier.nama }}
            </template>
            <template #cell-items="{ row }">
                <template
                    v-for="(item, index) in row.items.slice(0, 2)"
                    :key="item.book.id"
                >
                    <span v-if="index > 0">, </span>
                    {{ item.book.judul }}
                    <span class="text-muted-foreground/60"
                        >×{{ item.qty }}</span
                    >
                </template>
                <span
                    v-if="row.items.length > 2"
                    class="text-muted-foreground/60"
                >
                    +{{ row.items.length - 2 }} lainnya
                </span>
            </template>
            <template #cell-total="{ row }">
                <Money :value="row.total" />
            </template>
            <template #cell-sisa="{ row }">
                <span
                    :class="
                        row.total - row.paid > 0
                            ? 'font-medium text-destructive'
                            : 'text-muted-foreground'
                    "
                >
                    <Money :value="row.total - row.paid" />
                </span>
            </template>
            <template #cell-aksi="{ row }">
                <a
                    :href="SupplierPurchaseController.invoice(row.id).url"
                    target="_blank"
                    rel="noopener"
                    class="inline-flex h-8 w-8 items-center justify-center rounded-md text-muted-foreground transition-colors hover:bg-accent hover:text-foreground"
                    :title="`Cetak nota ${row.ref_code}`"
                    aria-label="Cetak nota"
                >
                    <Printer class="size-4" />
                </a>
            </template>
        </DataTable>
    </div>
</template>

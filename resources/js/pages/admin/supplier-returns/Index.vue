<script setup lang="ts">
defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Beranda', href: '/admin/dashboard' },
            { title: 'Retur Supplier', href: '/admin/supplier-returns' },
        ],
    },
});

import { Head, router } from '@inertiajs/vue3';
import { X } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import DataTable from '@/components/DataTable.vue';
import type { DataTableColumn } from '@/components/DataTable.vue';
import Money from '@/components/Money.vue';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { index as indexRoute } from '@/routes/admin/supplier-returns';

type ReturnRecord = {
    id: string;
    return_date: string;
    total: number;
    notes: string | null;
    items_count: number;
    supplier: { id: string; nama: string };
    items: Array<{
        qty: number;
        price: number;
        reason: string;
        book: { id: string; judul: string; kode_sku: string | null };
    }>;
};

type SupplierOption = { id: string; nama: string };

const props = defineProps<{
    returns: {
        data: ReturnRecord[];
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
    { key: 'return_date', header: 'Tanggal', cellClass: 'tabular-nums' },
    { key: 'ref', header: 'Ref', cellClass: 'font-mono text-xs' },
    { key: 'supplier', header: 'Supplier' },
    { key: 'items', header: 'Item', cellClass: 'text-muted-foreground' },
    { key: 'total', header: 'Total', cellClass: 'text-right tabular-nums' },
];

const supplierId = ref(props.filters.supplier_id ?? '');
const from = ref(props.filters.from ?? '');
const to = ref(props.filters.to ?? '');

const hasActiveFilters = computed(
    () => supplierId.value !== '' || from.value !== '' || to.value !== '',
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
    supplierId.value = '';
    from.value = '';
    to.value = '';
    applyFilters();
}

watch([supplierId, from, to], applyFilters);
</script>

<template>
    <Head title="Retur Supplier" />

    <div class="mx-auto flex w-full max-w-7xl flex-col gap-3 p-3 md:p-4">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div>
                <h1 class="text-xl font-semibold tracking-tight">
                    Retur Supplier
                </h1>
                <p class="text-sm text-muted-foreground">
                    Riwayat pengembalian barang ke supplier — retur dibuat dari
                    menu Barang Masuk → Aksi → Retur
                </p>
            </div>
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
            :data="returns.data"
            :columns="columns"
            :paginator="returns"
            empty-title="Belum ada retur"
            empty-description="Retur dibuat dari menu Barang Masuk → Aksi → Retur pada faktur terkait."
        >
            <template #cell-ref="{ row }">RET-{{ row.id }}</template>
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
                    <span class="text-muted-foreground/60" title="Alasan">
                        ({{ item.reason }})
                    </span>
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
        </DataTable>
    </div>
</template>

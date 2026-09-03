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
import { Plus, X } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import SupplierPurchaseController from '@/actions/App/Http/Controllers/Admin/SupplierPurchaseController';
import DataTable from '@/components/DataTable.vue';
import type { DataTableColumn } from '@/components/DataTable.vue';
import DataTableActions from '@/components/DataTableActions.vue';
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
import { show as showPurchase } from '@/routes/admin/purchases';
import { create, index as indexRoute } from '@/routes/admin/purchases';
import { index as debtsIndex } from '@/routes/admin/supplier-debts';
import { create as createRetur } from '@/routes/admin/supplier-returns';

type Purchase = {
    id: string;
    ref_code: string;
    purchase_date: string;
    total: number;
    paid: number;
    notes: string | null;
    items_count: number;
    warehouse_kode: string | null;
    warehouse_kodes: string[] | null;
    warehouse: { kode: string; nama: string } | null;
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
    {
        key: 'expand',
        header: '',
        srOnly: true,
        expandable: true,
        cellClass: 'w-10',
    },
    {
        key: 'purchase_date',
        header: 'Tanggal',
        cellClass: 'tabular-nums whitespace-nowrap',
    },
    {
        key: 'ref_code',
        header: 'Ref Code',
        cellClass: 'font-mono text-xs whitespace-nowrap',
    },
    { key: 'supplier', header: 'Supplier', cellClass: 'whitespace-nowrap' },
    { key: 'gudang', header: 'Gudang', cellClass: 'whitespace-nowrap' },
    {
        key: 'total',
        header: 'Total',
        cellClass: 'text-right tabular-nums whitespace-nowrap',
    },
    {
        key: 'sisa',
        header: 'Sisa Hutang',
        cellClass: 'text-right tabular-nums whitespace-nowrap',
    },
    {
        key: 'aksi',
        header: '',
        cellClass: 'text-right',
        srOnly: true,
    },
];

const expandedIds = ref<Set<string>>(new Set());

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
    <Head title="Barang Masuk" />

    <div class="flex flex-col gap-6 p-6 md:p-8">
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
            expandable
            v-model:expanded-ids="expandedIds"
            empty-title="Belum ada barang masuk"
            empty-description="Catat pembelian dari supplier melalui tombol di atas."
        >
            <template #cell-supplier="{ row }">
                {{ row.supplier.nama }}
            </template>
            <template #cell-gudang="{ row }">
                <span class="whitespace-nowrap">
                    {{ row.warehouse?.nama ?? row.warehouse_kode ?? '—' }}
                </span>
                <span
                    v-if="row.warehouse_kodes && row.warehouse_kodes.length > 1"
                    class="block text-xs text-muted-foreground"
                >
                    {{ row.warehouse_kodes.join(', ') }}
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
                <DataTableActions
                    :actions="[
                        {
                            label: 'Detail',
                            href: showPurchase(row.id).url,
                        },
                        {
                            label: 'Print',
                            href: SupplierPurchaseController.invoice(row.id)
                                .url,
                        },
                        {
                            label: 'Retur',
                            href: createRetur({
                                query: {
                                    purchase_id: row.id,
                                    supplier_id: row.supplier.id,
                                },
                            }).url,
                        },
                        {
                            label: 'Bayar Hutang',
                            href: debtsIndex({
                                query: {
                                    supplier_id: row.supplier.id,
                                    purchase_id: row.id,
                                },
                            }).url,
                        },
                    ]"
                />
            </template>
            <template #expanded-row="{ row }">
                <div class="bg-muted/30 p-4">
                    <div class="grid gap-4 lg:grid-cols-[1fr_300px]">
                        <!-- Kiri: List Item — batas kirinya sejajar kolom Total -->
                        <div class="overflow-hidden rounded-lg border bg-card">
                            <div class="border-b bg-muted/50 px-4 py-2">
                                <p class="text-sm font-medium">
                                    Detail Item — {{ row.ref_code }} ({{
                                        row.items.length
                                    }}
                                    buku)
                                </p>
                            </div>
                            <div class="overflow-x-auto">
                                <table class="w-full text-sm">
                                    <thead>
                                        <tr
                                            class="border-b bg-muted/20 text-left text-xs text-muted-foreground"
                                        >
                                            <th
                                                class="w-24 px-3 py-2 font-medium"
                                            >
                                                Kode
                                            </th>
                                            <th class="px-3 py-2 font-medium">
                                                Buku
                                            </th>
                                            <th
                                                class="w-14 px-2 py-2 text-left font-medium"
                                            >
                                                Qty
                                            </th>
                                            <th
                                                class="w-24 px-2 py-2 text-left font-medium"
                                            >
                                                Harga
                                            </th>
                                            <th
                                                class="w-28 px-3 py-2 text-right font-medium"
                                            >
                                                Subtotal
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr
                                            v-for="item in row.items"
                                            :key="item.book.id"
                                            class="border-b last:border-0"
                                        >
                                            <td
                                                class="px-3 py-2 font-mono text-xs tabular-nums"
                                            >
                                                {{ item.book.kode_sku ?? '—' }}
                                            </td>
                                            <td class="px-3 py-2 font-medium">
                                                {{ item.book.judul }}
                                            </td>
                                            <td
                                                class="px-2 py-2 text-left tabular-nums"
                                            >
                                                {{ item.qty }}
                                            </td>
                                            <td
                                                class="px-2 py-2 text-left tabular-nums"
                                            >
                                                <Money :value="item.price" />
                                            </td>
                                            <td
                                                class="px-3 py-2 text-right tabular-nums"
                                            >
                                                <Money
                                                    :value="
                                                        item.qty * item.price
                                                    "
                                                />
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        <!-- Kanan: Ringkasan Hutang — table seperti item list -->
                        <div
                            class="h-fit overflow-hidden rounded-lg border bg-card"
                        >
                            <div class="border-b bg-muted/50 px-4 py-2">
                                <p class="text-sm font-medium">
                                    Ringkasan Hutang
                                </p>
                            </div>
                            <table class="w-full text-sm">
                                <tbody>
                                    <tr class="border-b">
                                        <td
                                            class="px-4 py-2 text-muted-foreground"
                                        >
                                            Total
                                        </td>
                                        <td
                                            class="px-4 py-2 text-right font-medium tabular-nums"
                                        >
                                            <Money :value="row.total" />
                                        </td>
                                    </tr>
                                    <tr class="border-b">
                                        <td
                                            class="px-4 py-2 text-muted-foreground"
                                        >
                                            Dibayar
                                        </td>
                                        <td
                                            class="px-4 py-2 text-right tabular-nums"
                                        >
                                            <Money :value="row.paid" />
                                        </td>
                                    </tr>
                                    <tr class="bg-muted/20 font-semibold">
                                        <td class="px-4 py-2">Sisa Hutang</td>
                                        <td
                                            :class="
                                                row.total - row.paid > 0
                                                    ? 'text-destructive'
                                                    : 'text-muted-foreground'
                                            "
                                            class="px-4 py-2 text-right tabular-nums"
                                        >
                                            <Money
                                                :value="row.total - row.paid"
                                            />
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </template>
        </DataTable>
    </div>
</template>

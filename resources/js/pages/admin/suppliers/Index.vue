<script setup lang="ts">
defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Beranda', href: '/admin/dashboard' },
            { title: 'Supplier', href: '/admin/suppliers' },
        ],
    },
});

import { Head, Link, router } from '@inertiajs/vue3';
import { Plus, Search, X } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import SupplierController from '@/actions/App/Http/Controllers/Admin/SupplierController';
import ConfirmDeleteDialog from '@/components/ConfirmDeleteDialog.vue';
import DataTable from '@/components/DataTable.vue';
import type { DataTableColumn } from '@/components/DataTable.vue';
import DataTableActions from '@/components/DataTableActions.vue';
import Money from '@/components/Money.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { index as indexRoute } from '@/routes/admin/suppliers';

type Supplier = {
    id: string;
    nama: string;
    telepon: string | null;
    alamat: string | null;
    purchase_total: number;
    return_total: number;
    payment_total: number;
    saldo_hutang: number;
};

const props = defineProps<{
    suppliers: {
        data: Supplier[];
        current_page: number;
        last_page: number;
        total: number;
        per_page: number;
        links: Array<{ url: string | null; label: string; active: boolean }>;
    };
    filters: { search?: string };
}>();

const columns: DataTableColumn[] = [
    { key: 'nama', header: 'Nama', cellClass: 'font-medium' },
    { key: 'telepon', header: 'Telepon', cellClass: 'text-muted-foreground' },
    {
        key: 'purchase_total',
        header: 'Total Beli',
        cellClass: 'text-right tabular-nums',
    },
    {
        key: 'return_total',
        header: 'Retur',
        cellClass: 'text-right tabular-nums',
    },
    {
        key: 'payment_total',
        header: 'Bayar',
        cellClass: 'text-right tabular-nums',
    },
    {
        key: 'saldo_hutang',
        header: 'Sisa Hutang',
        cellClass: 'text-right tabular-nums',
    },
    { key: 'aksi', header: 'Aksi', srOnly: true, cellClass: 'text-right' },
];

const search = ref(props.filters.search ?? '');

const hasActiveFilters = computed(() => search.value !== '');

let filterTimer: ReturnType<typeof setTimeout> | undefined;

function applyFilters() {
    clearTimeout(filterTimer);
    filterTimer = setTimeout(() => {
        router.get(
            indexRoute().url,
            {
                search: search.value || undefined,
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
    search.value = '';
    applyFilters();
}

watch([search], applyFilters);

// Hapus supplier
const deleting = ref<Supplier | null>(null);

function confirmDelete(supplier: Supplier) {
    deleting.value = supplier;
}

function executeDelete() {
    if (!deleting.value) {
        return;
    }

    const supplier = deleting.value;
    deleting.value = null;
    router.delete(SupplierController.destroy(supplier.id).url, {
        preserveScroll: true,
    });
}
</script>

<template>
    <Head title="Supplier" />

    <div class="mx-auto flex w-full max-w-7xl flex-col gap-3 p-3 md:p-4">
        <!-- HEADING -->
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div>
                <h1 class="text-xl font-semibold tracking-tight">Supplier</h1>
                <p class="text-sm text-muted-foreground">
                    Data pemasok buku untuk pembelian dan retur
                </p>
            </div>
            <Button as-child>
                <Link :href="SupplierController.create().url">
                    <Plus class="size-4" />
                    Tambah Supplier
                </Link>
            </Button>
        </div>

        <!-- FILTERS -->
        <div
            class="flex w-full flex-col divide-y divide-border overflow-hidden rounded-md border bg-card md:w-fit md:flex-row md:items-stretch md:divide-x md:divide-y-0"
        >
            <div class="relative flex items-center">
                <Search
                    class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
                />
                <Input
                    v-model="search"
                    class="h-11 w-full rounded-none border-0 bg-transparent pl-9 shadow-none focus-visible:ring-0 md:h-9 md:w-56"
                    placeholder="Cari nama / telepon..."
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
            :data="suppliers.data"
            :columns="columns"
            :paginator="suppliers"
            empty-title="Belum ada supplier"
            empty-description="Tambahkan supplier pertama untuk mulai mencatat pembelian."
        >
            <template #cell-telepon="{ row }">
                {{ row.telepon ?? '—' }}
            </template>
            <template #cell-purchase_total="{ row }">
                <Money :value="row.purchase_total" />
            </template>
            <template #cell-return_total="{ row }">
                <Money :value="row.return_total" />
            </template>
            <template #cell-payment_total="{ row }">
                <Money :value="row.payment_total" />
            </template>
            <template #cell-saldo_hutang="{ row }">
                <span
                    :class="
                        row.saldo_hutang > 0
                            ? 'font-medium text-destructive'
                            : 'text-muted-foreground'
                    "
                >
                    <Money :value="row.saldo_hutang" />
                </span>
            </template>
            <template #cell-aksi="{ row }">
                <DataTableActions
                    :actions="[
                        {
                            label: 'Edit',
                            href: SupplierController.edit(row.id).url,
                        },
                        {
                            label: 'Hapus',
                            variant: 'destructive',
                            onClick: () => confirmDelete(row),
                        },
                    ]"
                />
            </template>
            <template #empty>
                <Button as-child>
                    <Link :href="SupplierController.create().url">
                        <Plus class="size-4" />
                        Tambah Supplier
                    </Link>
                </Button>
            </template>
        </DataTable>
    </div>

    <ConfirmDeleteDialog
        :open="!!deleting"
        @update:open="
            (open) => {
                if (!open) deleting = null;
            }
        "
        title="Hapus Supplier?"
        :description="
            deleting
                ? `Supplier '${deleting.nama}' akan dihapus. Supplier yang punya riwayat transaksi tidak bisa dihapus.`
                : ''
        "
        @confirm="executeDelete"
    />
</template>

<script setup lang="ts">
defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Beranda', href: '/admin/dashboard' },
            { title: 'Gudang', href: '/admin/warehouses' },
        ],
    },
});

import { Head, Link, router } from '@inertiajs/vue3';
import { Plus, Search, X } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import WarehouseController from '@/actions/App/Http/Controllers/Admin/WarehouseController';
import ConfirmDeleteDialog from '@/components/ConfirmDeleteDialog.vue';
import DataTable from '@/components/DataTable.vue';
import type { DataTableColumn } from '@/components/DataTable.vue';
import DataTableActions from '@/components/DataTableActions.vue';
import StatusBadge from '@/components/StatusBadge.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { index as indexRoute } from '@/routes/admin/warehouses';

type Warehouse = {
    id: string;
    nama: string;
    alamat: string | null;
    is_defect: boolean;
    is_active: boolean;
};

const props = defineProps<{
    warehouses: {
        data: Warehouse[];
        current_page: number;
        last_page: number;
        total: number;
        per_page: number;
        links: Array<{ url: string | null; label: string; active: boolean }>;
    };
    filters: { search?: string };
}>();

const columns: DataTableColumn[] = [
    { key: 'nama', header: 'Nama' },
    { key: 'status', header: 'Status' },
    { key: 'aksi', header: 'Aksi', srOnly: true, cellClass: 'text-right' },
];

const search = ref(props.filters.search ?? '');

// Snapshot awal (nilai server saat load) untuk tombol Reset.
const initialSearch = props.filters.search ?? '';

const hasActiveFilters = computed(() => search.value !== initialSearch);

let filterTimer: ReturnType<typeof setTimeout> | undefined;

function applyFilters() {
    clearTimeout(filterTimer);
    filterTimer = setTimeout(() => {
        router.get(
            indexRoute().url,
            { search: search.value || undefined },
            { preserveState: true, replace: true },
        );
    }, 350);
}

function resetFilters() {
    search.value = initialSearch;
    applyFilters();
}

watch([search], applyFilters);

const deleting = ref<Warehouse | null>(null);

function confirmDelete(warehouse: Warehouse) {
    deleting.value = warehouse;
}

function executeDelete() {
    if (!deleting.value) {
        return;
    }

    const warehouse = deleting.value;
    deleting.value = null;
    router.delete(WarehouseController.destroy(warehouse.id).url, {
        preserveScroll: true,
    });
}
</script>

<template>
    <Head title="Gudang" />

    <div class="flex flex-col gap-4 p-4 md:p-6">
        <!-- HEADING -->
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div>
                <h1 class="text-xl font-semibold tracking-tight">Gudang</h1>
                <p class="text-sm text-muted-foreground">
                    Kelola lokasi penyimpanan stok buku
                </p>
            </div>
            <Button as-child>
                <Link :href="WarehouseController.create().url">
                    <Plus class="size-4" />
                    Tambah Gudang
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
                    placeholder="Cari nama / kode..."
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

        <!-- TABLE -->
        <DataTable
            :data="warehouses.data"
            :columns="columns"
            :paginator="warehouses"
            empty-title="Belum ada gudang"
            empty-description="Tambahkan gudang pertama untuk mulai mengelola stok per lokasi."
        >
            <template #cell-nama="{ row }">
                <span class="font-medium">{{ row.nama }}</span>
                <p v-if="row.alamat" class="text-xs text-muted-foreground">
                    {{ row.alamat }}
                </p>
            </template>
            <template #cell-status="{ row }">
                <div class="flex flex-wrap gap-1">
                    <StatusBadge
                        v-if="row.is_defect"
                        variant="warning"
                        label="Defect"
                    />
                    <StatusBadge
                        :variant="row.is_active ? 'success' : 'neutral'"
                        :label="row.is_active ? 'Aktif' : 'Nonaktif'"
                    />
                </div>
            </template>
            <template #cell-aksi="{ row }">
                <DataTableActions
                    :actions="[
                        {
                            label: 'Edit',
                            href: WarehouseController.edit(row.id).url,
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
                    <Link :href="WarehouseController.create().url">
                        <Plus class="size-4" />
                        Tambah Gudang
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
        title="Hapus Gudang?"
        :description="
            deleting
                ? `Gudang '${deleting.nama}' akan dihapus. Gudang yang masih menyimpan stok atau punya riwayat mutasi tidak bisa dihapus.`
                : ''
        "
        @confirm="executeDelete"
    />
</template>

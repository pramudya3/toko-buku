<script setup lang="ts">
defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Beranda', href: '/admin/dashboard' },
            { title: 'Promosi', href: '/admin/promotions' },
        ],
    },
});

import { Head, Link, router } from '@inertiajs/vue3';
import { Plus, Search, Upload, X } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import PromotionController from '@/actions/App/Http/Controllers/Admin/PromotionController';
import ConfirmDeleteDialog from '@/components/ConfirmDeleteDialog.vue';
import DataTable from '@/components/DataTable.vue';
import type { DataTableColumn } from '@/components/DataTable.vue';
import DataTableActions from '@/components/DataTableActions.vue';
import ImportCsvDialog from '@/components/ImportCsvDialog.vue';
import StatusBadge from '@/components/StatusBadge.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { create, edit, index as indexRoute } from '@/routes/admin/promotions';

type Promotion = {
    id: string;
    promo_name: string;
    promo_type: string;
    discount_percentage: number | null;
    promo_value: number | null;
    start_date: string;
    end_date: string;
    is_active: boolean;
    books_count: number;
};

type Props = {
    promotions: {
        data: Promotion[];
        current_page: number;
        last_page: number;
        total: number;
        per_page: number;
        links: Array<{ url: string | null; label: string; active: boolean }>;
    };
    filters: { search?: string };
    typeOptions: Record<string, string>;
};

const props = defineProps<Props>();

const columns: DataTableColumn[] = [
    { key: 'promo_name', header: 'Nama', cellClass: 'font-medium' },
    { key: 'promo_type', header: 'Tipe' },
    { key: 'nilai', header: 'Nilai', cellClass: 'text-right tabular-nums' },
    { key: 'periode', header: 'Periode', cellClass: 'text-muted-foreground' },
    { key: 'books_count', header: 'Buku' },
    { key: 'is_active', header: 'Status' },
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
            {
                preserveState: true,
                replace: true,
            },
        );
    }, 350);
}

function resetFilters() {
    search.value = '';
    applyFilters();
}

watch([search], applyFilters);

const typeLabel = (promo: Promotion) => {
    switch (promo.promo_type) {
        case 'percentage':
            return `${promo.discount_percentage}%`;
        case 'fixed':
            return `Rp ${promo.promo_value?.toLocaleString('id-ID')}`;
        case 'bundle':
            return `Paket ${promo.discount_percentage}%`;
        default:
            return promo.promo_type;
    }
};

const typeVariant: Record<
    string,
    'success' | 'warning' | 'danger' | 'info' | 'neutral'
> = {
    percentage: 'info',
    fixed: 'warning',
    bundle: 'success',
};

function confirmDelete(promo: Promotion) {
    deletingPromo.value = promo;
}

const deletingPromo = ref<Promotion | null>(null);
const importOpen = ref(false);

function executeDelete() {
    if (!deletingPromo.value) {
        return;
    }

    const promo = deletingPromo.value;

    deletingPromo.value = null;
    router.delete(PromotionController.destroy(promo.id).url, {
        preserveScroll: true,
    });
}
</script>

<template>
    <Head title="Promosi" />

    <div class="mx-auto flex w-full max-w-7xl flex-col gap-3 p-3 md:p-4">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div>
                <h1 class="text-xl font-semibold tracking-tight">Promosi</h1>
                <p class="text-sm text-muted-foreground">
                    Mengelola promo, diskon, dan bundle item
                </p>
            </div>
            <div class="flex items-center gap-2">
                <Button variant="outline" @click="importOpen = true">
                    <Upload class="size-4" />
                    Import CSV
                </Button>
                <Button as-child>
                    <Link :href="create()">
                        <Plus class="size-4" />
                        Buat Promo
                    </Link>
                </Button>
            </div>
        </div>

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
                    placeholder="Cari promo..."
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
            :data="promotions.data"
            :columns="columns"
            :paginator="promotions"
            empty-title="Belum ada promo"
            empty-description="Buat promosi pertama untuk menarik pembeli."
        >
            <template #cell-promo_type="{ row }">
                <StatusBadge
                    :variant="typeVariant[row.promo_type] ?? 'neutral'"
                    :label="typeOptions[row.promo_type] ?? row.promo_type"
                />
            </template>
            <template #cell-nilai="{ row }">
                {{ typeLabel(row) }}
            </template>
            <template #cell-periode="{ row }">
                {{ row.start_date }} → {{ row.end_date }}
            </template>
            <template #cell-books_count="{ row }">
                {{
                    row.books_count === 0 ? 'Global' : `${row.books_count} buku`
                }}
            </template>
            <template #cell-is_active="{ row }">
                <StatusBadge
                    :variant="row.is_active ? 'success' : 'danger'"
                    :label="row.is_active ? 'Aktif' : 'Nonaktif'"
                />
            </template>
            <template #cell-aksi="{ row }">
                <DataTableActions
                    :actions="[
                        {
                            label: 'Edit',
                            href: edit(row.id).url,
                        },
                        {
                            label: row.is_active ? 'Nonaktifkan' : 'Aktifkan',
                            onClick: () =>
                                router.patch(
                                    PromotionController.toggle(row.id).url,
                                    { preserveScroll: true },
                                ),
                        },
                        {
                            label: 'Hapus',
                            variant: 'destructive',
                            onClick: () => confirmDelete(row),
                        },
                    ]"
                />
            </template>
        </DataTable>

        <ConfirmDeleteDialog
            :open="!!deletingPromo"
            @update:open="
                (open) => {
                    if (!open) deletingPromo = null;
                }
            "
            title="Hapus Promo?"
            :description="
                deletingPromo
                    ? `Promo '${deletingPromo.promo_name}' akan dihapus.`
                    : ''
            "
            @confirm="executeDelete"
        />
    </div>

    <ImportCsvDialog
        v-model:open="importOpen"
        :action="PromotionController.importCsv.form()"
        template-type="promotions"
        title="Import Promo dari CSV"
        description="Format kolom: promo_name,promo_type,discount_percent,komponen"
        hint="Komponen = judul buku dipisah | (bundle)."
    />
</template>

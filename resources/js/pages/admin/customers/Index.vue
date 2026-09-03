<script setup lang="ts">
defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Beranda', href: '/admin/dashboard' },
            { title: 'Pelanggan', href: '/admin/customers' },
        ],
    },
});

import { Head, Link, router } from '@inertiajs/vue3';
import { Plus, Search, Upload, X } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import CustomerController from '@/actions/App/Http/Controllers/Admin/CustomerController';
import DataTable from '@/components/DataTable.vue';
import type { DataTableColumn } from '@/components/DataTable.vue';
import DataTableActions from '@/components/DataTableActions.vue';
import ImportCsvDialog from '@/components/ImportCsvDialog.vue';
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
import { create, edit, index as indexRoute } from '@/routes/admin/customers';

type Customer = {
    id: string;
    name: string;
    email: string;
    whatsapp_number: string | null;
    status_pelanggan: string;
    is_active: boolean;
    orders_count: number;
};

type Props = {
    customers: {
        data: Customer[];
        current_page: number;
        last_page: number;
        total: number;
        per_page: number;
        links: Array<{ url: string | null; label: string; active: boolean }>;
    };
    filters: { search?: string; tier?: string; is_active?: string };
    tierOptions: Record<string, string>;
};

const props = defineProps<Props>();

const columns: DataTableColumn[] = [
    { key: 'name', header: 'Nama', cellClass: 'font-medium' },
    { key: 'email', header: 'Email' },
    { key: 'whatsapp_number', header: 'WhatsApp', cellClass: 'tabular-nums' },
    { key: 'status_pelanggan', header: 'Tier' },
    { key: 'is_active', header: 'Status' },
    { key: 'aksi', header: 'Aksi', srOnly: true, cellClass: 'text-right' },
];

const search = ref(props.filters.search ?? '');
const tier = ref(props.filters.tier ?? '__all__');

const hasActiveFilters = computed(
    () => search.value !== '' || tier.value !== '__all__',
);

let filterTimer: ReturnType<typeof setTimeout> | undefined;

function applyFilters() {
    clearTimeout(filterTimer);
    filterTimer = setTimeout(() => {
        router.get(
            indexRoute().url,
            {
                search: search.value || undefined,
                tier: tier.value === '__all__' ? undefined : tier.value,
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
    tier.value = '__all__';
    applyFilters();
}

watch([search, tier], applyFilters);

const tierVariant: Record<
    string,
    'success' | 'warning' | 'danger' | 'info' | 'neutral'
> = {
    reguler: 'neutral',
    bazaf: 'info',
    guru: 'success',
    reseller: 'warning',
};

const tierLabel: Record<string, string> = {
    reguler: 'Reguler',
    bazaf: 'Bazaf',
    guru: 'Guru',
    reseller: 'Reseller',
};

const importOpen = ref(false);
</script>

<template>
    <Head title="Pelanggan" />

    <div class="mx-auto flex w-full max-w-7xl flex-col gap-3 p-3 md:p-4">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div>
                <h1 class="text-xl font-semibold tracking-tight">Pelanggan</h1>
                <p class="text-sm text-muted-foreground">
                    Mengelola data dan status tier pelanggan
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
                        Buat Pelanggan
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
                    placeholder="Cari nama, email, WhatsApp..."
                />
            </div>
            <div class="flex items-center border-t md:border-t-0 md:border-l">
                <Select v-model="tier">
                    <SelectTrigger
                        class="h-11 w-full rounded-none border-0 bg-transparent px-3 shadow-none focus-visible:ring-0 md:h-9 md:w-44"
                    >
                        <SelectValue placeholder="Semua tier" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value="__all__">Semua tier</SelectItem>
                        <SelectItem
                            v-for="(label, value) in tierOptions"
                            :key="value"
                            :value="value"
                            >{{ label }}</SelectItem
                        >
                    </SelectContent>
                </Select>
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
            :data="customers.data"
            :columns="columns"
            :paginator="customers"
            empty-title="Tidak ada pelanggan"
            empty-description="Pelanggan storefront akan muncul di sini."
        >
            <template #cell-whatsapp_number="{ row }">
                {{ row.whatsapp_number ?? '—' }}
            </template>
            <template #cell-status_pelanggan="{ row }">
                <StatusBadge
                    :variant="tierVariant[row.status_pelanggan] ?? 'neutral'"
                    :label="
                        tierLabel[row.status_pelanggan] ?? row.status_pelanggan
                    "
                />
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
                    ]"
                />
            </template>
        </DataTable>
    </div>

    <ImportCsvDialog
        v-model:open="importOpen"
        :action="CustomerController.importCsv.form()"
        template-type="customers"
        title="Import Pelanggan dari CSV"
        description="Format kolom: PENERIMA,TUJUAN,Kota/Kabupaten,Kecamatan,Kelurahan"
        hint="Email dikosongkan (isi manual via edit bila perlu). Nama yang sudah ada diperbarui alamatnya; baris identik dilewati."
    />
</template>

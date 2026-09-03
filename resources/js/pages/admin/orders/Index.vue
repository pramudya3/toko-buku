<script setup lang="ts">
defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Beranda', href: '/admin/dashboard' },
            { title: 'Pesanan', href: '/admin/orders' },
        ],
    },
});

import { Head, Link, router } from '@inertiajs/vue3';
import { PackageSearch, Search, X } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import DataTable from '@/components/DataTable.vue';
import type { DataTableColumn } from '@/components/DataTable.vue';
import DataTableActions from '@/components/DataTableActions.vue';
import Money from '@/components/Money.vue';
import StatusBadge from '@/components/StatusBadge.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { create, index as indexRoute, show } from '@/routes/admin/orders';

type Order = {
    id: string;
    no_order: string;
    nama_pembeli: string;
    sumber_pembelian: string | null;
    total: number;
    status: string;
    is_dropship: boolean;
    created_at: string;
    items_count: number;
    preorder_items_count: number;
};

type Props = {
    orders: {
        data: Order[];
        current_page: number;
        last_page: number;
        total: number;
        per_page: number;
        links: Array<{ url: string | null; label: string; active: boolean }>;
    };
    filters: {
        search?: string;
        status?: string;
        from?: string;
        to?: string;
        dropship?: string;
        sumber_pembelian?: string;
        preorder?: string;
    };
    statusOptions: Record<string, string>;
    salesChannels: Record<string, string>;
};

const props = defineProps<Props>();

const columns: DataTableColumn[] = [
    { key: 'tanggal', header: 'Tanggal', cellClass: 'text-muted-foreground' },
    { key: 'no_order', header: 'No. Order', cellClass: 'font-medium' },
    { key: 'nama_pembeli', header: 'Pembeli' },
    { key: 'sumber', header: 'Sumber' },
    {
        key: 'items_count',
        header: 'Item',
        cellClass: 'text-right tabular-nums',
    },
    { key: 'total', header: 'Total', cellClass: 'text-right tabular-nums' },
    { key: 'status', header: 'Status', cellClass: 'text-center' },
    { key: 'aksi', header: 'Aksi', srOnly: true, cellClass: 'text-right' },
];

const allStatuses = '__all_statuses__';
const allSources = '__all_sources__';
const search = ref(props.filters.search ?? '');
const status = ref(props.filters.status ?? allStatuses);
const from = ref(props.filters.from ?? '');
const to = ref(props.filters.to ?? '');
const dropship = ref(props.filters.dropship === '1');
const preorder = ref(props.filters.preorder === '1');
const sumberPembelian = ref(props.filters.sumber_pembelian ?? allSources);

const hasActiveFilters = computed(
    () =>
        search.value !== '' ||
        status.value !== allStatuses ||
        from.value !== '' ||
        to.value !== '' ||
        dropship.value !== false ||
        preorder.value !== false ||
        sumberPembelian.value !== allSources,
);

let filterTimer: ReturnType<typeof setTimeout> | undefined;

function applyFilters() {
    clearTimeout(filterTimer);
    filterTimer = setTimeout(() => {
        router.get(
            indexRoute().url,
            {
                search: search.value || undefined,
                status: status.value === allStatuses ? undefined : status.value,
                from: from.value || undefined,
                to: to.value || undefined,
                dropship: dropship.value ? '1' : undefined,
                preorder: preorder.value ? '1' : undefined,
                sumber_pembelian:
                    sumberPembelian.value === allSources
                        ? undefined
                        : sumberPembelian.value,
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
    status.value = allStatuses;
    from.value = '';
    to.value = '';
    dropship.value = false;
    preorder.value = false;
    sumberPembelian.value = allSources;
    applyFilters();
}

watch(
    [search, status, from, to, dropship, preorder, sumberPembelian],
    applyFilters,
);

const statusVariant: Record<
    string,
    'success' | 'warning' | 'danger' | 'info' | 'neutral'
> = {
    menunggu_konfirmasi: 'warning',
    diproses: 'info',
    dikirim: 'info',
    selesai: 'success',
    batal: 'danger',
};
</script>

<template>
    <Head title="Pesanan" />

    <div class="mx-auto flex w-full max-w-7xl flex-col gap-3 p-3 md:p-4">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div>
                <h1 class="text-xl font-semibold tracking-tight">Pesanan</h1>
                <p class="text-sm text-muted-foreground">
                    Mengelola pesanan dari storefront dan WhatsApp
                </p>
            </div>
            <Button as-child>
                <Link :href="create()">
                    <PackageSearch class="size-4" />
                    Buat Pesanan
                </Link>
            </Button>
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
                    class="h-11 w-full rounded-none border-0 bg-transparent pl-9 shadow-none focus-visible:border-transparent focus-visible:ring-0 md:h-9 md:w-56"
                    placeholder="Cari no. order / nama pembeli..."
                />
            </div>

            <div class="md:flex md:items-center">
                <p
                    class="px-3 pt-2 text-xs font-medium text-muted-foreground md:hidden"
                >
                    Status
                </p>
                <Select v-model="status">
                    <SelectTrigger
                        class="h-11 w-full rounded-none border-0 bg-transparent px-3 shadow-none focus-visible:border-transparent focus-visible:ring-0 md:h-9 md:w-40"
                    >
                        <SelectValue placeholder="Semua status" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem :value="allStatuses"
                            >Semua status</SelectItem
                        >
                        <SelectItem
                            v-for="(label, value) in statusOptions"
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
                    Sumber
                </p>
                <Select v-model="sumberPembelian" name="sumber_pembelian">
                    <SelectTrigger
                        class="h-11 w-full rounded-none border-0 bg-transparent px-3 shadow-none focus-visible:border-transparent focus-visible:ring-0 md:h-9 md:w-40"
                    >
                        <SelectValue placeholder="Semua sumber" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem :value="allSources"
                            >Semua sumber</SelectItem
                        >
                        <SelectItem
                            v-for="(label, value) in salesChannels"
                            :key="value"
                            :value="value"
                        >
                            {{ label }}
                        </SelectItem>
                    </SelectContent>
                </Select>
            </div>
            <div class="md:flex md:items-center">
                <Label class="flex h-11 items-center gap-2 px-3 text-sm md:h-9">
                    <Checkbox v-model="dropship" />
                    Dropship
                </Label>
            </div>
            <div class="md:flex md:items-center">
                <Label class="flex h-11 items-center gap-2 px-3 text-sm md:h-9">
                    <Checkbox v-model="preorder" />
                    Pre-Order
                </Label>
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
            :data="orders.data"
            :columns="columns"
            :paginator="orders"
            empty-title="Tidak ada pesanan"
            empty-description="Order dari storefront atau WhatsApp akan tampil di sini."
        >
            <template #cell-no_order="{ row }">
                {{ row.no_order }}
                <span v-if="row.is_dropship" class="ml-1 text-xs text-blue-600"
                    >(dropship)</span
                >
                <span
                    v-if="row.preorder_items_count > 0"
                    class="ml-1 rounded-full bg-sky-100 px-1.5 py-0.5 text-[10px] font-semibold text-sky-800"
                    title="Mengandung item pre-order yang menunggu stok"
                >
                    PO
                </span>
            </template>
            <template #cell-total="{ row }">
                <Money :value="row.total" />
            </template>
            <template #cell-sumber="{ row }">
                {{
                    salesChannels[row.sumber_pembelian ?? ''] ??
                    row.sumber_pembelian ??
                    '—'
                }}
            </template>
            <template #cell-status="{ row }">
                <StatusBadge
                    :variant="statusVariant[row.status] ?? 'neutral'"
                    :label="statusOptions[row.status] ?? row.status"
                />
            </template>
            <template #cell-tanggal="{ row }">
                {{
                    new Date(row.created_at).toLocaleDateString('id-ID', {
                        timeZone: 'Asia/Jakarta',
                    })
                }}
            </template>
            <template #cell-aksi="{ row }">
                <DataTableActions
                    :actions="[
                        {
                            label: 'Detail',
                            href: show(row.id).url,
                        },
                    ]"
                />
            </template>
        </DataTable>
    </div>
</template>

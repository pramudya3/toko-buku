<script setup lang="ts">
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
    total: number;
    status: string;
    is_dropship: boolean;
    created_at: string;
    items_count: number;
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
    filters: { search?: string; status?: string; dropship?: string };
    statusOptions: Record<string, string>;
};

const props = defineProps<Props>();

const columns: DataTableColumn[] = [
    { key: 'no_order', header: 'No. Order', cellClass: 'font-medium' },
    { key: 'nama_pembeli', header: 'Pembeli' },
    {
        key: 'items_count',
        header: 'Item',
        cellClass: 'text-right tabular-nums',
    },
    { key: 'total', header: 'Total', cellClass: 'text-right tabular-nums' },
    { key: 'status', header: 'Status' },
    { key: 'tanggal', header: 'Tanggal', cellClass: 'text-muted-foreground' },
    { key: 'aksi', header: 'Aksi', srOnly: true, cellClass: 'text-right' },
];

const allStatuses = '__all_statuses__';
const search = ref(props.filters.search ?? '');
const status = ref(props.filters.status ?? allStatuses);
const dropship = ref(props.filters.dropship === '1');

// Snapshot awal (nilai server saat load) untuk tombol Reset.
const initialSearch = props.filters.search ?? '';
const initialStatus = props.filters.status ?? allStatuses;
const initialDropship = props.filters.dropship === '1';

const hasActiveFilters = computed(
    () =>
        search.value !== initialSearch ||
        status.value !== initialStatus ||
        dropship.value !== initialDropship,
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
                dropship: dropship.value ? '1' : undefined,
            },
            {
                preserveState: true,
                replace: true,
            },
        );
    }, 350);
}

function resetFilters() {
    search.value = initialSearch;
    status.value = initialStatus;
    dropship.value = initialDropship;
    applyFilters();
}

watch([search, status, dropship], applyFilters);

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

    <div class="flex flex-col gap-4 p-4 md:p-6">
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
                <Label class="flex h-11 items-center gap-2 px-3 text-sm md:h-9">
                    <Checkbox v-model="dropship" />
                    Dropship
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
            </template>
            <template #cell-total="{ row }">
                <Money :value="row.total" />
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

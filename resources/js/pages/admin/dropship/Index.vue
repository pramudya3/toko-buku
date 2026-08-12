<script setup lang="ts">
defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Beranda', href: '/admin/dashboard' },
            { title: 'Dropship', href: '/admin/dropship' },
        ],
    },
});

import { Head, router } from '@inertiajs/vue3';
import { X } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import DataTable from '@/components/DataTable.vue';
import type { DataTableColumn } from '@/components/DataTable.vue';
import DataTableActions from '@/components/DataTableActions.vue';
import Money from '@/components/Money.vue';
import StatusBadge from '@/components/StatusBadge.vue';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { index as indexRoute } from '@/routes/admin/dropship';
import { show } from '@/routes/admin/orders';

type Order = {
    id: string;
    no_order: string;
    nama_pembeli: string;
    total: number;
    status: string;
    created_at: string;
    items_count: number;
    user: { id: string; name: string } | null;
    dropshipper: {
        id: string;
        end_customer_name: string;
        end_customer_whatsapp: string | null;
        end_customer_address: string | null;
    } | null;
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
    filters: { from?: string; to?: string; status?: string };
    statusOptions: Record<string, string>;
};

const props = defineProps<Props>();

const columns: DataTableColumn[] = [
    {
        key: 'created_at',
        header: 'Tanggal',
        cellClass: 'text-muted-foreground whitespace-nowrap',
    },
    { key: 'no_order', header: 'No. Order', cellClass: 'font-medium' },
    { key: 'dropshipper', header: 'Dropshipper' },
    { key: 'end_customer', header: 'End-Customer' },
    {
        key: 'items_count',
        header: 'Item',
        cellClass: 'text-right tabular-nums',
    },
    { key: 'total', header: 'Total', cellClass: 'text-right tabular-nums' },
    { key: 'status', header: 'Status' },
    { key: 'aksi', header: 'Aksi', srOnly: true, cellClass: 'text-right' },
];

const allStatuses = '__all_statuses__';
const from = ref(props.filters.from ?? '');
const to = ref(props.filters.to ?? '');
const status = ref(props.filters.status ?? allStatuses);

const hasActiveFilters = computed(
    () => from.value !== '' || to.value !== '' || status.value !== allStatuses,
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
                status: status.value === allStatuses ? undefined : status.value,
            },
            {
                preserveState: true,
                replace: true,
            },
        );
    }, 350);
}

function resetFilters() {
    from.value = '';
    to.value = '';
    status.value = allStatuses;
    applyFilters();
}

watch([from, to, status], applyFilters);

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
    <Head title="Dropship" />

    <div class="flex flex-col gap-4 p-4 md:p-6">
        <div>
            <h1 class="text-xl font-semibold tracking-tight">Order Dropship</h1>
            <p class="text-sm text-muted-foreground">
                Pesanan yang dikirim langsung ke end-customer
            </p>
        </div>

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
            empty-title="Tidak ada order dropship"
            empty-description="Order dengan is_dropship akan tampil di sini."
        >
            <template #cell-created_at="{ row }">
                {{
                    new Date(row.created_at).toLocaleDateString('id-ID', {
                        timeZone: 'Asia/Jakarta',
                    })
                }}
            </template>
            <template #cell-dropshipper="{ row }">
                {{ row.user?.name ?? row.nama_pembeli }}
            </template>
            <template #cell-end_customer="{ row }">
                <template v-if="row.dropshipper">
                    <p>{{ row.dropshipper.end_customer_name }}</p>
                    <p class="text-xs text-muted-foreground tabular-nums">
                        {{ row.dropshipper.end_customer_whatsapp }}
                    </p>
                </template>
                <span v-else class="text-muted-foreground"
                    >Data belum diisi</span
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

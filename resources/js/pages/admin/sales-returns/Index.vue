<script setup lang="ts">
defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Beranda', href: '/admin/dashboard' },
            { title: 'Retur Penjualan', href: '/admin/sales-returns' },
        ],
    },
});

import { Form, Head, router, useHttp } from '@inertiajs/vue3';
import { Plus, RotateCcw, Search, X } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import SalesReturnController from '@/actions/App/Http/Controllers/Admin/SalesReturnController';
import { orderOptions } from '@/actions/App/Http/Controllers/Admin/SalesReturnController';
import DataTable from '@/components/DataTable.vue';
import type { DataTableColumn } from '@/components/DataTable.vue';
import Money from '@/components/Money.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { formatDateID, todayWIB } from '@/lib/date';
import { index as indexRoute } from '@/routes/admin/sales-returns';

type SalesReturnRow = {
    id: string;
    order: { id: string; no_order: string; nama_pembeli: string } | null;
    return_date: string;
    total_refund: number;
    notes: string | null;
    user: { id: string; name: string } | null;
    items: Array<{ id: string; qty: number; price_refund: number }>;
};

type OrderOption = {
    id: string;
    no_order: string;
    nama_pembeli: string;
    created_at: string;
};

type ReturnableItem = {
    id: string;
    book_id: string;
    book_edition_id: string | null;
    judul: string;
    edition_snapshot: string | null;
    ordered_qty: number;
    returnable_qty: number;
    price_refund: number;
};

type OrderDetail = {
    id: string;
    no_order: string;
    nama_pembeli: string;
    items: ReturnableItem[];
};

const props = defineProps<{
    returns: {
        data: SalesReturnRow[];
        current_page: number;
        last_page: number;
        total: number;
        per_page: number;
        links: Array<{ url: string | null; label: string; active: boolean }>;
    };
    filters: { search?: string };
}>();

const columns: DataTableColumn[] = [
    {
        key: 'return_date',
        header: 'Tanggal',
        cellClass: 'font-medium whitespace-nowrap',
    },
    {
        key: 'order',
        header: 'Order',
        cellClass: 'font-medium whitespace-nowrap',
    },
    { key: 'pembeli', header: 'Pembeli' },
    { key: 'item', header: 'Item', cellClass: 'text-right tabular-nums' },
    {
        key: 'total_refund',
        header: 'Total Refund',
        cellClass: 'text-right font-medium tabular-nums',
    },
    { key: 'user', header: 'Petugas' },
    {
        key: 'notes',
        header: 'Keterangan',
        cellClass: 'max-w-48 truncate text-muted-foreground',
    },
];

const search = ref(props.filters.search ?? '');
const createOpen = ref(false);

const orderSearch = ref('');
const orderListOpen = ref(false);
const availableOrders = ref<OrderOption[]>([]);
const orderSearchRequest = useHttp({ search: '' });
let orderSearchTimer: ReturnType<typeof setTimeout> | undefined;

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

const selectedOrder = ref<OrderDetail | null>(null);
const orderLoading = ref(false);
const orderError = ref('');
const returnRows = ref<
    Array<{
        item: ReturnableItem;
        qty: number;
        condition: string;
        reason: string;
    }>
>([]);

function onOrderBlur() {
    setTimeout(() => {
        orderListOpen.value = false;
    }, 150);
}

function searchOrders() {
    orderListOpen.value = true;
    clearTimeout(orderSearchTimer);

    if (!orderSearch.value.trim()) {
        return;
    }

    orderSearchTimer = setTimeout(() => {
        orderSearchRequest.search = orderSearch.value.trim();
        orderSearchRequest.get(orderOptions().url, {
            onSuccess: (data) => {
                availableOrders.value = data as OrderOption[];
            },
        });
    }, 250);
}

async function selectOrder(option: OrderOption) {
    orderSearch.value = option.no_order;
    orderListOpen.value = false;
    orderLoading.value = true;
    orderError.value = '';
    selectedOrder.value = null;

    try {
        const response = await fetch(
            SalesReturnController.orderDetail(option.id).url,
        );

        if (!response.ok) {
            throw new Error('Order tidak dapat diretur.');
        }

        const detail = (await response.json()) as OrderDetail;
        selectedOrder.value = detail;
        returnRows.value = detail.items.map((item) => ({
            item,
            qty: item.returnable_qty,
            condition: 'baik',
            reason: '',
        }));
    } catch (error) {
        orderError.value =
            error instanceof Error
                ? error.message
                : 'Gagal memuat detail order.';
    } finally {
        orderLoading.value = false;
    }
}

function openCreate() {
    createOpen.value = true;
    orderSearch.value = '';
    selectedOrder.value = null;
    orderError.value = '';
    returnRows.value = [];
}

function clampQty(row: (typeof returnRows.value)[number]) {
    const max = row.item.returnable_qty;
    row.qty = Math.min(Math.max(Number(row.qty) || 1, 1), max);
}

const canSubmit = computed(
    () =>
        returnRows.value.length > 0 &&
        returnRows.value.every(
            (row) =>
                Number.isInteger(row.qty) &&
                row.qty >= 1 &&
                row.qty <= row.item.returnable_qty,
        ),
);

const totalRefund = computed(() =>
    returnRows.value.reduce(
        (sum, row) => sum + row.qty * row.item.price_refund,
        0,
    ),
);
</script>

<template>
    <Head title="Retur Penjualan" />

    <div class="flex flex-col gap-4 p-4 md:p-6">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div>
                <h1 class="text-xl font-semibold tracking-tight">
                    Retur Penjualan
                </h1>
                <p class="text-sm text-muted-foreground">
                    Barang yang dikembalikan oleh pembeli
                </p>
            </div>
            <Button @click="openCreate">
                <Plus class="size-4" />
                Catat Retur
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
                    class="h-11 w-full rounded-none border-0 bg-transparent pl-9 shadow-none focus-visible:ring-0 md:h-9 md:w-56"
                    placeholder="Cari no. order..."
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
            empty-description="Catat retur penjualan pertama saat pembeli mengembalikan barang."
        >
            <template #cell-return_date="{ row }">{{
                formatDateID(row.return_date)
            }}</template>
            <template #cell-order="{ row }">{{
                row.order?.no_order ?? '—'
            }}</template>
            <template #cell-pembeli="{ row }">{{
                row.order?.nama_pembeli ?? '—'
            }}</template>
            <template #cell-item="{ row }">
                {{ row.items.reduce((sum, item) => sum + item.qty, 0) }}
            </template>
            <template #cell-total_refund="{ row }">
                <Money :value="row.total_refund" />
            </template>
            <template #cell-user="{ row }">{{
                row.user?.name ?? '—'
            }}</template>
            <template #cell-aksi="{ row }">
                <a
                    :href="SalesReturnController.invoice(row.id).url"
                    target="_blank"
                    rel="noopener"
                    class="inline-flex h-8 w-8 items-center justify-center rounded-md text-muted-foreground transition-colors hover:bg-accent hover:text-foreground"
                    title="Cetak nota retur"
                    aria-label="Cetak nota retur"
                >
                    <Printer class="size-4" />
                </a>
            </template>
        </DataTable>

        <!-- Dialog: Catat Retur -->
        <Dialog v-model:open="createOpen">
            <DialogContent class="sm:max-w-2xl">
                <DialogHeader>
                    <DialogTitle>Catat Retur Penjualan</DialogTitle>
                    <DialogDescription>
                        Pilih order, lalu tentukan qty yang dikembalikan per
                        item.
                    </DialogDescription>
                </DialogHeader>

                <div class="grid gap-2">
                    <Label for="order_search">Cari Order (no. order)</Label>
                    <div class="relative">
                        <Input
                            id="order_search"
                            v-model="orderSearch"
                            placeholder="SF-20260809..."
                            @focus="searchOrders"
                            @input="searchOrders"
                            @blur="onOrderBlur"
                        />
                        <ul
                            v-if="orderListOpen"
                            class="absolute z-10 mt-1 max-h-52 w-full overflow-auto rounded-md border bg-popover shadow-md"
                        >
                            <li
                                v-for="option in availableOrders"
                                :key="option.id"
                                class="cursor-pointer px-3 py-2 text-sm hover:bg-accent"
                                @mousedown.prevent="selectOrder(option)"
                            >
                                <span class="font-mono text-xs">
                                    {{ option.no_order }}
                                </span>
                                — {{ option.nama_pembeli }}
                                <span class="text-xs text-muted-foreground">
                                    ({{ option.created_at }})
                                </span>
                            </li>
                            <li
                                v-if="!availableOrders.length"
                                class="px-3 py-2 text-sm text-muted-foreground"
                            >
                                Tidak ditemukan / ketik untuk mencari
                            </li>
                        </ul>
                    </div>
                </div>

                <div
                    v-if="orderLoading"
                    class="py-6 text-center text-sm text-muted-foreground"
                >
                    Memuat detail order...
                </div>

                <div
                    v-if="orderError"
                    class="rounded-md border border-destructive/40 bg-destructive/5 px-3 py-2 text-sm text-destructive"
                >
                    {{ orderError }}
                </div>

                <Form
                    v-if="selectedOrder"
                    v-bind="SalesReturnController.store.form()"
                    class="grid gap-4"
                    v-slot="{ errors, processing }"
                    @success="createOpen = false"
                >
                    <input
                        type="hidden"
                        name="order_id"
                        :value="String(selectedOrder.id)"
                    />
                    <input
                        type="hidden"
                        name="return_date"
                        :value="todayWIB()"
                    />

                    <div class="overflow-x-auto rounded-lg border">
                        <div class="border-b px-3 py-2 text-sm">
                            <span class="font-mono font-medium">
                                {{ selectedOrder.no_order }}
                            </span>
                            — {{ selectedOrder.nama_pembeli }}
                        </div>
                        <table class="w-full text-sm">
                            <thead>
                                <tr
                                    class="border-b text-left text-xs text-muted-foreground"
                                >
                                    <th class="px-3 py-2 font-medium">Buku</th>
                                    <th class="px-3 py-2 font-medium">Harga</th>
                                    <th class="px-3 py-2 font-medium">Qty</th>
                                    <th class="px-3 py-2 font-medium">
                                        Kondisi
                                    </th>
                                    <th class="px-3 py-2 font-medium">
                                        Alasan
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr
                                    v-for="(row, i) in returnRows"
                                    :key="row.item.id"
                                    class="border-b last:border-0"
                                >
                                    <td class="px-3 py-2">
                                        <p class="font-medium">
                                            {{ row.item.judul }}
                                        </p>
                                        <p
                                            v-if="row.item.edition_snapshot"
                                            class="text-xs text-muted-foreground"
                                        >
                                            {{ row.item.edition_snapshot }}
                                        </p>
                                    </td>
                                    <td class="px-3 py-2 whitespace-nowrap">
                                        <Money :value="row.item.price_refund" />
                                    </td>
                                    <td class="px-3 py-2">
                                        <Input
                                            type="number"
                                            min="1"
                                            class="h-8 w-20"
                                            :max="row.item.returnable_qty"
                                            v-model.number="row.qty"
                                            @blur="clampQty(row)"
                                        />
                                        <p
                                            class="text-xs text-muted-foreground"
                                        >
                                            max
                                            {{ row.item.returnable_qty }}
                                        </p>
                                        <input
                                            type="hidden"
                                            :name="`items[${i}][order_item_id]`"
                                            :value="row.item.id"
                                        />
                                        <input
                                            type="hidden"
                                            :name="`items[${i}][qty]`"
                                            :value="row.qty"
                                        />
                                        <input
                                            type="hidden"
                                            :name="`items[${i}][condition]`"
                                            :value="row.condition"
                                        />
                                        <input
                                            type="hidden"
                                            :name="`items[${i}][reason]`"
                                            :value="row.reason"
                                        />
                                    </td>
                                    <td class="px-3 py-2">
                                        <Select
                                            v-model="row.condition"
                                            class="w-44"
                                        >
                                            <SelectTrigger class="h-8">
                                                <SelectValue />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectItem value="baik">
                                                    Baik (bisa dijual)
                                                </SelectItem>
                                                <SelectItem value="rusak">
                                                    Rusak (ke defect)
                                                </SelectItem>
                                            </SelectContent>
                                        </Select>
                                    </td>
                                    <td class="px-3 py-2">
                                        <Input
                                            v-model="row.reason"
                                            class="h-8 w-40"
                                            placeholder="Alasan retur"
                                        />
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div class="grid gap-2">
                        <Label for="notes">Keterangan</Label>
                        <Textarea
                            id="notes"
                            name="notes"
                            rows="2"
                            placeholder="Alasan retur / catatan internal (opsional)"
                        />
                    </div>

                    <div v-if="errors.items" class="text-sm text-destructive">
                        {{ errors.items }}
                    </div>

                    <div class="flex items-center justify-between">
                        <p class="text-sm">
                            Total Refund:
                            <Money :value="totalRefund" class="font-semibold" />
                        </p>
                        <DialogFooter>
                            <Button
                                type="submit"
                                :disabled="processing || !canSubmit"
                            >
                                <RotateCcw class="size-4" />
                                {{
                                    processing ? 'Menyimpan...' : 'Catat Retur'
                                }}
                            </Button>
                        </DialogFooter>
                    </div>
                </Form>
            </DialogContent>
        </Dialog>
    </div>
</template>

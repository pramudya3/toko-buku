<script setup lang="ts">
defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Beranda', href: '/admin/dashboard' },
            { title: 'Piutang', href: '/admin/receivables' },
        ],
    },
});

import { Form, Head, router, useHttp } from '@inertiajs/vue3';
import { HandCoins, Plus, Search, Trash2, X } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import { customerOptions } from '@/actions/App/Http/Controllers/Admin/OrderController';
import ReceivableController from '@/actions/App/Http/Controllers/Admin/ReceivableController';
import ConfirmDeleteDialog from '@/components/ConfirmDeleteDialog.vue';
import CurrencyInput from '@/components/CurrencyInput.vue';
import DataTable from '@/components/DataTable.vue';
import type { DataTableColumn } from '@/components/DataTable.vue';
import Money from '@/components/Money.vue';
import StatusBadge from '@/components/StatusBadge.vue';
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
import { todayWIB } from '@/lib/date';
import { index as indexRoute } from '@/routes/admin/receivables';

type Customer = {
    id: string;
    name: string;
    whatsapp_number: string | null;
};

type Receivable = {
    id: string;
    amount: number;
    paid_amount: number;
    due_date: string | null;
    notes: string | null;
    created_at: string;
    payments_count: number;
    customer: {
        id: string;
        name: string;
        whatsapp_number: string | null;
    } | null;
    order: { id: string; no_order: string } | null;
};

const props = defineProps<{
    receivables: {
        data: Receivable[];
        current_page: number;
        last_page: number;
        total: number;
        per_page: number;
        links: Array<{ url: string | null; label: string; active: boolean }>;
    };
    customers: Customer[];
    filters: { search?: string; status?: string };
}>();

const columns: DataTableColumn[] = [
    { key: 'pelanggan', header: 'Pelanggan' },
    { key: 'order', header: 'Order', cellClass: 'font-mono text-xs' },
    { key: 'due_date', header: 'Jatuh Tempo' },
    { key: 'amount', header: 'Total', cellClass: 'text-right tabular-nums' },
    {
        key: 'paid_amount',
        header: 'Terbayar',
        cellClass: 'text-right tabular-nums',
    },
    {
        key: 'sisa',
        header: 'Sisa',
        cellClass: 'text-right font-medium tabular-nums',
    },
    { key: 'status', header: 'Status' },
    { key: 'aksi', header: 'Aksi', srOnly: true, cellClass: 'text-right' },
];

const search = ref(props.filters.search ?? '');
const status = ref(props.filters.status ?? '');
const createOpen = ref(false);
const payReceivable = ref<Receivable | null>(null);
const deletingReceivable = ref<Receivable | null>(null);

const customerSearch = ref('');
const customerListOpen = ref(false);
const availableCustomers = ref<Customer[]>(props.customers);
const customerSearchRequest = useHttp({ search: '' });
let customerSearchTimer: ReturnType<typeof setTimeout> | undefined;

const hasActiveFilters = computed(
    () => search.value !== '' || status.value !== '',
);

let filterTimer: ReturnType<typeof setTimeout> | undefined;

function applyFilters() {
    clearTimeout(filterTimer);
    filterTimer = setTimeout(() => {
        router.get(
            indexRoute().url,
            {
                search: search.value || undefined,
                status: status.value === 'all' ? undefined : status.value,
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
    status.value = '';
    applyFilters();
}

watch([search, status], applyFilters);

function searchCustomers() {
    customerListOpen.value = true;
    clearTimeout(customerSearchTimer);

    if (!customerSearch.value.trim()) {
        availableCustomers.value = props.customers;

        return;
    }

    customerSearchTimer = setTimeout(() => {
        customerSearchRequest.search = customerSearch.value.trim();
        customerSearchRequest.get(customerOptions().url, {
            onSuccess: (data) => {
                availableCustomers.value = data as Customer[];
            },
        });
    }, 250);
}

function selectCustomer(customer: Customer) {
    customerSearch.value = customer.name;
    customerListOpen.value = false;
}

function onCustomerBlur() {
    setTimeout(() => {
        customerListOpen.value = false;
    }, 150);
}

function remaining(receivable: Receivable): number {
    return Math.max(0, receivable.amount - receivable.paid_amount);
}

function executeDelete() {
    if (!deletingReceivable.value) {
        return;
    }

    router.delete(
        ReceivableController.destroy(deletingReceivable.value.id).url,
        { preserveScroll: true },
    );
    deletingReceivable.value = null;
}
</script>

<template>
    <Head title="Piutang" />

    <div class="mx-auto flex w-full max-w-7xl flex-col gap-3 p-3 md:p-4">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div>
                <h1 class="text-xl font-semibold tracking-tight">Piutang</h1>
                <p class="text-sm text-muted-foreground">
                    Tagihan pelanggan yang belum dibayar penuh
                </p>
            </div>
            <Button @click="createOpen = true">
                <Plus class="size-4" />
                Catat Piutang
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
                    placeholder="Cari pelanggan..."
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
                        <SelectItem value="all">Semua status</SelectItem>
                        <SelectItem value="belum_lunas">Belum Lunas</SelectItem>
                        <SelectItem value="lunas">Lunas</SelectItem>
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
            :data="receivables.data"
            :columns="columns"
            :paginator="receivables"
            empty-title="Tidak ada piutang"
            empty-description="Catat piutang pertama untuk pelanggan yang belum membayar penuh."
        >
            <template #cell-pelanggan="{ row }">
                <p class="font-medium">{{ row.customer?.name ?? '—' }}</p>
                <p class="text-xs text-muted-foreground">
                    {{ row.customer?.whatsapp_number ?? '' }}
                </p>
            </template>
            <template #cell-order="{ row }">{{
                row.order?.no_order ?? '—'
            }}</template>
            <template #cell-due_date="{ row }">
                {{
                    row.due_date
                        ? new Date(
                              row.due_date + 'T00:00:00',
                          ).toLocaleDateString('id-ID', {
                              day: '2-digit',
                              month: 'short',
                              year: 'numeric',
                          })
                        : '—'
                }}
            </template>
            <template #cell-amount="{ row }">
                <Money :value="row.amount" />
            </template>
            <template #cell-paid_amount="{ row }">
                <Money :value="row.paid_amount" />
            </template>
            <template #cell-sisa="{ row }">
                <Money :value="remaining(row)" />
            </template>
            <template #cell-status="{ row }">
                <StatusBadge
                    :variant="remaining(row) === 0 ? 'success' : 'warning'"
                    :label="remaining(row) === 0 ? 'Lunas' : 'Belum Lunas'"
                />
            </template>
            <template #cell-aksi="{ row }">
                <div class="flex items-center justify-end gap-1">
                    <Button
                        v-if="remaining(row) > 0"
                        type="button"
                        variant="outline"
                        size="sm"
                        @click="payReceivable = row"
                    >
                        <HandCoins class="size-3.5" />
                        Bayar
                    </Button>
                    <Button
                        v-if="!row.payments_count"
                        type="button"
                        variant="ghost"
                        size="icon-sm"
                        class="size-7 text-muted-foreground hover:text-destructive"
                        title="Hapus piutang"
                        @click="deletingReceivable = row"
                    >
                        <Trash2 class="size-3.5" />
                    </Button>
                </div>
            </template>
        </DataTable>

        <!-- Dialog: Catat Piutang -->
        <Dialog v-model:open="createOpen">
            <DialogContent class="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>Catat Piutang</DialogTitle>
                    <DialogDescription>
                        Tagihan untuk pelanggan yang tidak membayar penuh
                        sekaligus.
                    </DialogDescription>
                </DialogHeader>
                <Form
                    v-bind="ReceivableController.store.form()"
                    class="grid gap-4"
                    v-slot="{ errors, processing }"
                    @success="createOpen = false"
                >
                    <div class="grid gap-2">
                        <Label for="customer_search">Pelanggan *</Label>
                        <div class="relative">
                            <Input
                                id="customer_search"
                                v-model="customerSearch"
                                placeholder="Ketik nama / no. WA..."
                                @focus="searchCustomers"
                                @input="searchCustomers"
                                @blur="onCustomerBlur"
                            />
                            <ul
                                v-if="customerListOpen"
                                class="absolute z-10 mt-1 max-h-52 w-full overflow-auto rounded-md border bg-popover shadow-md"
                            >
                                <li
                                    v-for="customer in availableCustomers"
                                    :key="customer.id"
                                    class="cursor-pointer px-3 py-2 text-sm hover:bg-accent"
                                    @mousedown.prevent="
                                        selectCustomer(customer)
                                    "
                                >
                                    {{ customer.name }}
                                    <span class="text-xs text-muted-foreground">
                                        {{ customer.whatsapp_number }}
                                    </span>
                                </li>
                                <li
                                    v-if="!availableCustomers.length"
                                    class="px-3 py-2 text-sm text-muted-foreground"
                                >
                                    Tidak ditemukan
                                </li>
                            </ul>
                        </div>
                        <input
                            type="hidden"
                            name="customer_id"
                            :value="
                                availableCustomers.find(
                                    (c) => c.name === customerSearch,
                                )?.id ?? ''
                            "
                        />
                        <span
                            v-if="errors.customer_id"
                            class="text-sm text-destructive"
                            >{{ errors.customer_id }}</span
                        >
                    </div>
                    <div class="grid gap-2">
                        <Label for="amount">Jumlah Piutang (Rp) *</Label>
                        <CurrencyInput
                            id="amount"
                            name="amount"
                            min="1"
                            required
                            placeholder="100.000"
                        />
                        <span
                            v-if="errors.amount"
                            class="text-sm text-destructive"
                            >{{ errors.amount }}</span
                        >
                    </div>
                    <div class="grid gap-2">
                        <Label for="due_date">Jatuh Tempo (opsional)</Label>
                        <Input id="due_date" name="due_date" type="date" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="notes">Keterangan (opsional)</Label>
                        <Input
                            id="notes"
                            name="notes"
                            placeholder="Contoh: DP 50rb, sisa bulan depan"
                        />
                    </div>
                    <DialogFooter>
                        <Button type="submit" :disabled="processing">
                            {{ processing ? 'Menyimpan...' : 'Simpan Piutang' }}
                        </Button>
                    </DialogFooter>
                </Form>
            </DialogContent>
        </Dialog>

        <!-- Dialog: Bayar Piutang -->
        <Dialog
            :open="payReceivable !== null"
            @update:open="(v) => !v && (payReceivable = null)"
        >
            <DialogContent class="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>
                        Bayar Piutang — {{ payReceivable?.customer?.name }}
                    </DialogTitle>
                    <DialogDescription>
                        Sisa piutang:
                        <Money
                            :value="
                                payReceivable ? remaining(payReceivable) : 0
                            "
                        />
                    </DialogDescription>
                </DialogHeader>
                <Form
                    v-if="payReceivable"
                    v-bind="ReceivableController.pay.form(payReceivable.id)"
                    class="grid gap-4"
                    v-slot="{ errors, processing }"
                    @success="createOpen = false"
                >
                    <div class="grid gap-2">
                        <Label for="pay_amount">Jumlah Bayar (Rp) *</Label>
                        <CurrencyInput
                            id="pay_amount"
                            name="amount"
                            min="1"
                            required
                            placeholder="50.000"
                        />
                        <span
                            v-if="errors.amount"
                            class="text-sm text-destructive"
                            >{{ errors.amount }}</span
                        >
                    </div>
                    <div class="grid gap-2">
                        <Label for="paid_at">Tanggal Bayar *</Label>
                        <Input
                            id="paid_at"
                            name="paid_at"
                            type="date"
                            :default-value="todayWIB()"
                            required
                        />
                    </div>
                    <div class="grid gap-2">
                        <Label for="metode">Metode *</Label>
                        <Select name="metode" default-value="transfer">
                            <SelectTrigger id="metode">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="transfer">
                                    Transfer
                                </SelectItem>
                                <SelectItem value="cod">COD</SelectItem>
                                <SelectItem value="cash">Tunai</SelectItem>
                            </SelectContent>
                        </Select>
                        <span
                            v-if="errors.metode"
                            class="text-sm text-destructive"
                            >{{ errors.metode }}</span
                        >
                    </div>
                    <div class="grid gap-2">
                        <Label for="pay_notes">Keterangan (opsional)</Label>
                        <Input
                            id="pay_notes"
                            name="notes"
                            placeholder="Contoh: cicilan ke-2"
                        />
                    </div>
                    <DialogFooter>
                        <Button type="submit" :disabled="processing">
                            {{
                                processing ? 'Menyimpan...' : 'Catat Pembayaran'
                            }}
                        </Button>
                    </DialogFooter>
                </Form>
            </DialogContent>
        </Dialog>

        <!-- Konfirmasi hapus -->
        <ConfirmDeleteDialog
            :open="deletingReceivable !== null"
            @update:open="
                (open) => {
                    if (!open) deletingReceivable = null;
                }
            "
            title="Hapus Piutang?"
            :description="
                deletingReceivable
                    ? deletingReceivable.payments_count > 0
                        ? 'Piutang dengan riwayat pembayaran tidak dapat dihapus.'
                        : 'Piutang ini akan dihapus permanen.'
                    : ''
            "
            @confirm="executeDelete"
        />
    </div>
</template>

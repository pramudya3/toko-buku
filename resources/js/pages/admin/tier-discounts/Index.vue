<script setup lang="ts">
defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Beranda', href: '/admin/dashboard' },
            { title: 'Tier Discount', href: '/admin/tier-discounts' },
        ],
    },
});

import { Form, Head, router } from '@inertiajs/vue3';
import { Plus, Search, Upload, X } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import TierDiscountController from '@/actions/App/Http/Controllers/Admin/TierDiscountController';
import ConfirmDeleteDialog from '@/components/ConfirmDeleteDialog.vue';
import DataTable from '@/components/DataTable.vue';
import type { DataTableColumn } from '@/components/DataTable.vue';
import DataTableActions from '@/components/DataTableActions.vue';
import ImportCsvDialog from '@/components/ImportCsvDialog.vue';
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

type TierDiscount = {
    id: string;
    tier: string;
    min_qty: number;
    discount_percent: number;
};

type Props = {
    tierDiscounts: {
        data: TierDiscount[];
        current_page: number;
        last_page: number;
        total: number;
        per_page: number;
        links: Array<{ url: string | null; label: string; active: boolean }>;
    };
    tierOptions: Record<string, string>;
    filters: { search?: string; tier?: string };
};

const props = defineProps<Props>();

const columns: DataTableColumn[] = [
    { key: 'tier', header: 'Tier' },
    { key: 'min_qty', header: 'Min Qty', cellClass: 'text-right tabular-nums' },
    {
        key: 'discount_percent',
        header: 'Diskon',
        cellClass: 'text-right tabular-nums',
    },
    { key: 'aksi', header: 'Aksi', srOnly: true, cellClass: 'text-right' },
];

const allTiersFilter = '__all_tiers__';

const search = ref(props.filters.search ?? '');
const filterTier = ref(props.filters.tier ?? allTiersFilter);

// Snapshot awal (nilai server saat load) untuk tombol Reset.
const initialSearch = props.filters.search ?? '';
const initialTier = props.filters.tier ?? allTiersFilter;

const hasActiveFilters = computed(
    () => search.value !== initialSearch || filterTier.value !== initialTier,
);

let filterTimer: ReturnType<typeof setTimeout> | undefined;

function applyFilters() {
    clearTimeout(filterTimer);
    filterTimer = setTimeout(() => {
        router.get(
            window.location.pathname,
            {
                search: search.value || undefined,
                tier:
                    filterTier.value === allTiersFilter
                        ? undefined
                        : filterTier.value,
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
    filterTier.value = initialTier;
    applyFilters();
}

watch([search, filterTier], applyFilters);

const formDialogOpen = ref(false);
const deleteDialogOpen = ref(false);
const importOpen = ref(false);
const editingDiscount = ref<TierDiscount | null>(null);
const deletingDiscount = ref<TierDiscount | null>(null);

function getTierLabel(tier: string): string {
    return props.tierOptions[tier] ?? tier;
}

const tierVariant: Record<string, 'success' | 'warning' | 'info' | 'neutral'> =
    {
        reguler: 'neutral',
        bazaf: 'info',
        guru: 'success',
        reseller: 'warning',
    };

function openCreate() {
    editingDiscount.value = null;
    formDialogOpen.value = true;
}

function openEdit(discount: TierDiscount) {
    editingDiscount.value = discount;
    formDialogOpen.value = true;
}

function openDelete(discount: TierDiscount) {
    deletingDiscount.value = discount;
    deleteDialogOpen.value = true;
}

function confirmDelete() {
    if (deletingDiscount.value) {
        router.delete(
            TierDiscountController.destroy(deletingDiscount.value.id).url,
        );
        deleteDialogOpen.value = false;
        deletingDiscount.value = null;
    }
}
</script>

<template>
    <Head title="Tier Discount" />

    <div class="flex flex-col gap-4 p-4 md:p-6">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div>
                <h1 class="text-xl font-semibold tracking-tight">
                    Tier Discount
                </h1>
                <p class="text-sm text-muted-foreground">
                    Atur diskon berdasarkan tier pelanggan
                </p>
            </div>
            <div class="flex items-center gap-2">
                <Button variant="outline" @click="importOpen = true">
                    <Upload class="size-4" />
                    Import CSV
                </Button>
                <Button @click="openCreate">
                    <Plus class="size-4" />
                    Tambah Rule
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
                    class="h-11 w-full rounded-none border-0 bg-transparent pl-9 shadow-none focus-visible:border-transparent focus-visible:ring-0 md:h-9 md:w-56"
                    placeholder="Cari tier..."
                />
            </div>

            <div class="md:flex md:items-center">
                <p
                    class="px-3 pt-2 text-xs font-medium text-muted-foreground md:hidden"
                >
                    Tier
                </p>
                <Select v-model="filterTier">
                    <SelectTrigger
                        class="h-11 w-full rounded-none border-0 bg-transparent px-3 shadow-none focus-visible:border-transparent focus-visible:ring-0 md:h-9 md:w-40"
                    >
                        <SelectValue placeholder="Semua tier" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem :value="allTiersFilter"
                            >Semua tier</SelectItem
                        >
                        <SelectItem
                            v-for="(label, value) in tierOptions"
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
            :data="tierDiscounts.data"
            :columns="columns"
            :paginator="tierDiscounts"
            empty-title="Belum ada tier discount"
            empty-description="Klik 'Tambah Rule' untuk menambahkan diskon berdasarkan tier."
        >
            <template #cell-tier="{ row }">
                <StatusBadge
                    :variant="tierVariant[row.tier] ?? 'neutral'"
                    :label="getTierLabel(row.tier)"
                />
            </template>
            <template #cell-discount_percent="{ row }">
                {{ row.discount_percent }}%
            </template>
            <template #cell-aksi="{ row }">
                <DataTableActions
                    :actions="[
                        {
                            label: 'Edit',
                            onClick: () => openEdit(row),
                        },
                        {
                            label: 'Hapus',
                            variant: 'destructive',
                            onClick: () => openDelete(row),
                        },
                    ]"
                />
            </template>
        </DataTable>

        <!-- Form Dialog -->
        <Dialog v-model:open="formDialogOpen">
            <DialogContent class="sm:max-w-sm">
                <DialogHeader>
                    <DialogTitle>{{
                        editingDiscount ? 'Edit Rule' : 'Tambah Rule'
                    }}</DialogTitle>
                    <DialogDescription>
                        Atur diskon berdasarkan tier dan range jumlah pembelian.
                    </DialogDescription>
                </DialogHeader>

                <Form
                    v-if="editingDiscount"
                    :action="
                        TierDiscountController.update(editingDiscount.id).url
                    "
                    method="post"
                    class="grid gap-4"
                    @success="formDialogOpen = false"
                >
                    <input type="hidden" name="_method" value="put" />
                    <input
                        type="hidden"
                        name="tier"
                        :value="editingDiscount.tier"
                    />

                    <div class="grid gap-2">
                        <Label>Tier</Label>
                        <div
                            class="flex h-9 items-center rounded-md border bg-muted px-3 text-sm"
                        >
                            {{ getTierLabel(editingDiscount.tier) }}
                        </div>
                    </div>

                    <div class="grid gap-2">
                        <Label for="edit_min_qty">Min Qty</Label>
                        <Input
                            id="edit_min_qty"
                            name="min_qty"
                            type="number"
                            min="1"
                            required
                            :default-value="editingDiscount.min_qty"
                        />
                    </div>

                    <div class="grid gap-2">
                        <Label for="edit_discount_percent"> Diskon (%) </Label>
                        <Input
                            id="edit_discount_percent"
                            name="discount_percent"
                            type="number"
                            min="0"
                            max="100"
                            required
                            :default-value="editingDiscount.discount_percent"
                        />
                    </div>

                    <DialogFooter>
                        <Button type="submit">Simpan</Button>
                    </DialogFooter>
                </Form>

                <Form
                    v-else
                    :action="TierDiscountController.store().url"
                    method="post"
                    class="grid gap-4"
                    @success="formDialogOpen = false"
                >
                    <div class="grid gap-2">
                        <Label for="tier">Tier</Label>
                        <select
                            id="tier"
                            name="tier"
                            required
                            class="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-xs transition-colors focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                        >
                            <option value="" disabled selected>
                                Pilih tier
                            </option>
                            <option
                                v-for="(label, value) in tierOptions"
                                :key="value"
                                :value="value"
                            >
                                {{ label }}
                            </option>
                        </select>
                    </div>

                    <div class="grid gap-2">
                        <Label for="min_qty">Min Qty</Label>
                        <Input
                            id="min_qty"
                            name="min_qty"
                            type="number"
                            min="1"
                            required
                        />
                    </div>

                    <div class="grid gap-2">
                        <Label for="discount_percent">Diskon (%)</Label>
                        <Input
                            id="discount_percent"
                            name="discount_percent"
                            type="number"
                            min="0"
                            max="100"
                            required
                        />
                    </div>

                    <DialogFooter>
                        <Button type="submit">Simpan</Button>
                    </DialogFooter>
                </Form>
            </DialogContent>
        </Dialog>

        <!-- Delete Confirmation -->
        <ConfirmDeleteDialog
            v-model:open="deleteDialogOpen"
            title="Hapus Rule?"
            :description="
                deletingDiscount
                    ? `${getTierLabel(deletingDiscount.tier)} — Min ${deletingDiscount.min_qty} → ${deletingDiscount.discount_percent}%`
                    : ''
            "
            @confirm="confirmDelete"
        />
    </div>

    <ImportCsvDialog
        v-model:open="importOpen"
        :action="TierDiscountController.importCsv.form()"
        template-type="tier-discounts"
        title="Import Tier Discount dari CSV"
        description="Format kolom: tier,min_qty,discount_percent"
        hint="Aturan global per (tier, min_qty)."
    />
</template>

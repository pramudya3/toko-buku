<script setup lang="ts">
defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Beranda', href: '/admin/dashboard' },
            { title: 'Voucher', href: '/admin/vouchers' },
        ],
    },
});

import { Head, Link, router } from '@inertiajs/vue3';
import { Plus, Search, X } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import { toast } from 'vue-sonner';
import VoucherController from '@/actions/App/Http/Controllers/Admin/VoucherController';
import ConfirmDeleteDialog from '@/components/ConfirmDeleteDialog.vue';
import DataTable from '@/components/DataTable.vue';
import type { DataTableColumn } from '@/components/DataTable.vue';
import DataTableActions from '@/components/DataTableActions.vue';
import StatusBadge from '@/components/StatusBadge.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { create, edit, index as indexRoute } from '@/routes/admin/vouchers';

type Voucher = {
    id: string;
    nama: string;
    kode: string | null;
    voucher_type: string;
    discount_scope: string;
    discount_percentage: number | null;
    discount_value: number | null;
    min_order_amount: number;
    max_uses: number | null;
    max_uses_per_user: number | null;
    start_date: string;
    end_date: string;
    is_active: boolean;
    usages_count: number;
};

type Props = {
    vouchers: {
        data: Voucher[];
        current_page: number;
        last_page: number;
        total: number;
        per_page: number;
        links: Array<{ url: string | null; label: string; active: boolean }>;
    };
    filters: { search?: string };
    typeOptions: Record<string, string>;
    scopeOptions: Record<string, string>;
};

const props = defineProps<Props>();

const columns: DataTableColumn[] = [
    { key: 'nama', header: 'Nama', cellClass: 'font-medium' },
    { key: 'kode', header: 'Kode' },
    { key: 'voucher_type', header: 'Tipe' },
    { key: 'nilai', header: 'Nilai', cellClass: 'text-right tabular-nums' },
    { key: 'discount_scope', header: 'Target' },
    {
        key: 'min_order_amount',
        header: 'Min. Belanja',
        cellClass: 'text-right tabular-nums',
    },
    { key: 'pemakaian', header: 'Pemakaian', cellClass: 'text-center' },
    { key: 'periode', header: 'Periode', cellClass: 'text-muted-foreground' },
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
            { search: search.value || undefined },
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

const typeVariant: Record<
    string,
    'success' | 'warning' | 'danger' | 'info' | 'neutral'
> = {
    percentage: 'info',
    fixed: 'warning',
};

// Voucher yang sudah dipakai di pesanan terkunci — tidak bisa diedit,
// dihapus, atau dinonaktifkan (backend juga memblokir aksi ini).
const isUsed = (voucher: Voucher) => voucher.usages_count > 0;

function editVoucher(voucher: Voucher) {
    if (isUsed(voucher)) {
        toast.error(
            "Voucher sudah dipakai di pesanan — tidak bisa diubah.",
        );

        return;
    }

    router.visit(edit(voucher.id).url);
}

function toggleVoucher(voucher: Voucher) {
    if (isUsed(voucher)) {
        toast.error(
            "Voucher sudah dipakai di pesanan — status tidak bisa diubah.",
        );

        return;
    }

    router.patch(VoucherController.toggle(voucher.id).url, {
        preserveScroll: true,
    });
}

const quotaLabel = (voucher: Voucher) => {
    const parts: string[] = [];

    if (voucher.max_uses !== null) {
        parts.push(`${voucher.usages_count}/${voucher.max_uses}`);
    } else {
        parts.push(`${voucher.usages_count}`);
    }

    if (voucher.max_uses_per_user !== null) {
        parts.push(`${voucher.max_uses_per_user}x/user`);
    }

    return parts.join(' · ');
};

function confirmDelete(voucher: Voucher) {
    if (isUsed(voucher)) {
        toast.error(
            "Voucher sudah dipakai di pesanan — tidak bisa dihapus.",
        );

        return;
    }

    deletingVoucher.value = voucher;
}

const deletingVoucher = ref<Voucher | null>(null);

function executeDelete() {
    if (!deletingVoucher.value) {
        return;
    }

    const voucher = deletingVoucher.value;

    deletingVoucher.value = null;
    router.delete(VoucherController.destroy(voucher.id).url, {
        preserveScroll: true,
    });
}
</script>

<template>
    <Head title="Voucher" />

    <div class="flex flex-col gap-4 p-4 md:p-6">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div>
                <h1 class="text-xl font-semibold tracking-tight">Voucher</h1>
                <p class="text-sm text-muted-foreground">
                    Voucher diskon yang bisa dipakai customer saat checkout
                </p>
            </div>
            <Button as-child>
                <Link :href="create()">
                    <Plus class="size-4" />
                    Buat Voucher
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
                    class="h-11 w-full rounded-none border-0 bg-transparent pl-9 shadow-none focus-visible:ring-0 md:h-9 md:w-56"
                    placeholder="Cari voucher..."
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
            :data="vouchers.data"
            :columns="columns"
            :paginator="vouchers"
            empty-title="Belum ada voucher"
            empty-description="Buat voucher pertama untuk menarik pembeli."
        >
            <template #cell-kode="{ row }">
                <code
                    class="rounded bg-muted px-1.5 py-0.5 text-xs font-medium"
                    >{{ row.kode ?? '-' }}</code
                >
            </template>
            <template #cell-voucher_type="{ row }">
                <StatusBadge
                    :variant="typeVariant[row.voucher_type] ?? 'neutral'"
                    :label="typeOptions[row.voucher_type] ?? row.voucher_type"
                />
            </template>
            <template #cell-nilai="{ row }">
                {{
                    row.voucher_type === 'percentage'
                        ? `${row.discount_percentage}%`
                        : `Rp ${row.discount_value?.toLocaleString('id-ID')}`
                }}
            </template>
            <template #cell-discount_scope="{ row }">
                <StatusBadge
                    :variant="
                        row.discount_scope === 'ongkir' ? 'info' : 'neutral'
                    "
                    :label="
                        scopeOptions[row.discount_scope] ?? row.discount_scope
                    "
                />
            </template>
            <template #cell-min_order_amount="{ row }">
                {{
                    row.min_order_amount > 0
                        ? `Rp ${row.min_order_amount.toLocaleString('id-ID')}`
                        : '-'
                }}
            </template>
            <template #cell-pemakaian="{ row }">
                {{ quotaLabel(row) }}
            </template>
            <template #cell-periode="{ row }">
                {{ row.start_date }} → {{ row.end_date }}
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
                            onClick: () => editVoucher(row),
                        },
                        {
                            label: row.is_active ? 'Nonaktifkan' : 'Aktifkan',
                            onClick: () => toggleVoucher(row),
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
            :open="!!deletingVoucher"
            @update:open="
                (open) => {
                    if (!open) deletingVoucher = null;
                }
            "
            title="Hapus Voucher?"
            :description="
                deletingVoucher
                    ? `Voucher '${deletingVoucher.nama}' akan dihapus.`
                    : ''
            "
            @confirm="executeDelete"
        />
    </div>
</template>

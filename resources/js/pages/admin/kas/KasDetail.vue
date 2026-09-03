<script setup lang="ts">
defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Beranda', href: '/admin/dashboard' },
            { title: 'Kas', href: '/admin/kas' },
            { title: 'Detail' },
        ],
    },
});

import { Head, router } from '@inertiajs/vue3';
import {
    ArrowDownCircle,
    ArrowUpCircle,
    Lock,
    LockOpen,
    Plus,
} from '@lucide/vue';
import { computed, ref } from 'vue';
import { toast } from 'vue-sonner';
import ConfirmDeleteDialog from '@/components/ConfirmDeleteDialog.vue';
import DataTable from '@/components/DataTable.vue';
import type { DataTableColumn } from '@/components/DataTable.vue';
import DataTableActions from '@/components/DataTableActions.vue';
import KasEntryDialog from '@/components/KasEntryDialog.vue';
import Money from '@/components/Money.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardHeader, CardTitle } from '@/components/ui/card';
import { todayWIB } from '@/lib/date';
import { destroy as destroyRoute } from '@/routes/admin/kas';
import { close as closeRoute, reopen as reopenRoute } from '@/routes/admin/kas';

type FlowRow = {
    id: string;
    entry_date: string;
    flow_type: string;
    description: string;
    kas_category_id?: string | null;
    kas_sub_category_id?: string | null;
    kas_category?: string | null;
    kas_sub_category?: string | null;
    amount: number;
};

const props = defineProps<{
    bulan: string;
    bulan_label: string;
    is_closed: boolean;
    kasCategories: Array<{
        id: string;
        nama: string;
        sub_categories?: Array<{ id: string; nama: string }>;
        subCategories?: Array<{ id: string; nama: string }>;
    }>;
    summary: { masuk: number; keluar: number };
    pencatatan: FlowRow[];
    pengeluaran: FlowRow[];
}>();

const dialogMode = ref<'in' | 'out' | null>(null);
const editingFlow = ref<FlowRow | null>(null);
const dialogOpen = computed(() => dialogMode.value !== null);

const columnsMasuk: DataTableColumn[] = [
    {
        key: 'entry_date',
        header: 'Tanggal',
        cellClass: 'whitespace-nowrap text-sm',
    },
    { key: 'description', header: 'Keterangan', cellClass: 'text-sm' },
    {
        key: 'amount',
        header: 'Nominal',
        cellClass: 'text-right tabular-nums text-sm font-medium',
    },
    { key: 'aksi', header: 'Aksi', srOnly: true, cellClass: 'text-right' },
];
const columnsKeluar: DataTableColumn[] = [
    {
        key: 'entry_date',
        header: 'Tanggal',
        cellClass: 'whitespace-nowrap text-sm',
    },
    { key: 'kategori', header: 'Kategori', cellClass: 'text-sm' },
    { key: 'sub_kategori', header: 'Subkategori', cellClass: 'text-sm' },
    { key: 'description', header: 'Keterangan', cellClass: 'text-sm' },
    {
        key: 'amount',
        header: 'Nominal',
        cellClass: 'text-right tabular-nums text-sm',
    },
    { key: 'aksi', header: 'Aksi', srOnly: true, cellClass: 'text-right' },
];

function formatDate(date: string): string {
    const [y, m, d] = date.split('-');

    return `${d}/${m}/${y}`;
}

// eslint-disable-next-line @typescript-eslint/no-unused-vars
function canEdit(_row: FlowRow): boolean {
    // OPSI A: Kas mandiri — semua entri bisa diedit sebelum close (tidak ada order_id)
    return !props.is_closed;
}
// eslint-disable-next-line @typescript-eslint/no-unused-vars
function canDelete(_row: FlowRow): boolean {
    return !props.is_closed;
}

function openCreate(mode: 'in' | 'out') {
    if (props.is_closed) {
        toast.error(
            'Periode telah ditutup — pencatatan tidak dapat ditambahkan.',
        );

        return;
    }

    editingFlow.value = null;
    dialogMode.value = mode;
}

function openEdit(row: FlowRow) {
    if (!canEdit(row)) {
        toast.error('Periode telah ditutup — pencatatan tidak dapat diubah.');

        return;
    }

    editingFlow.value = row;
    const isIn = row.flow_type === 'income';

    dialogMode.value = isIn ? 'in' : 'out';
}

const deletingFlow = ref<FlowRow | null>(null);

function confirmDelete(row: FlowRow) {
    if (!canDelete(row)) {
        toast.error('Periode telah ditutup — pencatatan tidak dapat dihapus.');

        return;
    }

    deletingFlow.value = row;
}

function executeDelete() {
    if (!deletingFlow.value) {
        return;
    }

    const id = deletingFlow.value.id;

    deletingFlow.value = null;
    router.delete(destroyRoute(id).url, {
        preserveScroll: true,
        onSuccess: () => toast.success('Pencatatan berhasil dihapus.'),
        onError: () => toast.error('Gagal menghapus pencatatan.'),
    });
}

function handleDialogOpen(open: boolean) {
    if (!open) {
        dialogMode.value = null;
        editingFlow.value = null;
    }
}

function handleClose() {
    router.post(
        closeRoute({ bulan: props.bulan }).url,
        {},
        {
            onSuccess: () =>
                toast.success(`Periode ${props.bulan_label} berhasil ditutup.`),
            onError: () => toast.error('Gagal menutup periode.'),
        },
    );
}

function handleReopen() {
    router.post(
        reopenRoute({ bulan: props.bulan }).url,
        {},
        {
            onSuccess: () =>
                toast.success(
                    `Periode ${props.bulan_label} berhasil dibuka kembali.`,
                ),
            onError: () => toast.error('Gagal membuka kembali periode.'),
        },
    );
}
</script>

<template>
    <Head :title="`Pencatatan Kas — ${bulan_label}`" />

    <div class="mx-auto flex w-full max-w-7xl flex-col gap-3 p-3 md:p-4">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <h1
                    class="flex items-center gap-2 text-xl font-semibold tracking-tight"
                >
                    Pencatatan Kas — {{ bulan_label }}
                    <Badge v-if="is_closed" variant="destructive" class="gap-1">
                        <Lock class="size-3" />
                        Tertutup
                    </Badge>
                    <Badge v-else variant="secondary" class="gap-1">
                        <LockOpen class="size-3" />
                        Terbuka
                    </Badge>
                </h1>
                <p class="text-sm text-muted-foreground">
                    Rincian pemasukan dan pengeluaran kas untuk periode
                    {{ bulan_label }}.
                    <span
                        v-if="is_closed"
                        class="text-amber-700 dark:text-amber-300"
                    >
                        — periode tertutup, pencatatan terkunci.</span
                    >
                </p>
            </div>
            <div class="flex gap-2">
                <Button
                    v-if="!is_closed"
                    variant="outline"
                    @click="handleClose"
                >
                    <Lock class="size-4" />
                    Tutup Periode
                </Button>
                <Button v-else variant="outline" @click="handleReopen">
                    <LockOpen class="size-4" />
                    Buka Kembali Periode
                </Button>
            </div>
        </div>

        <!-- Tabel 1: Pencatatan (uang masuk) -->
        <Card>
            <CardHeader>
                <CardTitle
                    class="flex items-center gap-2 text-base font-medium"
                >
                    <ArrowDownCircle class="size-4 text-green-600" />
                    Pemasukan
                    <Button
                        class="ml-auto"
                        size="sm"
                        :disabled="is_closed"
                        @click="openCreate('in')"
                    >
                        <Plus class="size-4" />
                        Catat
                    </Button>
                </CardTitle>
            </CardHeader>
            <DataTable
                :data="pencatatan"
                :columns="columnsMasuk"
                empty-title="Belum Ada Pencatatan"
                empty-description="Belum terdapat pemasukan pada periode ini. Silakan gunakan tombol Catat untuk menambahkan."
            >
                <template #cell-entry_date="{ row }">
                    {{ formatDate((row as FlowRow).entry_date) }}
                </template>
                <template #cell-amount="{ row }">
                    <span class="font-medium text-green-600">
                        <Money :value="(row as FlowRow).amount" />
                    </span>
                </template>
                <template #cell-aksi="{ row }">
                    <DataTableActions
                        v-if="canEdit(row as FlowRow)"
                        :actions="[
                            {
                                label: 'Edit',
                                onClick: () => openEdit(row as FlowRow),
                            },
                            {
                                label: 'Hapus',
                                variant: 'destructive',
                                onClick: () => confirmDelete(row as FlowRow),
                            },
                        ]"
                    />
                </template>
            </DataTable>
        </Card>

        <!-- Tabel 2: Pengeluaran (uang keluar) -->
        <Card>
            <CardHeader>
                <CardTitle
                    class="flex items-center gap-2 text-base font-medium"
                >
                    <ArrowUpCircle class="size-4 text-destructive" />
                    Pengeluaran
                    <Button
                        class="ml-auto"
                        size="sm"
                        :disabled="is_closed"
                        @click="openCreate('out')"
                    >
                        <Plus class="size-4" />
                        Catat
                    </Button>
                </CardTitle>
            </CardHeader>
            <DataTable
                :data="pengeluaran"
                :columns="columnsKeluar"
                empty-title="Belum Ada Pencatatan"
                empty-description="Belum terdapat pengeluaran pada periode ini. Silakan gunakan tombol Catat untuk menambahkan."
            >
                <template #cell-entry_date="{ row }">
                    {{ formatDate((row as FlowRow).entry_date) }}
                </template>
                <template #cell-kategori="{ row }">
                    {{ (row as FlowRow).kas_category ?? '—' }}
                </template>
                <template #cell-sub_kategori="{ row }">
                    {{ (row as FlowRow).kas_sub_category ?? '—' }}
                </template>
                <template #cell-amount="{ row }">
                    <span class="font-medium text-destructive">
                        <Money :value="(row as FlowRow).amount" />
                    </span>
                </template>
                <template #cell-aksi="{ row }">
                    <DataTableActions
                        v-if="canEdit(row as FlowRow)"
                        :actions="[
                            {
                                label: 'Edit',
                                onClick: () => openEdit(row as FlowRow),
                            },
                            {
                                label: 'Hapus',
                                variant: 'destructive',
                                onClick: () => confirmDelete(row as FlowRow),
                            },
                        ]"
                    />
                </template>
            </DataTable>
        </Card>
    </div>

    <KasEntryDialog
        :open="dialogOpen"
        :mode="dialogMode ?? 'in'"
        :default-date="todayWIB()"
        :cash-flow="editingFlow"
        :kas-categories="kasCategories"
        @update:open="handleDialogOpen"
    />

    <ConfirmDeleteDialog
        :open="!!deletingFlow"
        title="Hapus Pencatatan?"
        :description="
            deletingFlow
                ? `Pencatatan '${deletingFlow.description}' senilai Rp ${Number(deletingFlow.amount).toLocaleString('id-ID')} akan dihapus secara permanen.`
                : ''
        "
        @update:open="
            (o) => {
                if (!o) deletingFlow = null;
            }
        "
        @confirm="executeDelete"
    />
</template>

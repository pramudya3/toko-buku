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

import { Head, Link } from '@inertiajs/vue3';
import { ArrowDownCircle, ArrowUpCircle, Plus, Undo2 } from '@lucide/vue';
import { ref } from 'vue';
import DataTable from '@/components/DataTable.vue';
import type { DataTableColumn } from '@/components/DataTable.vue';
import KasEntryDialog from '@/components/KasEntryDialog.vue';
import Money from '@/components/Money.vue';
import { Button } from '@/components/ui/button';
import { Card, CardHeader, CardTitle } from '@/components/ui/card';
import { index as indexRoute } from '@/routes/admin/kas';

type FlowRow = {
    id: string;
    entry_date: string;
    flow_type: string;
    description: string;
    order_no: string | null;
    amount: number;
};

defineProps<{
    bulan: string;
    bulan_label: string;
    summary: { masuk: number; keluar: number };
    pencatatan: FlowRow[];
    pengeluaran: FlowRow[];
}>();

const dialogMode = ref<'in' | 'out' | null>(null);

const columns: DataTableColumn[] = [
    { key: 'entry_date', header: 'Tanggal', cellClass: 'whitespace-nowrap' },
    { key: 'description', header: 'Keterangan' },
    { key: 'order_no', header: 'Order', cellClass: 'font-mono' },
    {
        key: 'amount',
        header: 'Nominal',
        cellClass: 'text-right tabular-nums',
    },
];

function formatDate(date: string): string {
    const [y, m, d] = date.split('-');

    return `${d}/${m}/${y}`;
}
</script>

<template>
    <Head :title="`Pencatatan Kas — ${bulan_label}`" />

    <div class="flex flex-col gap-4 p-4 md:p-6">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <Button variant="ghost" size="sm" class="mb-1 -ml-2" as-child>
                    <Link :href="indexRoute().url">
                        <Undo2 class="size-4" />
                        Kembali
                    </Link>
                </Button>
                <h1 class="text-xl font-semibold tracking-tight">
                    Pencatatan Kas — {{ bulan_label }}
                </h1>
                <p class="text-sm text-muted-foreground">
                    Rincian pemasukan dan pengeluaran kas
                </p>
            </div>
        </div>

        <!-- Tabel 1: Pencatatan (uang masuk) -->
        <Card>
            <CardHeader>
                <CardTitle
                    class="flex items-center gap-2 text-base font-medium"
                >
                    <ArrowDownCircle class="size-4 text-green-600" />
                    Pencatatan (Uang Masuk)
                    <Button
                        class="ml-auto"
                        size="sm"
                        @click="dialogMode = 'in'"
                    >
                        <Plus class="size-4" />
                        Catat
                    </Button>
                </CardTitle>
            </CardHeader>
            <DataTable
                :data="pencatatan"
                :columns="columns"
                empty-title="Belum ada uang masuk"
                empty-description="Klik tombol 'Catat' untuk mencatat pemasukan manual."
            >
                <template #cell-entry_date="{ row }">
                    {{ formatDate(row.entry_date) }}
                </template>
                <template #cell-order_no="{ row }">
                    {{ row.order_no ?? '—' }}
                </template>
                <template #cell-amount="{ row }">
                    <span class="font-medium text-green-600">
                        <Money :value="row.amount" />
                    </span>
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
                    Pengeluaran (Uang Keluar)
                    <Button
                        class="ml-auto"
                        size="sm"
                        @click="dialogMode = 'out'"
                    >
                        <Plus class="size-4" />
                        Pengeluaran
                    </Button>
                </CardTitle>
            </CardHeader>
            <DataTable
                :data="pengeluaran"
                :columns="columns"
                empty-title="Belum ada pengeluaran"
                empty-description="Klik tombol 'Pengeluaran' untuk mencatat pengeluaran manual."
            >
                <template #cell-entry_date="{ row }">
                    {{ formatDate(row.entry_date) }}
                </template>
                <template #cell-order_no="{ row }">
                    {{ row.order_no ?? '—' }}
                </template>
                <template #cell-amount="{ row }">
                    <span class="font-medium text-destructive">
                        <Money :value="row.amount" />
                    </span>
                </template>
            </DataTable>
        </Card>
    </div>

    <KasEntryDialog
        :open="dialogMode !== null"
        :mode="dialogMode ?? 'in'"
        :default-date="`${bulan}-01`"
        @update:open="
            (open) => {
                if (!open) dialogMode = null;
            }
        "
    />
</template>

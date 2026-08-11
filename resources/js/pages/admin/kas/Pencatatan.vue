<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { ArrowDownCircle, ArrowUpCircle } from '@lucide/vue';
import DataTable from '@/components/DataTable.vue';
import type { DataTableColumn } from '@/components/DataTable.vue';
import DataTableActions from '@/components/DataTableActions.vue';
import Money from '@/components/Money.vue';
import { Card, CardContent } from '@/components/ui/card';
import { detail as detailRoute } from '@/routes/admin/kas';

type MonthRow = {
    key: string;
    label: string;
    count: number;
    masuk: number;
    keluar: number;
};

defineProps<{
    months: MonthRow[];
    summary: { masuk: number; keluar: number };
}>();

const columns: DataTableColumn[] = [
    { key: 'label', header: 'Bulan', cellClass: 'font-medium' },
    {
        key: 'masuk',
        header: 'Total Uang Masuk',
        cellClass: 'text-right tabular-nums',
    },
    {
        key: 'keluar',
        header: 'Total Uang Keluar',
        cellClass: 'text-right tabular-nums',
    },
    { key: 'aksi', header: 'Aksi', srOnly: true, cellClass: 'text-right' },
];
</script>

<template>
    <Head title="Pencatatan Kas" />

    <div class="flex flex-col gap-4 p-4 md:p-6">
        <div>
            <h1 class="text-xl font-semibold tracking-tight">Pencatatan Kas</h1>
            <p class="text-sm text-muted-foreground">
                Rekap uang masuk & uang keluar per bulan
            </p>
        </div>

        <div class="grid gap-4 sm:grid-cols-3">
            <Card>
                <CardContent class="flex items-center gap-3 p-4 text-sm">
                    <ArrowDownCircle class="size-5 shrink-0 text-green-600" />
                    <div>
                        <p class="text-muted-foreground">Total Uang Masuk</p>
                        <p class="text-lg font-semibold">
                            <Money :value="summary.masuk" />
                        </p>
                    </div>
                </CardContent>
            </Card>
            <Card>
                <CardContent class="flex items-center gap-3 p-4 text-sm">
                    <ArrowUpCircle class="size-5 shrink-0 text-destructive" />
                    <div>
                        <p class="text-muted-foreground">Total Uang Keluar</p>
                        <p class="text-lg font-semibold">
                            <Money :value="summary.keluar" />
                        </p>
                    </div>
                </CardContent>
            </Card>
            <Card>
                <CardContent class="flex items-center gap-3 p-4 text-sm">
                    <div class="size-5 shrink-0" />
                    <div>
                        <p class="text-muted-foreground">Saldo Bersih</p>
                        <p
                            class="text-lg font-semibold"
                            :class="
                                summary.masuk - summary.keluar >= 0
                                    ? 'text-green-600'
                                    : 'text-destructive'
                            "
                        >
                            <Money :value="summary.masuk - summary.keluar" />
                        </p>
                    </div>
                </CardContent>
            </Card>
        </div>

        <DataTable
            :data="months"
            :columns="columns"
            key-field="key"
            empty-title="Belum ada pencatatan kas"
            empty-description="Pencatatan muncul setelah ada transaksi penjualan atau entri kas manual."
        >
            <template #cell-label="{ row }">
                {{ row.label }}
                <span class="ml-2 text-xs text-muted-foreground">
                    {{ row.count }} entri
                </span>
            </template>
            <template #cell-masuk="{ row }">
                <span class="text-green-600">
                    <Money :value="row.masuk" />
                </span>
            </template>
            <template #cell-keluar="{ row }">
                <span class="text-destructive">
                    <Money :value="row.keluar" />
                </span>
            </template>
            <template #cell-aksi="{ row }">
                <DataTableActions
                    :actions="[
                        {
                            label: 'Lihat Detail',
                            href: detailRoute(row.key).url,
                        },
                    ]"
                />
            </template>
        </DataTable>
    </div>
</template>

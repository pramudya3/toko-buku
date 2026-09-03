<script setup lang="ts">
defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Beranda', href: '/admin/dashboard' },
            { title: 'Kas', href: '/admin/kas' },
            { title: 'Laporan' },
        ],
    },
});

import { Head, router } from '@inertiajs/vue3';
import { ArrowDownCircle, ArrowUpCircle } from '@lucide/vue';
import { ref, watch } from 'vue';
import DataTable from '@/components/DataTable.vue';
import type { DataTableColumn } from '@/components/DataTable.vue';
import Money from '@/components/Money.vue';
import { Card, CardContent } from '@/components/ui/card';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { laporan as laporanRoute } from '@/routes/admin/kas';

type FlowRow = {
    id: string;
    entry_date: string;
    flow_type: string;
    description: string;
    kas_category?: string | null;
    kas_sub_category?: string | null;
    amount: number;
};

const props = defineProps<{
    flows: {
        data: FlowRow[];
        current_page: number;
        last_page: number;
        total: number;
        per_page: number;
        links: Array<{ url: string | null; label: string; active: boolean }>;
    };
    filters: { bulan: string };
    monthOptions: Array<{ value: string; label: string }>;
    summary: { inflow: number; outflow: number; net: number };
    flowOptions: Record<string, string>;
}>();

const columns: DataTableColumn[] = [
    { key: 'entry_date', header: 'Tanggal Pencatatan' },
    { key: 'flow_type', header: 'Jenis Transaksi' },
    { key: 'description', header: 'Keterangan' },
    { key: 'kategori', header: 'Kategori' },
    { key: 'amount', header: 'Nominal', cellClass: 'text-right tabular-nums' },
];

const allMonths = 'all';
const selectedMonth = ref(props.filters.bulan || allMonths);

watch(selectedMonth, (value) => {
    router.get(
        laporanRoute().url,
        {
            bulan: value === allMonths ? undefined : value,
            per_page:
                new URLSearchParams(window.location.search).get('per_page') ||
                undefined,
        },
        { preserveState: true, replace: true },
    );
});
</script>

<template>
    <Head title="Laporan Kas" />

    <div class="mx-auto flex w-full max-w-7xl flex-col gap-3 p-3 md:p-4">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div>
                <h1 class="text-xl font-semibold tracking-tight">
                    Laporan Kas
                </h1>
                <p class="text-sm text-muted-foreground">
                    Rekapitulasi arus kas per periode bulanan hingga
                    keseluruhan.
                </p>
            </div>
            <Select v-model="selectedMonth">
                <SelectTrigger class="w-56">
                    <SelectValue placeholder="Pilih periode" />
                </SelectTrigger>
                <SelectContent>
                    <SelectItem :value="allMonths">Semua Periode</SelectItem>
                    <SelectItem
                        v-for="month in monthOptions"
                        :key="month.value"
                        :value="month.value"
                    >
                        {{ month.label }}
                    </SelectItem>
                </SelectContent>
            </Select>
        </div>

        <div class="grid gap-4 sm:grid-cols-3">
            <Card>
                <CardContent class="flex items-center gap-3 p-4 text-sm">
                    <ArrowDownCircle class="size-5 shrink-0 text-green-600" />
                    <div>
                        <p class="text-muted-foreground">Pemasukan</p>
                        <p class="text-lg font-semibold">
                            <Money :value="summary.inflow" />
                        </p>
                    </div>
                </CardContent>
            </Card>
            <Card>
                <CardContent class="flex items-center gap-3 p-4 text-sm">
                    <ArrowUpCircle class="size-5 shrink-0 text-destructive" />
                    <div>
                        <p class="text-muted-foreground">Pengeluaran</p>
                        <p class="text-lg font-semibold">
                            <Money :value="summary.outflow" />
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
                                summary.net >= 0
                                    ? 'text-green-600'
                                    : 'text-destructive'
                            "
                        >
                            <Money :value="summary.net" />
                        </p>
                    </div>
                </CardContent>
            </Card>
        </div>

        <Card>
            <CardContent class="p-0">
                <DataTable
                    :data="flows.data"
                    :columns="columns"
                    :paginator="flows"
                >
                    <template #cell-entry_date="{ row }">
                        <span class="whitespace-nowrap">{{
                            row.entry_date
                        }}</span>
                    </template>
                    <template #cell-flow_type="{ row }">
                        {{ flowOptions[row.flow_type] ?? row.flow_type }}
                    </template>
                    <template #cell-kategori="{ row }">
                        <span class="text-sm">{{
                            (row as FlowRow).kas_category ?? '—'
                        }}</span>
                        <span
                            v-if="(row as FlowRow).kas_sub_category"
                            class="text-xs text-muted-foreground"
                        >
                            / {{ (row as FlowRow).kas_sub_category }}</span
                        >
                    </template>
                    <template #cell-amount="{ row }">
                        <span
                            class="font-medium"
                            :class="
                                ['refund', 'expense'].includes(row.flow_type)
                                    ? 'text-destructive'
                                    : 'text-green-600'
                            "
                        >
                            <Money :value="row.amount" />
                        </span>
                    </template>
                </DataTable>
            </CardContent>
        </Card>
    </div>
</template>

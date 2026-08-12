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
    order: { no_order: string } | null;
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
    { key: 'entry_date', header: 'Tanggal' },
    { key: 'flow_type', header: 'Tipe' },
    { key: 'description', header: 'Keterangan' },
    { key: 'order', header: 'Order' },
    { key: 'amount', header: 'Nominal', cellClass: 'text-right' },
];

const allMonths = 'all';
const selectedMonth = ref(props.filters.bulan || allMonths);

watch(selectedMonth, (value) => {
    router.get(
        laporanRoute().url,
        { bulan: value === allMonths ? undefined : value },
        { preserveState: true, replace: true },
    );
});
</script>

<template>
    <Head title="Laporan Kas" />

    <div class="flex flex-col gap-4 p-4 md:p-6">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div>
                <h1 class="text-xl font-semibold tracking-tight">
                    Laporan Kas
                </h1>
                <p class="text-sm text-muted-foreground">
                    Arus kas per bulan atau keseluruhan
                </p>
            </div>
            <Select v-model="selectedMonth">
                <SelectTrigger class="w-56">
                    <SelectValue placeholder="Pilih bulan" />
                </SelectTrigger>
                <SelectContent>
                    <SelectItem :value="allMonths">Keseluruhan</SelectItem>
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
                        <p class="text-muted-foreground">Uang Masuk</p>
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
                        <p class="text-muted-foreground">Uang Keluar</p>
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
                    <template #cell-order="{ row }">
                        <span class="font-mono">
                            {{ row.order?.no_order ?? '—' }}
                        </span>
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

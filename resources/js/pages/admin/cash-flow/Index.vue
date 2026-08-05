<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { ref, watch } from 'vue';
import EmptyState from '@/components/EmptyState.vue';
import Money from '@/components/Money.vue';
import Pagination from '@/components/Pagination.vue';
import StatusBadge from '@/components/StatusBadge.vue';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { index as indexRoute } from '@/routes/admin/cash-flow';

type CashFlow = {
    id: number;
    entry_date: string;
    flow_type: string;
    amount: number;
    description: string | null;
    order: { id: number; no_order: string } | null;
};

type Props = {
    flows: {
        data: CashFlow[];
        current_page: number;
        last_page: number;
        total: number;
        per_page: number;
        links: Array<{ url: string | null; label: string; active: boolean }>;
    };
    filters: { from: string; to: string };
    summary: { inflow: number; outflow: number; net: number };
    flowOptions: Record<string, string>;
};

const props = defineProps<Props>();

const from = ref(props.filters.from);
const to = ref(props.filters.to);

let searchTimer: ReturnType<typeof setTimeout> | undefined;

watch([from, to], () => {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(() => {
        router.get(
            indexRoute().url,
            { from: from.value, to: to.value },
            {
                preserveState: true,
                replace: true,
            },
        );
    }, 350);
});

const flowVariant: Record<
    string,
    'success' | 'warning' | 'danger' | 'info' | 'neutral'
> = {
    revenue: 'success',
    shipping: 'info',
    refund: 'danger',
};
</script>

<template>
    <Head title="Arus Kas" />

    <div class="flex flex-col gap-4 p-4 md:p-6">
        <div>
            <h1 class="text-xl font-semibold tracking-tight">Arus Kas</h1>
            <p class="text-sm text-muted-foreground">
                Mencatat arus kas secara otomatis dari setiap pesanan
            </p>
        </div>

        <div class="grid gap-4 md:grid-cols-3">
            <Card class="p-6">
                <CardHeader class="p-0">
                    <CardTitle class="text-sm font-medium text-muted-foreground"
                        >Kas Masuk</CardTitle
                    >
                </CardHeader>
                <CardContent class="p-0 pt-3">
                    <p
                        class="text-2xl font-semibold text-green-600 tabular-nums"
                    >
                        <Money :value="summary.inflow" />
                    </p>
                </CardContent>
            </Card>
            <Card class="p-6">
                <CardHeader class="p-0">
                    <CardTitle class="text-sm font-medium text-muted-foreground"
                        >Kas Keluar</CardTitle
                    >
                </CardHeader>
                <CardContent class="p-0 pt-3">
                    <p class="text-2xl font-semibold text-red-600 tabular-nums">
                        <Money :value="summary.outflow" />
                    </p>
                </CardContent>
            </Card>
            <Card class="p-6">
                <CardHeader class="p-0">
                    <CardTitle class="text-sm font-medium text-muted-foreground"
                        >Net</CardTitle
                    >
                </CardHeader>
                <CardContent class="p-0 pt-3">
                    <p class="text-2xl font-semibold tabular-nums">
                        <Money :value="summary.net" />
                    </p>
                </CardContent>
            </Card>
        </div>

        <Card>
            <CardContent class="p-4">
                <div class="flex flex-wrap items-end gap-4">
                    <div class="grid gap-2">
                        <Label for="from">Dari tanggal</Label>
                        <Input
                            id="from"
                            v-model="from"
                            type="date"
                            class="w-44"
                        />
                    </div>
                    <div class="grid gap-2">
                        <Label for="to">Sampai tanggal</Label>
                        <Input id="to" v-model="to" type="date" class="w-44" />
                    </div>
                </div>
            </CardContent>
        </Card>

        <Card>
            <CardContent class="p-0">
                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead>Tanggal</TableHead>
                            <TableHead>Tipe</TableHead>
                            <TableHead>Deskripsi</TableHead>
                            <TableHead>Referensi</TableHead>
                            <TableHead class="text-right">Jumlah</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <TableRow v-for="flow in flows.data" :key="flow.id">
                            <TableCell class="tabular-nums">{{
                                flow.entry_date
                            }}</TableCell>
                            <TableCell>
                                <StatusBadge
                                    :variant="
                                        flowVariant[flow.flow_type] ?? 'neutral'
                                    "
                                    :label="
                                        flowOptions[flow.flow_type] ??
                                        flow.flow_type
                                    "
                                />
                            </TableCell>
                            <TableCell>{{ flow.description ?? '—' }}</TableCell>
                            <TableCell>
                                <Link
                                    v-if="flow.order"
                                    :href="`/admin/orders/${flow.order.id}`"
                                    class="font-mono text-xs text-blue-600 hover:underline"
                                >
                                    {{ flow.order.no_order }}
                                </Link>
                                <span v-else class="text-muted-foreground"
                                    >—</span
                                >
                            </TableCell>
                            <TableCell
                                class="text-right font-medium tabular-nums"
                            >
                                <Money :value="flow.amount" />
                            </TableCell>
                        </TableRow>
                    </TableBody>
                </Table>
                <EmptyState
                    v-if="!flows.data.length"
                    title="Tidak ada arus kas"
                    description="Order yang selesai akan mencatat entry revenue & shipping di sini."
                />
                <Pagination v-else :paginator="flows" />
            </CardContent>
        </Card>
    </div>
</template>

<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { ref, watch } from 'vue';
import DataTableActions from '@/components/DataTableActions.vue';
import EmptyState from '@/components/EmptyState.vue';
import Money from '@/components/Money.vue';
import Pagination from '@/components/Pagination.vue';
import StatusBadge from '@/components/StatusBadge.vue';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { index as indexRoute } from '@/routes/admin/dropship';
import { show } from '@/routes/admin/orders';

type Order = {
    id: number;
    no_order: string;
    nama_pembeli: string;
    total: number;
    status: string;
    created_at: string;
    items_count: number;
    user: { id: number; name: string } | null;
    dropshipper: {
        id: number;
        end_customer_name: string;
        end_customer_whatsapp: string | null;
        end_customer_address: string | null;
    } | null;
};

type Props = {
    orders: {
        data: Order[];
        current_page: number;
        last_page: number;
        total: number;
        per_page: number;
        links: Array<{ url: string | null; label: string; active: boolean }>;
    };
    filters: { from?: string; to?: string; status?: string };
    statusOptions: Record<string, string>;
};

const props = defineProps<Props>();

const allStatuses = '__all_statuses__';
const from = ref(props.filters.from ?? '');
const to = ref(props.filters.to ?? '');
const status = ref(props.filters.status ?? allStatuses);

let searchTimer: ReturnType<typeof setTimeout> | undefined;

watch([from, to, status], () => {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(() => {
        router.get(
            indexRoute().url,
            {
                from: from.value || undefined,
                to: to.value || undefined,
                status: status.value === allStatuses ? undefined : status.value,
            },
            {
                preserveState: true,
                replace: true,
            },
        );
    }, 350);
});

const statusVariant: Record<
    string,
    'success' | 'warning' | 'danger' | 'info' | 'neutral'
> = {
    menunggu_konfirmasi: 'warning',
    diproses: 'info',
    dikirim: 'info',
    selesai: 'success',
    batal: 'danger',
};
</script>

<template>
    <Head title="Dropship" />

    <div class="flex flex-col gap-4 p-4 md:p-6">
        <div>
            <h1 class="text-xl font-semibold tracking-tight">Order Dropship</h1>
            <p class="text-sm text-muted-foreground">
                Pesanan yang dikirim langsung ke end-customer
            </p>
        </div>

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
            <div class="grid gap-2">
                <Label for="status">Status</Label>
                <Select v-model="status">
                    <SelectTrigger id="status" class="w-48">
                        <SelectValue placeholder="Semua status" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem :value="allStatuses"
                            >Semua status</SelectItem
                        >
                        <SelectItem
                            v-for="(label, value) in statusOptions"
                            :key="value"
                            :value="value"
                        >
                            {{ label }}
                        </SelectItem>
                    </SelectContent>
                </Select>
            </div>
        </div>

        <Card>
            <CardContent class="p-0">
                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead>No. Order</TableHead>
                            <TableHead>Dropshipper</TableHead>
                            <TableHead>End-Customer</TableHead>
                            <TableHead>Item</TableHead>
                            <TableHead>Total</TableHead>
                            <TableHead>Status</TableHead>
                            <TableHead class="text-right"><span class="sr-only">Aksi</span></TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <TableRow v-for="order in orders.data" :key="order.id">
                            <TableCell class="font-medium">{{
                                order.no_order
                            }}</TableCell>
                            <TableCell>{{
                                order.user?.name ?? order.nama_pembeli
                            }}</TableCell>
                            <TableCell>
                                <template v-if="order.dropshipper">
                                    <p>
                                        {{
                                            order.dropshipper.end_customer_name
                                        }}
                                    </p>
                                    <p
                                        class="text-xs text-muted-foreground tabular-nums"
                                    >
                                        {{
                                            order.dropshipper
                                                .end_customer_whatsapp
                                        }}
                                    </p>
                                </template>
                                <span v-else class="text-muted-foreground"
                                    >Data belum diisi</span
                                >
                            </TableCell>
                            <TableCell>{{ order.items_count }}</TableCell>
                            <TableCell
                                ><Money :value="order.total"
                            /></TableCell>
                            <TableCell>
                                <StatusBadge
                                    :variant="
                                        statusVariant[order.status] ?? 'neutral'
                                    "
                                    :label="
                                        statusOptions[order.status] ??
                                        order.status
                                    "
                                />
                            </TableCell>
                            <TableCell class="text-right">
                                <DataTableActions
                                    :actions="[
                                        {
                                            label: 'Detail',
                                            href: show(order.id),
                                        },
                                    ]"
                                />
                            </TableCell>
                        </TableRow>
                    </TableBody>
                </Table>
                <EmptyState
                    v-if="!orders.data.length"
                    title="Tidak ada order dropship"
                    description="Order dengan is_dropship akan tampil di sini."
                />
                <Pagination v-else :paginator="orders" />
            </CardContent>
        </Card>
    </div>
</template>

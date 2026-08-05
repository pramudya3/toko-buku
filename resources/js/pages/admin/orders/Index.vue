<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { PackageSearch, Search } from '@lucide/vue';
import { ref, watch } from 'vue';
import DataTableActions from '@/components/DataTableActions.vue';
import EmptyState from '@/components/EmptyState.vue';
import Money from '@/components/Money.vue';
import Pagination from '@/components/Pagination.vue';
import StatusBadge from '@/components/StatusBadge.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
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
import { create, index as indexRoute, show } from '@/routes/admin/orders';

type Order = {
    id: number;
    no_order: string;
    nama_pembeli: string;
    total: number;
    status: string;
    is_dropship: boolean;
    created_at: string;
    items_count: number;
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
    filters: { search?: string; status?: string; dropship?: string };
    statusOptions: Record<string, string>;
};

const props = defineProps<Props>();

const allStatuses = '__all_statuses__';
const search = ref(props.filters.search ?? '');
const status = ref(props.filters.status ?? allStatuses);
const dropship = ref(props.filters.dropship === '1');

let searchTimer: ReturnType<typeof setTimeout> | undefined;

watch([search, status, dropship], () => {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(() => {
        router.get(
            indexRoute().url,
            {
                search: search.value || undefined,
                status: status.value === allStatuses ? undefined : status.value,
                dropship: dropship.value ? '1' : undefined,
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
    <Head title="Pesanan" />

    <div class="flex flex-col gap-4 p-4 md:p-6">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div>
                <h1 class="text-xl font-semibold tracking-tight">Pesanan</h1>
                <p class="text-sm text-muted-foreground">
                    Mengelola pesanan dari storefront dan WhatsApp
                </p>
            </div>
            <Button as-child>
                <Link :href="create()">
                    <PackageSearch class="size-4" />
                    Buat Pesanan
                </Link>
            </Button>
        </div>

        <div class="grid gap-4 md:grid-cols-3">
            <div class="relative">
                <Search
                    class="absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
                />
                <Input
                    v-model="search"
                    class="pl-9"
                    placeholder="Cari no. order / nama pembeli..."
                />
            </div>
            <Select v-model="status">
                <SelectTrigger>
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
            <Label class="flex items-center gap-2 pt-2">
                <Checkbox v-model="dropship" />
                Hanya order dropship
            </Label>
        </div>

        <Card>
            <CardContent class="p-0">
                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead>No. Order</TableHead>
                            <TableHead>Pembeli</TableHead>
                            <TableHead>Item</TableHead>
                            <TableHead>Total</TableHead>
                            <TableHead>Status</TableHead>
                            <TableHead>Tanggal</TableHead>
                            <TableHead class="text-right"><span class="sr-only">Aksi</span></TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <TableRow v-for="order in orders.data" :key="order.id">
                            <TableCell class="font-medium">
                                {{ order.no_order }}
                                <span
                                    v-if="order.is_dropship"
                                    class="ml-1 text-xs text-blue-600"
                                    >(dropship)</span
                                >
                            </TableCell>
                            <TableCell>{{ order.nama_pembeli }}</TableCell>
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
                            <TableCell class="text-muted-foreground">{{
                                new Date(order.created_at).toLocaleDateString(
                                    'id-ID',
                                )
                            }}</TableCell>
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
                    title="Tidak ada pesanan"
                    description="Order dari storefront atau WhatsApp akan tampil di sini."
                />
                <Pagination v-else :paginator="orders" />
            </CardContent>
        </Card>
    </div>
</template>

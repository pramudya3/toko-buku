<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import {
    AlertTriangle,
    Banknote,
    BookOpen,
    ShoppingCart,
    TrendingUp,
} from '@lucide/vue';
import EmptyState from '@/components/EmptyState.vue';
import Money from '@/components/Money.vue';
import StatusBadge from '@/components/StatusBadge.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { index as booksIndex } from '@/routes/admin/books';
import { index as ordersIndex } from '@/routes/admin/orders';

type SalesPoint = {
    date: string;
    total: number;
};

type Props = {
    stats: {
        revenue: number;
        orders_count: number;
        cash_in_month: number;
        book_count: number;
        total_stock: number;
    };
    lowStockBooks: Array<{
        id: string;
        judul: string;
        kode_sku: string | null;
        stok: number;
    }>;
    lowStockThreshold: number;
    recentOrders: Array<{
        id: string;
        no_order: string;
        nama_pembeli: string;
        total: number;
        status: string;
        created_at: string;
    }>;
    salesChart: SalesPoint[];
    statusOptions: Record<string, string>;
};

defineProps<Props>();

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

// Label tanggal di bawah chart — format dd (chart = bulan berjalan, 1 s.d. hari ini).
function shortDate(date: string): string {
    return date.slice(8, 10);
}
</script>

<template>
    <Head title="Dashboard" />

    <div class="flex flex-col gap-4 p-4 md:p-6">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div>
                <h1 class="text-xl font-semibold tracking-tight">Dashboard</h1>
                <p class="text-sm text-muted-foreground">
                    Ringkasan operasional toko
                </p>
            </div>
        </div>

        <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
            <Card class="p-6">
                <CardHeader class="p-0">
                    <CardTitle
                        class="flex items-center gap-2 text-sm font-medium text-muted-foreground"
                    >
                        <TrendingUp class="size-4" />
                        Penjualan (Bulan Ini)
                    </CardTitle>
                </CardHeader>
                <CardContent class="p-0 pt-3">
                    <p class="text-2xl font-semibold tabular-nums">
                        <Money :value="stats.revenue" />
                    </p>
                </CardContent>
            </Card>
            <Card class="p-6">
                <CardHeader class="p-0">
                    <CardTitle
                        class="flex items-center gap-2 text-sm font-medium text-muted-foreground"
                    >
                        <ShoppingCart class="size-4" />
                        Jumlah Order (Bulan Ini)
                    </CardTitle>
                </CardHeader>
                <CardContent class="p-0 pt-3">
                    <p class="text-2xl font-semibold tabular-nums">
                        {{ stats.orders_count }}
                    </p>
                </CardContent>
            </Card>
            <Card class="p-6">
                <CardHeader class="p-0">
                    <CardTitle
                        class="flex items-center gap-2 text-sm font-medium text-muted-foreground"
                    >
                        <Banknote class="size-4" />
                        Uang Cash (Bulan Ini)
                    </CardTitle>
                </CardHeader>
                <CardContent class="p-0 pt-3">
                    <p class="text-2xl font-semibold tabular-nums">
                        <Money :value="stats.cash_in_month" />
                    </p>
                    <p class="mt-1 text-xs text-muted-foreground">
                        Khusus transaksi tunai
                    </p>
                </CardContent>
            </Card>
            <Card class="p-6">
                <CardHeader class="p-0">
                    <CardTitle
                        class="flex items-center gap-2 text-sm font-medium text-muted-foreground"
                    >
                        <BookOpen class="size-4" />
                        Buku / Stok Total
                    </CardTitle>
                </CardHeader>
                <CardContent class="p-0 pt-3">
                    <p class="text-2xl font-semibold tabular-nums">
                        {{ stats.book_count }}
                        <span class="text-sm font-normal text-muted-foreground"
                            >buku</span
                        >
                        · {{ stats.total_stock }}
                        <span class="text-sm font-normal text-muted-foreground"
                            >stok</span
                        >
                    </p>
                </CardContent>
            </Card>
        </div>

        <div class="grid gap-4 lg:grid-cols-3">
            <!-- Grafik penjualan -->
            <Card class="flex h-72 flex-col gap-4 lg:col-span-2">
                <CardHeader class="pb-0">
                    <CardTitle class="text-base font-medium"
                        >Penjualan Harian</CardTitle
                    >
                    <div data-slot="card-action">
                        <span
                            class="flex items-center gap-1.5 text-xs text-muted-foreground"
                        >
                            <span
                                class="size-2.5 rounded-sm border border-primary/40 bg-primary/15"
                            />
                            Penjualan bersih (setelah refund)
                        </span>
                    </div>
                </CardHeader>
                <CardContent class="flex min-h-0 flex-1 flex-col">
                    <div
                        v-if="salesChart.length"
                        class="flex min-h-0 flex-1 items-end gap-1"
                    >
                        <div
                            v-for="point in salesChart"
                            :key="point.date"
                            class="group relative flex-1 rounded-t bg-primary/15 transition-colors hover:bg-primary/30"
                            :style="{
                                height: `${Math.max((point.total / Math.max(...salesChart.map((p) => p.total), 1)) * 100, 2)}%`,
                            }"
                        >
                            <div
                                class="absolute -top-8 left-1/2 z-10 hidden -translate-x-1/2 rounded-md border bg-background px-2 py-1 text-xs shadow-sm group-hover:block"
                            >
                                <Money :value="point.total" />
                            </div>
                        </div>
                    </div>
                    <EmptyState
                        v-else
                        title="Belum ada penjualan"
                        description="Order yang selesai akan muncul di grafik ini."
                        class="flex-1"
                    />

                    <template v-if="salesChart.length">
                        <div
                            class="flex gap-1 pt-2 text-[10px] text-muted-foreground"
                        >
                            <span
                                v-for="point in salesChart"
                                :key="point.date"
                                class="flex-1 truncate text-center"
                            >
                                {{ shortDate(point.date) }}
                            </span>
                        </div>
                    </template>
                </CardContent>
            </Card>

            <!-- Peringatan stok menipis -->
            <Card class="flex h-72 flex-col gap-4">
                <CardHeader class="pb-0">
                    <CardTitle
                        class="flex items-center gap-2 text-base font-medium"
                    >
                        <AlertTriangle class="size-4 text-amber-500" />
                        Stok Menipis
                        <span
                            v-if="lowStockBooks.length"
                            class="rounded-md bg-muted px-1.5 py-0.5 text-xs font-medium tabular-nums"
                        >
                            {{ lowStockBooks.length }}
                        </span>
                    </CardTitle>
                    <div data-slot="card-action">
                        <Button variant="ghost" size="sm" as-child>
                            <Link
                                :href="
                                    booksIndex({
                                        query: { low_stock: '1' },
                                    }).url
                                "
                                >Lihat semua</Link
                            >
                        </Button>
                    </div>
                </CardHeader>

                <!-- Legenda: menipis vs habis -->
                <div
                    class="flex items-center gap-4 px-6 text-xs text-muted-foreground"
                >
                    <span class="flex items-center gap-1.5">
                        <span class="size-2 rounded-full bg-amber-500" />
                        Menipis (≤ {{ lowStockThreshold }})
                    </span>
                    <span class="flex items-center gap-1.5">
                        <span class="size-2 rounded-full bg-red-500" />
                        Habis (0)
                    </span>
                </div>

                <CardContent class="min-h-0 flex-1 overflow-y-auto">
                    <ul v-if="lowStockBooks.length" class="space-y-2">
                        <li
                            v-for="book in lowStockBooks"
                            :key="book.id"
                            class="flex items-center justify-between gap-2 rounded-lg border px-3 py-2"
                        >
                            <div class="min-w-0">
                                <p class="truncate text-sm font-medium">
                                    {{ book.judul }}
                                </p>
                                <p class="text-xs text-muted-foreground">
                                    {{ book.kode_sku }}
                                </p>
                            </div>
                            <StatusBadge
                                :variant="
                                    book.stok === 0 ? 'danger' : 'warning'
                                "
                                :label="
                                    book.stok === 0
                                        ? 'Habis'
                                        : `${book.stok} stok`
                                "
                            />
                        </li>
                    </ul>
                    <EmptyState
                        v-else
                        title="Stok aman"
                        description="Tidak ada buku dengan stok menipis."
                    >
                        <Button variant="outline" size="sm" as-child>
                            <Link :href="booksIndex()">Kelola Buku</Link>
                        </Button>
                    </EmptyState>
                </CardContent>
            </Card>
        </div>

        <!-- Pesanan terbaru -->
        <Card>
            <CardHeader>
                <CardTitle class="text-base font-medium"
                    >Pesanan Terbaru</CardTitle
                >
                <div data-slot="card-action">
                    <Button variant="ghost" size="sm" as-child>
                        <Link :href="ordersIndex()">Lihat semua</Link>
                    </Button>
                </div>
            </CardHeader>
            <CardContent class="p-0">
                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead>No. Order</TableHead>
                            <TableHead>Pembeli</TableHead>
                            <TableHead>Total</TableHead>
                            <TableHead>Status</TableHead>
                            <TableHead>Tanggal</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <TableRow v-for="order in recentOrders" :key="order.id">
                            <TableCell class="font-medium">{{
                                order.no_order
                            }}</TableCell>
                            <TableCell>{{ order.nama_pembeli }}</TableCell>
                            <TableCell>
                                <Money :value="order.total" />
                            </TableCell>
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
                                    { timeZone: 'Asia/Jakarta' },
                                )
                            }}</TableCell>
                        </TableRow>
                    </TableBody>
                </Table>
                <EmptyState
                    v-if="!recentOrders.length"
                    title="Belum ada pesanan"
                />
            </CardContent>
        </Card>
    </div>
</template>

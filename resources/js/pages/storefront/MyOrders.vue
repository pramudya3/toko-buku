<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import EmptyState from '@/components/EmptyState.vue';
import Money from '@/components/Money.vue';
import Pagination from '@/components/Pagination.vue';
import StatusBadge from '@/components/StatusBadge.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import CustomerLayout from '@/layouts/customer/CustomerLayout.vue';

type Order = {
    id: string;
    no_order: string;
    total: number;
    status: string;
    payment_status: string;
    created_at: string;
    items_count: number;
};

defineProps<{
    orders: {
        data: Order[];
        current_page: number;
        last_page: number;
        total: number;
        per_page: number;
        links: Array<{ url: string | null; label: string; active: boolean }>;
    };
    statusOptions: Record<string, string>;
}>();

defineOptions({
    layout: CustomerLayout,
});
</script>

<template>
    <Head title="Pesanan Saya" />

    <div class="flex flex-col gap-6">
        <div>
            <h1 class="text-2xl font-bold tracking-tight">Pesanan Saya</h1>
            <p class="text-sm text-muted-foreground">
                Riwayat pesanan yang pernah Anda buat.
            </p>
        </div>

        <Card v-if="orders.data.length">
            <CardContent class="p-0">
                <ul class="divide-y">
                    <li
                        v-for="order in orders.data"
                        :key="order.id"
                        class="flex flex-wrap items-center justify-between gap-3 px-4 py-4"
                    >
                        <div class="min-w-0">
                            <p class="font-mono text-sm font-semibold">
                                {{ order.no_order }}
                            </p>
                            <p class="text-xs text-muted-foreground">
                                {{ order.items_count }} item ·
                                {{
                                    new Date(
                                        order.created_at,
                                    ).toLocaleDateString('id-ID', {
                                        day: '2-digit',
                                        month: 'long',
                                        year: 'numeric',
                                        timeZone: 'Asia/Jakarta',
                                    })
                                }}
                            </p>
                        </div>
                        <div class="flex items-center gap-4">
                            <Money
                                :value="order.total"
                                class="text-sm font-semibold"
                            />
                            <div class="flex flex-col items-end gap-1">
                                <StatusBadge
                                    :variant="
                                        order.status === 'menunggu_konfirmasi'
                                            ? 'warning'
                                            : order.status === 'selesai'
                                              ? 'success'
                                              : order.status === 'batal'
                                                ? 'danger'
                                                : 'info'
                                    "
                                    :label="
                                        statusOptions[order.status] ??
                                        order.status
                                    "
                                />
                                <StatusBadge
                                    v-if="order.payment_status === 'menunggu'"
                                    variant="warning"
                                    label="Menunggu Pembayaran"
                                />
                                <StatusBadge
                                    v-else-if="order.payment_status === 'lunas'"
                                    variant="success"
                                    label="Lunas"
                                />
                            </div>
                        </div>
                    </li>
                </ul>
                <Pagination :paginator="orders" />
            </CardContent>
        </Card>

        <EmptyState
            v-else
            icon="/img/empty-orders.png"
            title="Belum ada pesanan"
            description="Yuk belanja buku pertama Anda!"
        >
            <Button size="sm" as-child>
                <Link :href="'/buku'">Lihat Katalog</Link>
            </Button>
        </EmptyState>
    </div>
</template>

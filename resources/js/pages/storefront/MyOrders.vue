<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { PackageOpen } from '@lucide/vue';
import Money from '@/components/Money.vue';
import Pagination from '@/components/Pagination.vue';
import StatusBadge from '@/components/StatusBadge.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import CustomerLayout from '@/layouts/customer/CustomerLayout.vue';

type Order = {
    id: number;
    no_order: string;
    total: number;
    status: string;
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
                                    new Date(order.created_at).toLocaleDateString(
                                        'id-ID',
                                        {
                                            day: '2-digit',
                                            month: 'long',
                                            year: 'numeric',
                                        },
                                    )
                                }}
                            </p>
                        </div>
                        <div class="flex items-center gap-4">
                            <Money
                                :value="order.total"
                                class="text-sm font-semibold"
                            />
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
                        </div>
                    </li>
                </ul>
                <Pagination :paginator="orders" />
            </CardContent>
        </Card>

        <div v-else class="flex flex-col items-center gap-3 py-10 text-center">
            <PackageOpen class="size-10 text-muted-foreground" />
            <p class="text-sm text-muted-foreground">
                Belum ada pesanan. Yuk belanja buku pertama Anda!
            </p>
            <Button size="sm" as-child>
                <Link :href="'/buku'">Lihat Katalog</Link>
            </Button>
        </div>
    </div>
</template>

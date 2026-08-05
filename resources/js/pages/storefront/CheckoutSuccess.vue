<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { CheckCircle2 } from '@lucide/vue';
import Money from '@/components/Money.vue';
import { Button } from '@/components/ui/button';
import CustomerLayout from '@/layouts/customer/CustomerLayout.vue';

type OrderItem = {
    id: number;
    judul_snapshot: string;
    qty: number;
    price_final: number;
};

type Order = {
    no_order: string;
    nama_pembeli: string;
    total: number;
    metode_bayar: string;
    items: OrderItem[];
};

defineProps<{
    order: Order;
}>();

defineOptions({
    layout: CustomerLayout,
});
</script>

<template>
    <Head title="Pesanan Berhasil" />

    <div class="mx-auto flex max-w-xl flex-col items-center gap-6 text-center">
        <CheckCircle2 class="size-14 text-green-600" />
        <div>
            <h1 class="text-2xl font-bold tracking-tight">
                Terima kasih, {{ order.nama_pembeli }}!
            </h1>
            <p class="mt-2 text-muted-foreground">
                Pesanan Anda telah kami terima. Silakan selesaikan pembayaran
                sesuai instruksi di bawah.
            </p>
        </div>

        <div class="w-full rounded-xl border p-6 text-left">
            <div class="flex justify-between text-sm">
                <span class="text-muted-foreground">No. Order</span>
                <span class="font-mono font-semibold">{{ order.no_order }}</span>
            </div>
            <div class="mt-2 flex justify-between text-sm">
                <span class="text-muted-foreground">Metode Bayar</span>
                <span class="font-medium">{{ order.metode_bayar }}</span>
            </div>
            <div class="mt-2 flex justify-between text-sm">
                <span class="text-muted-foreground">Total</span>
                <Money :value="order.total" class="font-semibold" />
            </div>

            <div class="mt-4 border-t pt-4">
                <h2 class="text-sm font-semibold">Item Pesanan</h2>
                <ul class="mt-2 flex flex-col gap-1">
                    <li
                        v-for="item in order.items"
                        :key="item.id"
                        class="flex justify-between text-sm"
                    >
                        <span class="text-muted-foreground">
                            {{ item.judul_snapshot }} × {{ item.qty }}
                        </span>
                        <Money :value="item.price_final * item.qty" />
                    </li>
                </ul>
            </div>

            <div class="mt-4 rounded-lg bg-muted p-4 text-sm">
                <p class="font-medium">Instruksi Pembayaran</p>
                <p class="mt-1 text-muted-foreground">
                    Transfer ke rekening toko, lalu kirim bukti transfer via
                    WhatsApp ke nomor kami. Pesanan diproses setelah pembayaran
                    dikonfirmasi.
                </p>
            </div>
        </div>

        <Button as-child>
            <Link :href="'/buku'">Lanjut Belanja</Link>
        </Button>
    </div>
</template>

<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { CheckCircle2, Clock, Landmark, Printer } from '@lucide/vue';
import Money from '@/components/Money.vue';
import { Button } from '@/components/ui/button';
import CustomerLayout from '@/layouts/customer/CustomerLayout.vue';
import { invoice as invoiceRoute } from '@/routes/my-orders';

type OrderItem = {
    id: string;
    judul_snapshot: string;
    edition_snapshot: string | null;
    qty: number;
    price_final: number;
};

type Order = {
    id: string;
    no_order: string;
    nama_pembeli: string;
    total: number;
    shipping_cost: number;
    voucher_code_snapshot: string | null;
    voucher_scope_snapshot: string;
    voucher_discount_amount: number;
    metode_bayar: string;
    payment_status: string;
    items: OrderItem[];
};

type BankAccount = {
    id: string;
    bank_name: string;
    account_number: string;
    account_holder: string;
};

defineProps<{
    order: Order;
    bankAccounts: BankAccount[];
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
                <span class="font-mono font-semibold">{{
                    order.no_order
                }}</span>
            </div>
            <div class="mt-2 flex justify-between text-sm">
                <span class="text-muted-foreground">Metode Bayar</span>
                <span class="font-medium">{{ order.metode_bayar }}</span>
            </div>
            <div
                v-if="order.voucher_discount_amount > 0"
                class="mt-2 flex justify-between text-sm"
            >
                <span class="text-muted-foreground"
                    >Voucher {{ order.voucher_code_snapshot ?? 'Diskon'
                    }}<template
                        v-if="order.voucher_scope_snapshot === 'ongkir'"
                    >
                        (ongkir)</template
                    ></span
                >
                <Money
                    :value="-order.voucher_discount_amount"
                    class="font-medium text-destructive"
                />
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
                            <span
                                v-if="item.edition_snapshot"
                                class="text-xs text-muted-foreground"
                            >
                                ({{ item.edition_snapshot }})
                            </span>
                        </span>
                        <Money :value="item.price_final * item.qty" />
                    </li>
                </ul>
            </div>

            <div class="mt-4 rounded-lg bg-muted p-4 text-sm">
                <p class="flex items-center gap-1.5 font-medium">
                    <Landmark class="size-4 text-primary" />
                    Cara Membayar
                </p>
                <template v-if="bankAccounts.length > 0">
                    <p class="mt-1 text-muted-foreground">
                        Transfer ke salah satu rekening berikut:
                    </p>
                    <ul class="mt-2 grid gap-2">
                        <li
                            v-for="account in bankAccounts"
                            :key="account.id"
                            class="rounded-md border bg-background px-3 py-2 text-left"
                        >
                            <p class="font-medium">
                                {{ account.bank_name }}
                            </p>
                            <p class="tabular-nums">
                                {{ account.account_number }}
                            </p>
                            <p class="text-xs text-muted-foreground">
                                a.n. {{ account.account_holder }}
                            </p>
                        </li>
                    </ul>
                </template>
                <p class="mt-2 text-muted-foreground">
                    Setelah transfer, unggah bukti transfer di
                    <Link :href="'/pesanan-saya'" class="font-medium underline"
                        >Pesanan Saya</Link
                    >
                    — pesanan yang sudah mengirim bukti tidak akan dibatalkan.
                </p>
                <p
                    class="mt-2 flex items-center gap-1.5 rounded-md border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-700 dark:border-amber-800 dark:bg-amber-950 dark:text-amber-300"
                >
                    <Clock class="size-3.5 shrink-0" />
                    Selesaikan pembayaran dalam 24 jam — jika tidak, pesanan
                    otomatis dibatalkan.
                </p>
            </div>
        </div>

        <div class="flex flex-wrap items-center justify-center gap-2">
            <Button as-child>
                <Link :href="'/buku'">Lanjut Belanja</Link>
            </Button>
            <Button variant="outline" as-child>
                <a
                    :href="invoiceRoute(order.id).url"
                    target="_blank"
                    rel="noopener"
                >
                    <Printer class="size-4" />
                    Cetak Invoice
                </a>
            </Button>
        </div>
    </div>
</template>

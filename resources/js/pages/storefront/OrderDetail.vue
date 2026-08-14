<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import {
    CheckCircle2,
    Clock,
    FileImage,
    Landmark,
    MapPin,
    Printer,
    Truck,
    Upload,
    Wallet,
} from '@lucide/vue';
import { computed, ref } from 'vue';
import MyOrderController from '@/actions/App/Http/Controllers/MyOrderController';
import Money from '@/components/Money.vue';
import StatusBadge from '@/components/StatusBadge.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import CustomerLayout from '@/layouts/customer/CustomerLayout.vue';
import { invoice as invoiceRoute } from '@/routes/my-orders';

type OrderItem = {
    id: string;
    judul_snapshot: string;
    edition_snapshot: string | null;
    qty: number;
    price_original: number;
    promo_discount_amount: number;
    tier_discount_amount: number;
    price_final: number;
};

type Order = {
    id: string;
    no_order: string;
    nama_pembeli: string;
    alamat: string | null;
    provinsi: string | null;
    kabupaten_kota: string | null;
    kecamatan: string | null;
    kelurahan: string | null;
    kode_pos: string | null;
    metode_bayar: string;
    total: number;
    shipping_cost: number;
    ekspedisi: string | null;
    ongkir_estimasi: string | null;
    status: string;
    payment_status: string;
    awb: string | null;
    biteship_courier_link: string | null;
    bukti_transfer_path: string | null;
    bukti_transfer_at: string | null;
    created_at: string;
    items: OrderItem[];
};

type BankAccount = {
    id: string;
    bank_name: string;
    account_number: string;
    account_holder: string;
};

const props = defineProps<{
    order: Order;
    statusOptions: Record<string, string>;
    bankAccounts: BankAccount[];
}>();

defineOptions({
    layout: CustomerLayout,
});

const selectedFile = ref('');

const subtotal = computed(() => props.order.total - props.order.shipping_cost);

const buktiUrl = computed(() =>
    props.order.bukti_transfer_path
        ? `/storage/${props.order.bukti_transfer_path}`
        : null,
);

const addressText = computed(() =>
    [
        props.order.alamat,
        props.order.kelurahan,
        props.order.kecamatan,
        props.order.kabupaten_kota,
        props.order.provinsi,
        props.order.kode_pos,
    ]
        .filter(Boolean)
        .join(', '),
);

function onFilePick(event: Event): void {
    const input = event.target as HTMLInputElement;
    selectedFile.value = input.files?.[0]?.name ?? '';
}

function statusVariant(
    status: string,
): 'success' | 'warning' | 'danger' | 'info' {
    if (status === 'menunggu_konfirmasi') {
        return 'warning';
    }

    if (status === 'selesai') {
        return 'success';
    }

    if (status === 'batal') {
        return 'danger';
    }

    return 'info';
}
</script>

<template>
    <Head :title="`Order ${order.no_order}`" />

    <div class="flex flex-col gap-6">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div class="flex flex-wrap items-center gap-2">
                <h1 class="font-mono text-2xl font-bold tracking-tight">
                    {{ order.no_order }}
                </h1>
                <StatusBadge
                    :variant="statusVariant(order.status)"
                    :label="statusOptions[order.status] ?? order.status"
                />
                <StatusBadge
                    :variant="
                        order.payment_status === 'lunas' ? 'success' : 'warning'
                    "
                    :label="
                        order.payment_status === 'lunas'
                            ? 'Lunas'
                            : 'Menunggu Pembayaran'
                    "
                />
            </div>
            <Button variant="outline" size="sm" as-child>
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

        <div class="grid gap-6 lg:grid-cols-3">
            <!-- Invoice preview -->
            <Card class="lg:col-span-2">
                <CardHeader>
                    <CardTitle class="text-base font-medium"
                        >Detail Pesanan</CardTitle
                    >
                </CardHeader>
                <CardContent class="flex flex-col gap-4">
                    <ul class="divide-y rounded-lg border">
                        <li
                            v-for="item in order.items"
                            :key="item.id"
                            class="flex items-start justify-between gap-3 px-3 py-2.5"
                        >
                            <div class="min-w-0">
                                <p class="text-sm font-medium">
                                    {{ item.judul_snapshot }}
                                </p>
                                <p class="text-xs text-muted-foreground">
                                    {{
                                        item.edition_snapshot ?? 'Cetakan ke-1'
                                    }}
                                    <template
                                        v-if="
                                            item.promo_discount_amount > 0 ||
                                            item.tier_discount_amount > 0
                                        "
                                    >
                                        ·
                                        <Money
                                            :value="item.price_original"
                                            class="line-through"
                                        />
                                    </template>
                                </p>
                            </div>
                            <div class="shrink-0 text-right">
                                <p class="text-sm font-medium tabular-nums">
                                    <Money :value="item.price_final" />
                                    <span class="text-muted-foreground">
                                        × {{ item.qty }}
                                    </span>
                                </p>
                                <p
                                    class="text-xs text-muted-foreground tabular-nums"
                                >
                                    <Money
                                        :value="item.price_final * item.qty"
                                    />
                                </p>
                            </div>
                        </li>
                    </ul>

                    <div class="flex flex-col gap-1 text-sm">
                        <div class="flex justify-between">
                            <span class="text-muted-foreground">Subtotal</span>
                            <span class="tabular-nums"
                                ><Money :value="subtotal"
                            /></span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-muted-foreground">Ongkir</span>
                            <span class="tabular-nums"
                                ><Money :value="order.shipping_cost"
                            /></span>
                        </div>
                        <div
                            class="flex justify-between border-t pt-2 font-semibold"
                        >
                            <span>Total</span>
                            <span class="tabular-nums"
                                ><Money :value="order.total"
                            /></span>
                        </div>
                    </div>

                    <div
                        class="grid gap-3 rounded-lg border bg-muted/30 p-3 text-sm"
                    >
                        <p class="flex items-start gap-2">
                            <MapPin
                                class="mt-0.5 size-4 shrink-0 text-muted-foreground"
                            />
                            <span>{{ addressText }}</span>
                        </p>
                        <p class="flex items-center gap-2">
                            <Truck
                                class="size-4 shrink-0 text-muted-foreground"
                            />
                            <span>
                                {{ order.ekspedisi ?? 'Belum diisi' }}
                                <template v-if="order.ongkir_estimasi">
                                    · {{ order.ongkir_estimasi }}
                                </template>
                            </span>
                        </p>
                        <p class="flex items-center gap-2">
                            <Wallet
                                class="size-4 shrink-0 text-muted-foreground"
                            />
                            <span>{{ order.metode_bayar }}</span>
                        </p>
                        <p v-if="order.awb" class="flex items-center gap-2">
                            <span class="font-mono text-xs font-medium">
                                AWB: {{ order.awb }}
                            </span>
                            <a
                                v-if="order.biteship_courier_link"
                                :href="order.biteship_courier_link"
                                target="_blank"
                                rel="noopener"
                                class="text-xs font-medium underline decoration-dotted underline-offset-2 hover:text-primary"
                            >
                                Lacak
                            </a>
                        </p>
                    </div>
                </CardContent>
            </Card>

            <!-- Pembayaran -->
            <div class="flex flex-col gap-4">
                <Card v-if="order.payment_status === 'menunggu'">
                    <CardHeader>
                        <CardTitle
                            class="flex items-center gap-2 text-base font-medium"
                        >
                            <Landmark class="size-4 text-primary" />
                            Cara Membayar
                        </CardTitle>
                    </CardHeader>
                    <CardContent class="flex flex-col gap-3 text-sm">
                        <template v-if="bankAccounts.length">
                            <ul class="grid gap-2">
                                <li
                                    v-for="account in bankAccounts"
                                    :key="account.id"
                                    class="rounded-lg border px-3 py-2"
                                >
                                    <p class="font-medium">
                                        {{ account.bank_name }}
                                    </p>
                                    <p class="font-mono tabular-nums">
                                        {{ account.account_number }}
                                    </p>
                                    <p class="text-xs text-muted-foreground">
                                        a.n. {{ account.account_holder }}
                                    </p>
                                </li>
                            </ul>
                        </template>
                        <p v-else class="text-xs text-muted-foreground">
                            Hubungi kami untuk instruksi pembayaran.
                        </p>

                        <!-- Preview bukti setelah upload -->
                        <div v-if="buktiUrl" class="flex flex-col gap-2">
                            <p
                                class="flex items-center gap-1.5 text-xs font-medium text-emerald-600"
                            >
                                <CheckCircle2 class="size-3.5" />
                                Bukti transfer terkirim — menunggu verifikasi
                                admin.
                            </p>
                            <img
                                :src="buktiUrl"
                                alt="Bukti transfer"
                                class="max-h-56 w-full rounded-lg border bg-muted object-contain"
                            />
                            <Button variant="outline" size="sm" as-child>
                                <a
                                    :href="buktiUrl"
                                    target="_blank"
                                    rel="noopener"
                                >
                                    <FileImage class="size-4" />
                                    Buka Bukti
                                </a>
                            </Button>
                        </div>

                        <!-- Upload bukti -->
                        <Form
                            v-else
                            :action="
                                MyOrderController.uploadBukti(order.id).url
                            "
                            method="post"
                            class="flex flex-col items-start gap-2"
                            v-slot="{ processing }"
                        >
                            <Label
                                for="bukti"
                                class="text-xs text-muted-foreground"
                                >Upload bukti transfer</Label
                            >
                            <div class="flex w-full items-center gap-2">
                                <Input
                                    id="bukti"
                                    type="file"
                                    name="bukti"
                                    accept="image/jpeg,image/png,image/webp"
                                    class="max-w-48 text-xs"
                                    :disabled="processing"
                                    @change="onFilePick"
                                />
                                <Button
                                    type="submit"
                                    size="sm"
                                    :disabled="processing"
                                >
                                    <Upload class="size-4" />
                                    {{
                                        processing
                                            ? 'Mengirim...'
                                            : 'Kirim Bukti'
                                    }}
                                </Button>
                            </div>
                            <p class="text-xs text-muted-foreground">
                                {{
                                    selectedFile ||
                                    'JPG/PNG/WebP maks 2MB — pesanan yang sudah mengirim bukti tidak akan dibatalkan otomatis.'
                                }}
                            </p>
                        </Form>

                        <p
                            class="flex items-center gap-1.5 rounded-md border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-700 dark:border-amber-800 dark:bg-amber-950 dark:text-amber-300"
                        >
                            <Clock class="size-3.5 shrink-0" />
                            Selesaikan pembayaran dalam 24 jam — jika tidak,
                            pesanan otomatis dibatalkan.
                        </p>
                    </CardContent>
                </Card>

                <Card v-else>
                    <CardContent class="flex flex-col gap-2 pt-6 text-sm">
                        <p
                            class="flex items-center gap-1.5 font-medium text-emerald-600"
                        >
                            <CheckCircle2 class="size-4" />
                            Pembayaran sudah dikonfirmasi lunas.
                        </p>
                        <p class="text-xs text-muted-foreground">
                            Pesanan diproses dan dikirim sesuai alamat
                            pengiriman.
                        </p>
                    </CardContent>
                </Card>
            </div>
        </div>
    </div>
</template>

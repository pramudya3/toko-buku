<script setup lang="ts">
defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Beranda', href: '/admin/dashboard' },
            { title: 'Pesanan', href: '/admin/orders' },
            { title: 'Detail' },
        ],
    },
});

import { Form, Head, Link } from '@inertiajs/vue3';
import {
    AlertTriangle,
    ArrowRight,
    Ban,
    CheckCircle2,
    CircleCheck,
    FileImage,
    PackageCheck,
    Printer,
    RefreshCw,
    Truck,
    XCircle,
} from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import OrderController from '@/actions/App/Http/Controllers/Admin/OrderController';
import CurrencyInput from '@/components/CurrencyInput.vue';
import Money from '@/components/Money.vue';
import StatusBadge from '@/components/StatusBadge.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
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

type OrderItem = {
    id: string;
    qty: number;
    price_original: number;
    promo_discount_amount: number;
    tier_discount_amount: number;
    price_final: number;
    edition_snapshot: string | null;
    harga_beli_snapshot: number | null;
    book: {
        id: string;
        judul: string;
        kode_sku: string | null;
        cover_url: string | null;
    };
};

type Order = {
    id: string;
    no_order: string;
    nama_pembeli: string;
    alamat: string | null;
    metode_bayar: string;
    sumber_pembelian: string | null;
    total: number;
    shipping_cost: number;
    is_dropship: boolean;
    warehouse_origin: string | null;
    status: string;
    payment_status: string;
    ekspedisi: string | null;
    ongkir_estimasi: number | null;
    courier_service_code: string | null;
    biteship_order_id: string | null;
    shipping_collection_method: string | null;
    awb: string | null;
    biteship_status: string | null;
    biteship_label_url: string | null;
    bukti_transfer_path: string | null;
    bukti_transfer_at: string | null;
    created_at: string;
    user: {
        id: string;
        name: string;
        whatsapp_number: string | null;
        status_pelanggan: string;
    } | null;
    items: OrderItem[];
    dropshipper: {
        id: string;
        end_customer_name: string;
        end_customer_whatsapp: string | null;
        end_customer_address: string | null;
    } | null;
    cash_flows: Array<{
        id: string;
        entry_date: string;
        flow_type: string;
        amount: number;
        description: string | null;
    }>;
};

type ShippingCourier = {
    courier_code: string;
    courier_name: string;
    service_code: string;
    service_name: string;
};

type Props = {
    order: Order;
    statusOptions: Record<string, string>;
    couriers: Record<string, string>;
    shippingCouriers: ShippingCourier[];
    paymentMethods: Record<string, string>;
    salesChannels: Record<string, string>;
    warehouseOptions: Record<string, string>;
    storeTelepon: string;
    storeAlamat: string;
};

const props = defineProps<Props>();

// Origin toko belum lengkap → booking Biteship akan ditolak.
const originIncomplete = computed(
    () => !props.storeTelepon.trim() || !props.storeAlamat.trim(),
);

// ── Pengiriman Biteship ──
const processShipOpen = ref(false);
const pickupDate = ref('');
const pickupTime = ref('');
const collectionMethod = ref(
    props.order.shipping_collection_method ?? 'pickup',
);

// Channel website = alur penuh (transfer → ongkir → booking → pickup).
const isWebsite = computed(() => props.order.sumber_pembelian === 'website');

// Order website + lunas + diproses → boleh booking kurir / sinkronisasi.
// Basis "belum lengkap" adalah biteship_order_id (AWB bisa terbit asinkron).
const canBookShipping = computed(
    () =>
        isWebsite.value &&
        props.order.status === 'diproses' &&
        props.order.payment_status === 'lunas' &&
        (!props.order.biteship_order_id || !props.order.awb),
);

const buktiUrl = computed(() =>
    props.order.bukti_transfer_path
        ? `/storage/${props.order.bukti_transfer_path}`
        : null,
);

// Dialog "Proses & Kirim" — website: proses + booking + pickup;
// non-website: proses saja. Sudah booked tapi AWB kosong → sinkronisasi.
const dialogTitle = computed(() => {
    if (!isWebsite.value) {
        return `Proses Order ${props.order.no_order}`;
    }

    if (props.order.status !== 'menunggu_konfirmasi') {
        return props.order.biteship_order_id
            ? `Sinkronisasi & Jadwalkan ${props.order.no_order}`
            : `Buat Pengiriman ${props.order.no_order}`;
    }

    return `Proses & Kirim ${props.order.no_order}`;
});

// ── Dialog Proses Order ──
const processCourier = ref(props.order.ekspedisi ?? '');
const processService = ref(props.order.courier_service_code ?? '');

const processServices = computed(() =>
    props.shippingCouriers.filter(
        (courier) => courier.courier_code === processCourier.value,
    ),
);

watch(
    processCourier,
    () => {
        const available = processServices.value;
        const current = processService.value;

        if (available.some((service) => service.service_code === current)) {
            return;
        }

        // Kode layanan kadang berbeda case antar API Biteship — coba match
        // case-insensitive dulu sebelum menyerah ke placeholder.
        const caseInsensitive = available.find(
            (service) =>
                service.service_code.toLowerCase() === current.toLowerCase(),
        );

        if (caseInsensitive) {
            processService.value = caseInsensitive.service_code;

            return;
        }

        // Hanya satu layanan tersedia → pilih otomatis; banyak layanan tanpa
        // kecocokan → biarkan placeholder agar admin sadar memilih.
        processService.value =
            available.length === 1 ? (available[0].service_code ?? '') : '';
    },
    { immediate: true },
);

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

const subtotal = computed(() =>
    props.order.items.reduce(
        (sum, item) => sum + item.price_final * item.qty,
        0,
    ),
);

/**
 * Laba per item = (harga final - HPP) × qty.
 */
function itemProfit(item: OrderItem): number {
    return (item.price_final - (item.harga_beli_snapshot ?? 0)) * item.qty;
}

const totalProfit = computed(() =>
    props.order.items.reduce((sum, item) => sum + itemProfit(item), 0),
);

const paymentLabel = computed(() => props.paymentMethods ?? {});

const flowTypeVariant: Record<
    string,
    'success' | 'warning' | 'danger' | 'info' | 'neutral'
> = {
    revenue: 'success',
    shipping: 'info',
    refund: 'danger',
};
</script>

<template>
    <Head :title="`Order ${order.no_order}`" />

    <div class="flex flex-col gap-4 p-4 md:p-6">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div class="flex items-center gap-3">
                <h1 class="text-xl font-semibold tracking-tight">
                    {{ order.no_order }}
                </h1>
                <StatusBadge
                    :variant="statusVariant[order.status] ?? 'neutral'"
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
                <StatusBadge
                    v-if="order.is_dropship"
                    variant="info"
                    label="Dropship"
                />
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <!-- Cetak nota/invoice penjualan -->
                <Button variant="outline" size="sm" as-child>
                    <a
                        :href="OrderController.invoice(order.id).url"
                        target="_blank"
                        rel="noopener"
                    >
                        <Printer class="size-4" />
                        Cetak Nota
                    </a>
                </Button>
                <!-- Konfirmasi pembayaran hanya untuk order website -->
                <Form
                    v-if="order.payment_status === 'menunggu' && isWebsite"
                    v-bind="OrderController.confirmPayment.form(order.id)"
                    v-slot="{ processing }"
                >
                    <Button
                        type="submit"
                        variant="outline"
                        :disabled="processing"
                    >
                        <CircleCheck class="size-4" />
                        Konfirmasi Pembayaran
                    </Button>
                </Form>
                <!-- Website: konfirmasi lunas + proses + booking + pickup -->
                <Button
                    v-if="order.status === 'menunggu_konfirmasi' && isWebsite"
                    @click="processShipOpen = true"
                >
                    <PackageCheck class="size-4" />
                    Proses & Kirim
                </Button>
                <!-- Non-website (toko/marketplace): proses saja -->
                <Button
                    v-if="order.status === 'menunggu_konfirmasi' && !isWebsite"
                    @click="processShipOpen = true"
                >
                    <PackageCheck class="size-4" />
                    Proses Order
                </Button>
                <!-- Booking / sinkronisasi untuk order website diproses -->
                <Button
                    v-if="canBookShipping"
                    variant="outline"
                    @click="processShipOpen = true"
                >
                    <PackageCheck class="size-4" />
                    {{
                        order.biteship_order_id && !order.awb
                            ? 'Sinkronisasi & Jadwalkan'
                            : 'Buat Pengiriman'
                    }}
                </Button>
                <!-- Website: tandai dikirim (wajib AWB sudah terbit) -->
                <Form
                    v-if="order.status === 'diproses' && isWebsite && order.awb"
                    v-bind="OrderController.updateStatus.form(order.id)"
                    v-slot="{ processing }"
                >
                    <input type="hidden" name="status" value="dikirim" />
                    <Button
                        type="submit"
                        variant="outline"
                        :disabled="processing"
                    >
                        <Truck class="size-4" />
                        Tandai Dikirim
                    </Button>
                </Form>
                <!-- Non-website: langsung selesai dari diproses -->
                <Form
                    v-if="order.status === 'diproses' && !isWebsite"
                    v-bind="OrderController.updateStatus.form(order.id)"
                    v-slot="{ processing }"
                >
                    <input type="hidden" name="status" value="selesai" />
                    <Button type="submit" :disabled="processing">
                        <CheckCircle2 class="size-4" />
                        Selesaikan Pesanan
                    </Button>
                </Form>
                <Form
                    v-if="order.status === 'dikirim'"
                    v-bind="OrderController.updateStatus.form(order.id)"
                    v-slot="{ processing }"
                >
                    <input type="hidden" name="status" value="selesai" />
                    <Button type="submit" :disabled="processing">
                        <CheckCircle2 class="size-4" />
                        Tandai Selesai
                    </Button>
                </Form>
                <!-- Batal hanya dari menunggu_konfirmasi/diproses (barang belum dikirim) -->
                <Form
                    v-if="
                        ['menunggu_konfirmasi', 'diproses'].includes(
                            order.status,
                        )
                    "
                    v-bind="OrderController.updateStatus.form(order.id)"
                    v-slot="{ processing }"
                >
                    <input type="hidden" name="status" value="batal" />
                    <Button
                        type="submit"
                        variant="destructive"
                        :disabled="processing"
                    >
                        <Ban class="size-4" />
                        Batalkan
                    </Button>
                </Form>
            </div>
        </div>

        <div class="grid gap-4 lg:grid-cols-3">
            <!-- Item pesanan -->
            <Card class="lg:col-span-2">
                <CardHeader>
                    <CardTitle class="text-base font-medium"
                        >Item Pesanan</CardTitle
                    >
                </CardHeader>
                <CardContent class="p-0">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Buku</TableHead>
                                <TableHead>Qty</TableHead>
                                <TableHead>Harga Asli</TableHead>
                                <TableHead>Diskon Promo</TableHead>
                                <TableHead>Diskon Tier</TableHead>
                                <TableHead class="text-right"
                                    >Harga Final</TableHead
                                >
                                <TableHead class="text-right">HPP</TableHead>
                                <TableHead class="text-right">Laba</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            <TableRow
                                v-for="item in order.items"
                                :key="item.id"
                            >
                                <TableCell>
                                    <p class="font-medium">
                                        {{ item.book.judul }}
                                    </p>
                                    <p class="text-xs text-muted-foreground">
                                        {{ item.book.kode_sku }}
                                    </p>
                                    <p
                                        v-if="item.edition_snapshot"
                                        class="text-xs text-muted-foreground"
                                    >
                                        {{ item.edition_snapshot }}
                                    </p>
                                </TableCell>
                                <TableCell>{{ item.qty }}</TableCell>
                                <TableCell
                                    ><Money :value="item.price_original"
                                /></TableCell>
                                <TableCell>
                                    <span
                                        v-if="item.promo_discount_amount > 0"
                                        class="text-red-600"
                                        >-<Money
                                            :value="item.promo_discount_amount"
                                    /></span>
                                    <span v-else class="text-muted-foreground"
                                        >—</span
                                    >
                                </TableCell>
                                <TableCell>
                                    <span
                                        v-if="item.tier_discount_amount > 0"
                                        class="text-red-600"
                                        >-<Money
                                            :value="item.tier_discount_amount"
                                    /></span>
                                    <span v-else class="text-muted-foreground"
                                        >—</span
                                    >
                                </TableCell>
                                <TableCell
                                    class="text-right font-medium tabular-nums"
                                >
                                    <Money :value="item.price_final" />
                                </TableCell>
                                <TableCell
                                    class="text-right text-muted-foreground tabular-nums"
                                >
                                    <Money
                                        :value="item.harga_beli_snapshot ?? 0"
                                    />
                                </TableCell>
                                <TableCell
                                    class="text-right font-medium tabular-nums"
                                    :class="
                                        itemProfit(item) > 0
                                            ? 'text-green-600'
                                            : 'text-destructive'
                                    "
                                >
                                    <Money :value="itemProfit(item)" />
                                </TableCell>
                            </TableRow>
                        </TableBody>
                    </Table>
                    <div class="flex flex-col gap-1 border-t px-4 py-3 text-sm">
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
                            <span>Grand Total</span>
                            <span class="tabular-nums"
                                ><Money :value="order.total"
                            /></span>
                        </div>
                        <div
                            class="flex justify-between border-t pt-2 font-semibold"
                            :class="
                                totalProfit > 0
                                    ? 'text-green-600'
                                    : 'text-destructive'
                            "
                        >
                            <span>Estimasi Laba (HPP)</span>
                            <span class="tabular-nums"
                                ><Money :value="totalProfit"
                            /></span>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <!-- Info order -->
            <div class="flex flex-col gap-4">
                <Card>
                    <CardHeader>
                        <CardTitle class="text-base font-medium"
                            >Informasi Order</CardTitle
                        >
                    </CardHeader>
                    <CardContent class="grid gap-3 text-sm">
                        <div>
                            <p class="text-xs text-muted-foreground">Pembeli</p>
                            <p class="font-medium">{{ order.nama_pembeli }}</p>
                            <p
                                v-if="order.user"
                                class="text-xs text-muted-foreground"
                            >
                                {{ order.user.name }} ·
                                {{ order.user.status_pelanggan }}
                            </p>
                        </div>
                        <div>
                            <p class="text-xs text-muted-foreground">
                                Metode Bayar
                            </p>
                            <p>
                                {{
                                    paymentLabel[order.metode_bayar] ??
                                    order.metode_bayar
                                }}
                            </p>
                        </div>
                        <div>
                            <p class="text-xs text-muted-foreground">
                                Pembelian Dari
                            </p>
                            <p>
                                {{
                                    props.salesChannels[
                                        order.sumber_pembelian ?? ''
                                    ] ??
                                    order.sumber_pembelian ??
                                    '—'
                                }}
                            </p>
                        </div>
                        <div>
                            <p class="text-xs text-muted-foreground">
                                Alamat Pengiriman
                            </p>
                            <p class="whitespace-pre-wrap">
                                {{ order.alamat ?? '—' }}
                            </p>
                        </div>
                        <div>
                            <p class="text-xs text-muted-foreground">
                                Ekspedisi
                            </p>
                            <p>
                                {{
                                    couriers[order.ekspedisi ?? ''] ??
                                    order.ekspedisi ??
                                    'Belum diisi'
                                }}
                            </p>
                        </div>
                        <div>
                            <p class="text-xs text-muted-foreground">
                                Gudang Asal
                            </p>
                            <p>
                                {{
                                    warehouseOptions[
                                        order.warehouse_origin ?? ''
                                    ] ?? 'Belum dipilih'
                                }}
                            </p>
                        </div>
                        <div>
                            <p class="text-xs text-muted-foreground">Dibuat</p>
                            <p>
                                {{
                                    new Date(order.created_at).toLocaleString(
                                        'id-ID',
                                        { timeZone: 'Asia/Jakarta' },
                                    )
                                }}
                            </p>
                        </div>
                    </CardContent>
                </Card>

                <Card v-if="order.dropshipper">
                    <CardHeader>
                        <CardTitle class="text-base font-medium"
                            >Data Dropship (end-customer)</CardTitle
                        >
                    </CardHeader>
                    <CardContent class="grid gap-3 text-sm">
                        <div>
                            <p class="text-xs text-muted-foreground">Nama</p>
                            <p class="font-medium">
                                {{ order.dropshipper.end_customer_name }}
                            </p>
                        </div>
                        <div>
                            <p class="text-xs text-muted-foreground">
                                WhatsApp
                            </p>
                            <p class="tabular-nums">
                                {{
                                    order.dropshipper.end_customer_whatsapp ??
                                    '—'
                                }}
                            </p>
                        </div>
                        <div>
                            <p class="text-xs text-muted-foreground">Alamat</p>
                            <p class="whitespace-pre-wrap">
                                {{
                                    order.dropshipper.end_customer_address ??
                                    '—'
                                }}
                            </p>
                        </div>
                    </CardContent>
                </Card>

                <!-- Bukti transfer dari customer -->
                <Card v-if="buktiUrl">
                    <CardHeader>
                        <CardTitle class="text-base font-medium"
                            >Bukti Transfer</CardTitle
                        >
                    </CardHeader>
                    <CardContent class="flex flex-col gap-3">
                        <img
                            :src="buktiUrl"
                            :alt="`Bukti transfer ${order.no_order}`"
                            class="max-h-48 w-full rounded-lg border bg-muted object-contain"
                        />
                        <Button variant="outline" size="sm" as-child>
                            <a :href="buktiUrl" target="_blank" rel="noopener">
                                <FileImage class="size-4" />
                                Buka Bukti
                            </a>
                        </Button>
                        <p
                            v-if="order.bukti_transfer_at"
                            class="text-xs text-muted-foreground"
                        >
                            Diunggah
                            {{
                                new Date(
                                    order.bukti_transfer_at,
                                ).toLocaleString('id-ID', {
                                    timeZone: 'Asia/Jakarta',
                                })
                            }}
                        </p>
                    </CardContent>
                </Card>

                <!-- Pengiriman Biteship -->
                <Card v-if="order.sumber_pembelian !== 'toko'">
                    <CardHeader>
                        <CardTitle
                            class="flex items-center gap-2 text-base font-medium"
                        >
                            <Truck class="size-4" />
                            Pengiriman Biteship
                        </CardTitle>
                    </CardHeader>
                    <CardContent class="flex flex-col gap-3 text-sm">
                        <!-- Origin toko belum lengkap → Biteship menolak booking -->
                        <div
                            v-if="originIncomplete && !order.biteship_order_id"
                            class="flex items-start gap-2 rounded-lg border border-destructive/50 bg-destructive/5 px-3 py-2.5 text-xs text-destructive"
                        >
                            <AlertTriangle class="mt-0.5 size-4 shrink-0" />
                            <div>
                                <p class="font-medium">
                                    Data alamat pengirim belum lengkap.
                                </p>
                                <p>
                                    Lengkapi nomor telepon & alamat toko di
                                    <Link
                                        :href="'/admin/settings/lembaga'"
                                        class="font-semibold underline underline-offset-2"
                                    >
                                        Pengaturan → Lembaga
                                    </Link>
                                    sebelum membuat pengiriman.
                                </p>
                            </div>
                        </div>

                        <!-- Belum booking (biteship_order_id kosong) -->
                        <template v-if="!order.biteship_order_id">
                            <p
                                v-if="canBookShipping"
                                class="text-muted-foreground"
                            >
                                Buat pengiriman untuk menerbitkan AWB & label
                                (saldo Biteship terpotong).
                            </p>
                            <p v-else class="text-xs text-muted-foreground">
                                {{
                                    order.status === 'diproses' &&
                                    order.payment_status === 'menunggu'
                                        ? 'Konfirmasi pembayaran terlebih dahulu untuk membuat pengiriman.'
                                        : 'Pengiriman dapat dibuat saat order berstatus Diproses.'
                                }}
                            </p>
                            <Button
                                v-if="canBookShipping"
                                size="sm"
                                @click="processShipOpen = true"
                            >
                                <PackageCheck class="size-4" />
                                Buat Pengiriman
                            </Button>
                        </template>

                        <!-- Sudah booking -->
                        <template v-else>
                            <div
                                class="flex items-center justify-between gap-3"
                            >
                                <div>
                                    <p class="text-xs text-muted-foreground">
                                        AWB
                                    </p>
                                    <p
                                        class="font-mono text-base font-semibold"
                                    >
                                        {{
                                            order.awb ??
                                            'Menunggu AWB dari kurir…'
                                        }}
                                    </p>
                                </div>
                                <StatusBadge
                                    v-if="order.biteship_status"
                                    variant="info"
                                    :label="order.biteship_status"
                                />
                            </div>
                            <Button
                                v-if="!order.awb && canBookShipping"
                                size="sm"
                                variant="outline"
                                @click="processShipOpen = true"
                            >
                                <RefreshCw class="size-4" />
                                Sinkronisasi & Jadwalkan
                            </Button>
                            <p class="text-xs text-muted-foreground">
                                Metode penyerahan:
                                {{
                                    order.shipping_collection_method ===
                                    'drop_off'
                                        ? 'Antar ke agen ekspedisi'
                                        : 'Dijemput kurir'
                                }}
                            </p>
                            <div class="flex flex-wrap gap-2">
                                <Button
                                    v-if="order.biteship_label_url"
                                    variant="outline"
                                    size="sm"
                                    as-child
                                >
                                    <a
                                        :href="
                                            OrderController.shippingLabel(
                                                order.id,
                                            ).url
                                        "
                                        target="_blank"
                                        rel="noopener"
                                    >
                                        <Printer class="size-4" />
                                        Print Label
                                    </a>
                                </Button>
                                <Form
                                    v-bind="
                                        OrderController.refreshShipping.form(
                                            order.id,
                                        )
                                    "
                                >
                                    <Button
                                        type="submit"
                                        variant="outline"
                                        size="sm"
                                    >
                                        <RefreshCw class="size-4" />
                                        Refresh Status
                                    </Button>
                                </Form>
                                <Form
                                    v-bind="
                                        OrderController.cancelShipping.form(
                                            order.id,
                                        )
                                    "
                                    v-slot="{ processing }"
                                >
                                    <Button
                                        type="submit"
                                        variant="destructive"
                                        size="sm"
                                        :disabled="processing"
                                    >
                                        <XCircle class="size-4" />
                                        Batalkan Pengiriman
                                    </Button>
                                </Form>
                            </div>
                        </template>
                    </CardContent>
                </Card>

                <Card v-if="order.cash_flows.length">
                    <CardHeader>
                        <CardTitle class="text-base font-medium"
                            >Arus Kas Terkait</CardTitle
                        >
                    </CardHeader>
                    <CardContent class="grid gap-2 text-sm">
                        <div
                            v-for="flow in order.cash_flows"
                            :key="flow.id"
                            class="flex items-center justify-between rounded-lg border px-3 py-2"
                        >
                            <div>
                                <StatusBadge
                                    :variant="
                                        flowTypeVariant[flow.flow_type] ??
                                        'neutral'
                                    "
                                    :label="flow.flow_type"
                                />
                                <p class="mt-1 text-xs text-muted-foreground">
                                    {{ flow.description }}
                                </p>
                            </div>
                            <span class="font-medium tabular-nums"
                                ><Money :value="flow.amount"
                            /></span>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </div>

        <!-- Dialog Proses & Kirim (konfirmasi lunas + proses + booking + pickup) -->
        <Dialog v-model:open="processShipOpen">
            <DialogContent class="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>{{ dialogTitle }}</DialogTitle>
                    <DialogDescription>
                        <template
                            v-if="
                                order.status === 'menunggu_konfirmasi' &&
                                isWebsite
                            "
                        >
                            Konfirmasi lunas, isi ongkir & ekspedisi, lalu
                            terbitkan pengiriman — semua dalam satu langkah.
                        </template>
                        <template
                            v-else-if="
                                order.status === 'menunggu_konfirmasi' &&
                                !isWebsite
                            "
                        >
                            Isi ongkir & tentukan gudang asal. Status berubah
                            menjadi <b>Diproses</b>.
                        </template>
                        <template v-else>
                            Terbitkan AWB & label; saldo Biteship terpotong
                            sesuai tarif.
                        </template>
                    </DialogDescription>
                </DialogHeader>

                <Form
                    v-bind="OrderController.processAndShip.form(order.id)"
                    class="grid gap-4"
                    v-slot="{ errors, processing }"
                    @success="processShipOpen = false"
                >
                    <!-- Konfirmasi lunas (hanya website saat pembayaran menunggu) -->
                    <div
                        v-if="order.payment_status === 'menunggu' && isWebsite"
                        class="flex items-center gap-2"
                    >
                        <Checkbox
                            id="konfirmasi_lunas"
                            name="konfirmasi_lunas"
                            value="1"
                        />
                        <Label
                            for="konfirmasi_lunas"
                            class="leading-tight font-normal"
                        >
                            Tandai pembayaran <b>lunas</b>
                        </Label>
                    </div>

                    <template v-if="order.status === 'menunggu_konfirmasi'">
                        <div class="grid gap-2">
                            <Label for="shipping_cost"
                                >Ongkir Final (Rp) *</Label
                            >
                            <CurrencyInput
                                id="shipping_cost"
                                name="shipping_cost"
                                :default-value="
                                    order.shipping_cost || undefined
                                "
                                required
                            />
                            <span
                                v-if="errors.shipping_cost"
                                class="text-sm text-destructive"
                                >{{ errors.shipping_cost }}</span
                            >
                        </div>
                        <div v-if="isWebsite" class="grid gap-2">
                            <Label for="ekspedisi">Ekspedisi</Label>
                            <Select v-model="processCourier">
                                <SelectTrigger id="ekspedisi">
                                    <SelectValue
                                        placeholder="Pilih ekspedisi"
                                    />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem
                                        v-for="(label, value) in couriers"
                                        :key="value"
                                        :value="value"
                                    >
                                        {{ label }}
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                            <input
                                type="hidden"
                                name="ekspedisi"
                                :value="processCourier"
                            />
                            <span
                                v-if="errors.ekspedisi"
                                class="text-sm text-destructive"
                                >{{ errors.ekspedisi }}</span
                            >
                        </div>
                        <div
                            v-if="isWebsite && processServices.length"
                            class="grid gap-2"
                        >
                            <Label for="courier_service_code">Layanan</Label>
                            <Select v-model="processService">
                                <SelectTrigger id="courier_service_code">
                                    <SelectValue placeholder="Pilih layanan" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem
                                        v-for="service in processServices"
                                        :key="service.service_code"
                                        :value="service.service_code"
                                    >
                                        {{ service.service_name }}
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                            <input
                                type="hidden"
                                name="courier_service_code"
                                :value="processService"
                            />
                        </div>
                        <div class="grid gap-2">
                            <Label for="warehouse_origin">Gudang Asal *</Label>
                            <Select name="warehouse_origin">
                                <SelectTrigger id="warehouse_origin">
                                    <SelectValue placeholder="Pilih gudang" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem
                                        v-for="(
                                            label, value
                                        ) in warehouseOptions"
                                        :key="value"
                                        :value="value"
                                    >
                                        {{ label }}
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                            <span
                                v-if="errors.warehouse_origin"
                                class="text-sm text-destructive"
                                >{{ errors.warehouse_origin }}</span
                            >
                        </div>
                    </template>

                    <!-- Metode penyerahan (hanya website) -->
                    <template v-if="isWebsite">
                        <div class="grid gap-2">
                            <Label>Metode Penyerahan</Label>
                            <div class="grid grid-cols-2 gap-2">
                                <label
                                    class="flex cursor-pointer items-start gap-2 rounded-lg border p-3 text-sm transition-colors"
                                    :class="
                                        collectionMethod === 'pickup'
                                            ? 'border-primary bg-primary/5'
                                            : ''
                                    "
                                >
                                    <input
                                        type="radio"
                                        name="collection_method"
                                        value="pickup"
                                        v-model="collectionMethod"
                                        class="mt-0.5 accent-primary"
                                    />
                                    <span>
                                        <span class="block font-medium">
                                            Dijemput Kurir
                                        </span>
                                        <span
                                            class="text-xs text-muted-foreground"
                                        >
                                            Kurir mengambil di alamat toko
                                        </span>
                                    </span>
                                </label>
                                <label
                                    class="flex cursor-pointer items-start gap-2 rounded-lg border p-3 text-sm transition-colors"
                                    :class="
                                        collectionMethod === 'drop_off'
                                            ? 'border-primary bg-primary/5'
                                            : ''
                                    "
                                >
                                    <input
                                        type="radio"
                                        name="collection_method"
                                        value="drop_off"
                                        v-model="collectionMethod"
                                        class="mt-0.5 accent-primary"
                                    />
                                    <span>
                                        <span class="block font-medium">
                                            Antar ke Agen
                                        </span>
                                        <span
                                            class="text-xs text-muted-foreground"
                                        >
                                            Kirim sendiri ke ekspedisi
                                        </span>
                                    </span>
                                </label>
                            </div>
                        </div>

                        <div
                            v-if="collectionMethod === 'pickup'"
                            class="grid grid-cols-2 gap-3"
                        >
                            <div class="grid gap-2">
                                <Label for="pickup_date">Tanggal Jemput</Label>
                                <Input
                                    id="pickup_date"
                                    name="pickup_date"
                                    type="date"
                                    v-model="pickupDate"
                                />
                            </div>
                            <div class="grid gap-2">
                                <Label for="pickup_time">Jam</Label>
                                <Input
                                    id="pickup_time"
                                    name="pickup_time"
                                    type="time"
                                    v-model="pickupTime"
                                />
                            </div>
                        </div>
                    </template>

                    <DialogFooter>
                        <Button type="submit" :disabled="processing">
                            <ArrowRight class="size-4" />
                            {{
                                processing
                                    ? 'Memproses...'
                                    : !isWebsite
                                      ? 'Proses Order'
                                      : order.status === 'menunggu_konfirmasi'
                                        ? 'Proses & Kirim'
                                        : order.biteship_order_id && !order.awb
                                          ? 'Sinkronisasi & Jadwalkan'
                                          : 'Buat Pengiriman'
                            }}
                        </Button>
                    </DialogFooter>
                </Form>
            </DialogContent>
        </Dialog>
    </div>
</template>

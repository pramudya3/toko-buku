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

import { Form, Head } from '@inertiajs/vue3';
import {
    ArrowRight,
    Ban,
    Check,
    CheckCircle2,
    CircleCheck,
    Copy,
    FileImage,
    PackageCheck,
    Printer,
    Truck,
} from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import OrderController from '@/actions/App/Http/Controllers/Admin/OrderController';
import Money from '@/components/Money.vue';
import StatusBadge from '@/components/StatusBadge.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
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
    is_preorder: boolean;
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
    no_hp: string | null;
    email_pembeli: string | null;
    alamat: string | null;
    provinsi: string | null;
    kabupaten_kota: string | null;
    kecamatan: string | null;
    kelurahan: string | null;
    kode_pos: string | null;
    metode_bayar: string;
    sumber_pembelian: string | null;
    metode_pengambilan: string | null;
    total: number;
    shipping_cost: number;
    voucher_code_snapshot: string | null;
    voucher_scope_snapshot: string;
    voucher_discount_amount: number;
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
    biteship_courier_link: string | null;
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
};

type Props = {
    order: Order;
    statusOptions: Record<string, string>;
    couriers: Record<string, string>;
    paymentMethods: Record<string, string>;
    salesChannels: Record<string, string>;
    warehouseOptions: Record<string, string>;
    storeNamaLembaga: string;
    storeTelepon: string;
    storeEmail: string;
    storeAlamat: string;
    originPostalCode: string;
    trackingUrlTemplate: string;
};

const props = defineProps<Props>();

// Channel utama (website/toko) = proses penuh; marketplace = pencatatan.
const isMain = computed(() =>
    ['website', 'toko'].includes(props.order.sumber_pembelian ?? ''),
);

// Ambil sendiri — tanpa ongkir & tanpa pengiriman.
const isAmbil = computed(
    () => (props.order.metode_pengambilan ?? 'kirim') === 'ambil',
);

const buktiUrl = computed(() =>
    props.order.bukti_transfer_path
        ? `/storage/${props.order.bukti_transfer_path}`
        : null,
);

// ── Data Pengiriman (pengirim vs penerima) untuk input manual ke ekspedisi ──
const sender = computed(() => ({
    name: props.storeNamaLembaga,
    phone: props.storeTelepon,
    email: props.storeEmail,
    address: props.storeAlamat || props.originPostalCode,
}));

const receiver = computed(() => {
    if (props.order.is_dropship && props.order.dropshipper) {
        return {
            name: props.order.dropshipper.end_customer_name,
            phone: props.order.dropshipper.end_customer_whatsapp,
            address: props.order.dropshipper.end_customer_address,
        };
    }

    const parts = [
        props.order.alamat,
        props.order.kelurahan,
        props.order.kecamatan,
        props.order.kabupaten_kota,
        props.order.provinsi,
    ].filter((part): part is string => Boolean(part));

    const address = [...parts, props.order.kode_pos]
        .filter((part): part is string => Boolean(part))
        .join(', ');

    return {
        name: props.order.nama_pembeli,
        phone: props.order.no_hp,
        address,
    };
});

const receiverLabel = computed(
    () =>
        `Penerima${
            props.order.is_dropship && props.order.dropshipper
                ? ' (Dropship)'
                : ''
        }`,
);

const copied = ref<'sender' | 'receiver' | null>(null);

async function copyAddress(
    kind: 'sender' | 'receiver',
    value: string,
): Promise<void> {
    if (!value.trim()) {
        return;
    }

    try {
        await navigator.clipboard.writeText(value.trim());
        copied.value = kind;
        setTimeout(() => {
            if (copied.value === kind) {
                copied.value = null;
            }
        }, 1500);
    } catch {
        // Clipboard tidak tersedia (http) — biarkan admin menyalin manual.
    }
}

function formatAddressBlock(data: {
    name: string;
    phone: string | null;
    address: string | null;
}): string {
    return [data.name, data.phone, data.address].filter(Boolean).join('\n');
}

// ── Dialog Proses Order ──
const processOpen = ref(false);
const processCourier = ref(props.order.ekspedisi ?? '');

// ── Dialog Proses Kirim (input resi — pengiriman manual via ekspedisi) ──
const shippingOpen = ref(false);
const shippingAWB = ref('');
const shippingLink = ref('');
const shippingLinkEdited = ref(false);

watch(shippingAWB, (awb) => {
    if (!shippingLinkEdited.value && awb.trim() !== '') {
        // Auto-isi link tracking dari template (mis. lacak Wahana).
        shippingLink.value = props.trackingUrlTemplate.replace(
            '{awb}',
            awb.trim(),
        );
    }
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

// Total diskon (promo + tier) — ditampilkan bila > 0.
const totalDiscount = computed(() =>
    props.order.items.reduce(
        (sum, item) =>
            sum +
            (item.promo_discount_amount + item.tier_discount_amount) * item.qty,
        0,
    ),
);

const paymentLabel = computed(() => props.paymentMethods ?? {});
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
                <!-- Konfirmasi pembayaran (semua channel — fallback manual) -->
                <Form
                    v-if="order.payment_status === 'menunggu'"
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
                <!-- Proses Order: isi ongkir final + gudang asal -->
                <Button
                    v-if="order.status === 'menunggu_konfirmasi'"
                    @click="processOpen = true"
                >
                    <PackageCheck class="size-4" />
                    Proses Order
                </Button>
                <!-- Proses Kirim: input resi manual (via website ekspedisi) -->
                <Button
                    v-if="order.status === 'diproses' && isMain && !isAmbil"
                    variant="outline"
                    @click="shippingOpen = true"
                >
                    <Truck class="size-4" />
                    Proses Kirim
                </Button>
                <!-- Marketplace (pencatatan) / ambil sendiri: selesai langsung -->
                <Form
                    v-if="order.status === 'diproses' && (!isMain || isAmbil)"
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
            <!-- Kolom kiri (1/3): data pengiriman & ringkasan — sticky -->
            <div class="flex flex-col gap-4 self-start lg:sticky lg:top-20">
                <!-- Data Pengiriman: pengirim vs penerima (input manual ke ekspedisi) -->
                <Card v-if="!isAmbil">
                    <CardHeader>
                        <CardTitle class="text-base font-medium"
                            >Data Pengiriman</CardTitle
                        >
                    </CardHeader>
                    <CardContent class="flex flex-col gap-3">
                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <!-- Pengirim -->
                            <div class="rounded-lg border p-3">
                                <div
                                    class="flex items-center justify-between gap-2"
                                >
                                    <p
                                        class="text-xs font-semibold text-muted-foreground"
                                    >
                                        Pengirim
                                    </p>
                                    <Button
                                        variant="ghost"
                                        size="sm"
                                        class="h-7 gap-1 text-xs"
                                        @click="
                                            copyAddress(
                                                'sender',
                                                formatAddressBlock(sender),
                                            )
                                        "
                                    >
                                        <Copy
                                            v-if="copied !== 'sender'"
                                            class="size-3.5"
                                        />
                                        <Check
                                            v-else
                                            class="size-3.5 text-green-600"
                                        />
                                        {{
                                            copied === 'sender'
                                                ? 'Tersalin'
                                                : 'Salin'
                                        }}
                                    </Button>
                                </div>
                                <p class="mt-2 font-medium">
                                    {{ sender.name || '—' }}
                                </p>
                                <p v-if="sender.phone" class="text-sm">
                                    {{ sender.phone }}
                                </p>
                                <p
                                    v-if="sender.email"
                                    class="text-sm text-muted-foreground"
                                >
                                    {{ sender.email }}
                                </p>
                                <p
                                    class="mt-1 text-sm whitespace-pre-wrap text-muted-foreground"
                                >
                                    {{ sender.address || '—' }}
                                </p>
                            </div>

                            <!-- Penerima -->
                            <div class="rounded-lg border p-3">
                                <div
                                    class="flex items-center justify-between gap-2"
                                >
                                    <p
                                        class="text-xs font-semibold text-muted-foreground"
                                    >
                                        {{ receiverLabel }}
                                    </p>
                                    <Button
                                        variant="ghost"
                                        size="sm"
                                        class="h-7 gap-1 text-xs"
                                        @click="
                                            copyAddress(
                                                'receiver',
                                                formatAddressBlock(receiver),
                                            )
                                        "
                                    >
                                        <Copy
                                            v-if="copied !== 'receiver'"
                                            class="size-3.5"
                                        />
                                        <Check
                                            v-else
                                            class="size-3.5 text-green-600"
                                        />
                                        {{
                                            copied === 'receiver'
                                                ? 'Tersalin'
                                                : 'Salin'
                                        }}
                                    </Button>
                                </div>
                                <p class="mt-2 font-medium">
                                    {{ receiver.name || '—' }}
                                </p>
                                <p v-if="receiver.phone" class="text-sm">
                                    {{ receiver.phone }}
                                </p>
                                <p
                                    class="mt-1 text-sm whitespace-pre-wrap text-muted-foreground"
                                >
                                    {{ receiver.address || '—' }}
                                </p>
                            </div>
                        </div>
                        <p class="text-xs text-muted-foreground">
                            Data di atas diinput manual ke website ekspedisi
                            (mis. Wahana) saat membuat pengiriman.
                        </p>
                    </CardContent>
                </Card>

                <!-- Pengiriman (resi manual via website ekspedisi) -->
                <Card v-if="!isAmbil">
                    <CardHeader>
                        <CardTitle
                            class="flex items-center gap-2 text-base font-medium"
                        >
                            <Truck class="size-4" />
                            Pengiriman
                        </CardTitle>
                    </CardHeader>
                    <CardContent class="flex flex-col gap-3 text-sm">
                        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
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
                                    No. Resi
                                </p>
                                <p
                                    v-if="order.awb"
                                    class="font-mono font-semibold"
                                >
                                    {{ order.awb }}
                                </p>
                                <p v-else class="text-muted-foreground">
                                    {{
                                        order.status === 'diproses'
                                            ? 'Belum diisi'
                                            : '—'
                                    }}
                                </p>
                            </div>
                        </div>

                        <div v-if="order.awb" class="flex flex-wrap gap-2">
                            <Button
                                v-if="order.biteship_courier_link"
                                variant="outline"
                                size="sm"
                                as-child
                            >
                                <a
                                    :href="order.biteship_courier_link"
                                    target="_blank"
                                    rel="noopener"
                                >
                                    <Truck class="size-4" />
                                    Lacak Paket
                                </a>
                            </Button>
                        </div>

                        <p
                            v-if="order.status === 'diproses'"
                            class="text-xs text-muted-foreground"
                        >
                            Kirim barang via ekspedisi (mis. Wahana), lalu input
                            No. Resi lewat tombol "Proses Kirim".
                        </p>
                    </CardContent>
                </Card>

                <!-- Ringkasan (Informasi Order compact) -->
                <Card>
                    <CardHeader>
                        <CardTitle class="text-base font-medium"
                            >Ringkasan</CardTitle
                        >
                    </CardHeader>
                    <CardContent class="grid gap-3 text-sm sm:grid-cols-2">
                        <div>
                            <p class="text-xs text-muted-foreground">Pembeli</p>
                            <p class="font-medium">{{ order.nama_pembeli }}</p>
                            <p v-if="order.no_hp" class="text-sm">
                                {{ order.no_hp }}
                            </p>
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

                <!-- Data Dropship (end-customer) -->
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
            </div>

            <!-- Kolom kanan (2/3): item pesanan + arus kas -->
            <div class="flex flex-col gap-4 lg:col-span-2">
                <!-- Item pesanan -->
                <Card>
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
                                        >HPP</TableHead
                                    >
                                    <TableHead class="text-right"
                                        >Laba</TableHead
                                    >
                                    <TableHead class="text-right"
                                        >Total</TableHead
                                    >
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
                                            <span
                                                v-if="item.is_preorder"
                                                class="ml-1 rounded-full bg-sky-100 px-1.5 py-0.5 text-[10px] font-semibold text-sky-800"
                                                title="Item pre-order menunggu stok"
                                            >
                                                Pre-Order
                                            </span>
                                        </p>
                                        <p
                                            class="text-xs text-muted-foreground"
                                        >
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
                                            v-if="
                                                item.promo_discount_amount > 0
                                            "
                                            class="text-red-600"
                                            >-<Money
                                                :value="
                                                    item.promo_discount_amount
                                                "
                                        /></span>
                                        <span
                                            v-else
                                            class="text-muted-foreground"
                                            >—</span
                                        >
                                    </TableCell>
                                    <TableCell>
                                        <span
                                            v-if="item.tier_discount_amount > 0"
                                            class="text-red-600"
                                            >-<Money
                                                :value="
                                                    item.tier_discount_amount
                                                "
                                        /></span>
                                        <span
                                            v-else
                                            class="text-muted-foreground"
                                            >—</span
                                        >
                                    </TableCell>
                                    <TableCell
                                        class="text-right text-muted-foreground tabular-nums"
                                    >
                                        <Money
                                            :value="
                                                item.harga_beli_snapshot ?? 0
                                            "
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
                                    <TableCell
                                        class="text-right font-semibold tabular-nums"
                                    >
                                        <Money
                                            :value="item.price_final * item.qty"
                                        />
                                    </TableCell>
                                </TableRow>
                            </TableBody>
                        </Table>
                        <!-- Total gaya nota (struk) -->
                        <div
                            class="ml-auto flex max-w-sm flex-col gap-1.5 border-t px-4 py-3 text-sm"
                        >
                            <div
                                class="flex items-baseline justify-between gap-4"
                            >
                                <span class="text-muted-foreground"
                                    >Subtotal</span
                                >
                                <span class="tabular-nums"
                                    ><Money :value="subtotal"
                                /></span>
                            </div>
                            <div
                                v-if="totalDiscount > 0"
                                class="flex items-baseline justify-between gap-4 text-destructive"
                            >
                                <span>Total Diskon</span>
                                <span class="tabular-nums"
                                    >-<Money :value="totalDiscount"
                                /></span>
                            </div>
                            <div
                                v-if="order.voucher_discount_amount > 0"
                                class="flex items-baseline justify-between gap-4 text-destructive"
                            >
                                <span
                                    >Voucher
                                    {{ order.voucher_code_snapshot ?? 'Diskon'
                                    }}<template
                                        v-if="
                                            order.voucher_scope_snapshot ===
                                            'ongkir'
                                        "
                                    >
                                        (ongkir)</template
                                    ></span
                                >
                                <span class="tabular-nums"
                                    >-<Money
                                        :value="order.voucher_discount_amount"
                                /></span>
                            </div>
                            <div
                                v-if="!isAmbil"
                                class="flex items-baseline justify-between gap-4"
                            >
                                <span class="text-muted-foreground"
                                    >Ongkir</span
                                >
                                <span class="tabular-nums"
                                    ><Money :value="order.shipping_cost"
                                /></span>
                            </div>
                            <div class="border-t border-dashed"></div>
                            <div
                                class="flex items-baseline justify-between gap-4 text-base font-bold"
                            >
                                <span>Grand Total</span>
                                <span class="tabular-nums"
                                    ><Money :value="order.total"
                                /></span>
                            </div>
                            <div
                                class="flex items-baseline justify-between gap-4"
                                :class="
                                    totalProfit > 0
                                        ? 'text-green-600'
                                        : 'text-destructive'
                                "
                            >
                                <span class="font-medium"
                                    >Estimasi Laba (HPP)</span
                                >
                                <span class="font-medium tabular-nums"
                                    ><Money :value="totalProfit"
                                /></span>
                            </div>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </div>

        <!-- Dialog Proses Order -->
        <Dialog v-model:open="processOpen">
            <DialogContent class="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>Proses Order {{ order.no_order }}</DialogTitle>
                    <DialogDescription>
                        Isi ongkir final dan tentukan gudang asal. Status
                        berubah menjadi <b>Diproses</b>.
                    </DialogDescription>
                </DialogHeader>

                <Form
                    v-bind="OrderController.process.form(order.id)"
                    class="grid gap-4"
                    v-slot="{ errors, processing }"
                    @success="processOpen = false"
                >
                    <p
                        v-if="isAmbil"
                        class="rounded-md bg-muted px-3 py-2 text-xs text-muted-foreground"
                    >
                        Ambil sendiri — tanpa ongkir.
                    </p>
                    <p
                        v-else
                        class="rounded-md bg-muted px-3 py-2 text-xs text-muted-foreground"
                    >
                        Ongkir memakai nilai dari pesanan: Rp
                        {{ order.shipping_cost.toLocaleString('id-ID') }}.
                    </p>
                    <input
                        type="hidden"
                        name="shipping_cost"
                        :value="order.shipping_cost"
                    />
                    <div v-if="isMain && !isAmbil" class="grid gap-2">
                        <Label for="ekspedisi">Ekspedisi</Label>
                        <Select v-model="processCourier">
                            <SelectTrigger id="ekspedisi">
                                <SelectValue placeholder="Pilih ekspedisi" />
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
                    <div class="grid gap-2">
                        <Label for="warehouse_origin">Gudang Asal *</Label>
                        <Select name="warehouse_origin">
                            <SelectTrigger id="warehouse_origin">
                                <SelectValue placeholder="Pilih gudang" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem
                                    v-for="(label, value) in warehouseOptions"
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

                    <DialogFooter>
                        <Button type="submit" :disabled="processing">
                            <ArrowRight class="size-4" />
                            {{ processing ? 'Memproses...' : 'Proses Order' }}
                        </Button>
                    </DialogFooter>
                </Form>
            </DialogContent>
        </Dialog>

        <!-- Dialog Proses Kirim (pengiriman manual — input No. Resi) -->
        <Dialog v-model:open="shippingOpen">
            <DialogContent class="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle
                        >Proses Kirim — {{ order.no_order }}</DialogTitle
                    >
                    <DialogDescription>
                        Barang sudah diserahkan ke ekspedisi? Input No. Resi
                        dari website ekspedisi (mis. Wahana). Status berubah
                        menjadi
                        <b>Dikirim</b>.
                    </DialogDescription>
                </DialogHeader>

                <Form
                    v-bind="OrderController.updateStatus.form(order.id)"
                    class="grid gap-4"
                    v-slot="{ errors, processing }"
                    @success="shippingOpen = false"
                >
                    <input type="hidden" name="status" value="dikirim" />
                    <div class="grid gap-2">
                        <Label for="shipping_awb">No. Resi *</Label>
                        <Input
                            id="shipping_awb"
                            name="awb"
                            v-model="shippingAWB"
                            placeholder="Contoh: 120000123456"
                            required
                        />
                        <span
                            v-if="errors.awb"
                            class="text-sm text-destructive"
                            >{{ errors.awb }}</span
                        >
                    </div>
                    <div class="grid gap-2">
                        <Label for="shipping_link"
                            >Link Tracking (opsional)</Label
                        >
                        <Input
                            id="shipping_link"
                            name="biteship_courier_link"
                            type="url"
                            v-model="shippingLink"
                            placeholder="https://www.wahana.com/lacak-kiriman?noresi=..."
                            @input="shippingLinkEdited = true"
                        />
                        <span
                            v-if="errors.biteship_courier_link"
                            class="text-sm text-destructive"
                            >{{ errors.biteship_courier_link }}</span
                        >
                        <p class="text-xs text-muted-foreground">
                            Terisi otomatis mengikuti No. Resi; bisa diubah.
                        </p>
                    </div>

                    <DialogFooter>
                        <Button type="submit" :disabled="processing">
                            <Truck class="size-4" />
                            {{ processing ? 'Menyimpan...' : 'Proses Kirim' }}
                        </Button>
                    </DialogFooter>
                </Form>
            </DialogContent>
        </Dialog>
    </div>
</template>

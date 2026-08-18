<script setup lang="ts">
/**
 * Detail pesanan proto-d — /pcd/pesanan-saya/{order}. Desain Flat:
 * timeline status, rincian item, pengiriman/pengambilan, pembayaran +
 * upload bukti transfer.
 */
import { Form, Head, Link } from '@inertiajs/vue3';
import {
    Check,
    CheckCircle2,
    Clock,
    FileImage,
    Landmark,
    MapPin,
    PackageCheck,
    Printer,
    Truck,
    Upload,
    Wallet,
} from '@lucide/vue';
import { computed, ref } from 'vue';
import MyOrderPcdController from '@/actions/App/Http/Controllers/MyOrderPcdController';
import Money from '@/components/Money.vue';
import StatusBadge from '@/components/StatusBadge.vue';
import StorefrontPcdLayout from '@/layouts/customer/StorefrontPcdLayout.vue';
import { invoice as invoiceRoute } from '@/routes/pcd/my-orders';

type OrderItem = {
    id: string;
    judul_snapshot: string;
    is_preorder: boolean;
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
    sumber_pembelian: string | null;
    metode_pengambilan: string | null;
    total: number;
    shipping_cost: number;
    voucher_code_snapshot: string | null;
    voucher_scope_snapshot: string;
    voucher_discount_amount: number;
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
    layout: StorefrontPcdLayout,
});

const selectedFile = ref('');

const subtotal = computed(() => props.order.total - props.order.shipping_cost);

const subtotalBeforeVoucher = computed(
    () => subtotal.value + props.order.voucher_discount_amount,
);

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

const createdDate = computed(() =>
    new Date(props.order.created_at).toLocaleDateString('id-ID', {
        day: '2-digit',
        month: 'long',
        year: 'numeric',
        timeZone: 'Asia/Jakarta',
    }),
);

// ── Timeline status ──
const ORDER_STEPS = [
    { key: 'menunggu_konfirmasi', label: 'Menunggu Konfirmasi' },
    { key: 'diproses', label: 'Diproses' },
    { key: 'dikirim', label: 'Dikirim' },
    { key: 'selesai', label: 'Selesai' },
] as const;

const isCancelled = computed(() => props.order.status === 'batal');
const hasPreorderItems = computed(() =>
    props.order.items.some((item) => item.is_preorder),
);

const isAmbil = computed(
    () => (props.order.metode_pengambilan ?? 'kirim') === 'ambil',
);

const currentStepIndex = computed(() => {
    const index = ORDER_STEPS.findIndex(
        (step) => step.key === props.order.status,
    );

    return index === -1 ? 0 : index;
});

function isStepDone(index: number): boolean {
    return (
        index < currentStepIndex.value ||
        (props.order.status === 'selesai' && index === currentStepIndex.value)
    );
}

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

    <div class="mx-auto max-w-6xl px-4 pt-14 pb-24 md:px-6 md:pt-20 md:pb-32">
        <Link
            href="/pcd/pesanan-saya"
            class="inline-flex min-h-12 items-center text-sm font-medium text-gray-500 transition-colors hover:text-flat-primary"
        >
            ← Kembali ke Pesanan Saya
        </Link>

        <div class="mt-4 flex flex-wrap items-center justify-between gap-3">
            <div class="flex flex-wrap items-center gap-2">
                <h1 class="font-mono text-2xl font-extrabold tracking-tight">
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
                <p class="w-full text-sm text-gray-500">
                    Dibuat {{ createdDate }}
                </p>
            </div>
            <a
                :href="invoiceRoute(order.id).url"
                target="_blank"
                rel="noopener"
                class="inline-flex min-h-11 items-center gap-2 rounded-md border-2 border-flat-border bg-white px-4 text-sm font-semibold transition-all duration-200 hover:bg-flat-muted focus-visible:ring-2 focus-visible:ring-flat-primary focus-visible:outline-none"
            >
                <Printer class="size-4" aria-hidden="true" />
                Cetak Invoice
            </a>
        </div>

        <div
            v-if="hasPreorderItems && order.status === 'menunggu_konfirmasi'"
            class="mt-6 flex items-start gap-2 rounded-lg bg-sky-50 p-3 text-sm text-sky-800"
        >
            <Clock class="mt-0.5 size-4 shrink-0" />
            <p>
                Pesanan ini berisi item
                <strong>Pre-Order</strong>. Pesanan akan diproses setelah stok
                tersedia — Anda akan kami kabari saat stok tiba.
            </p>
        </div>

        <div class="mt-6 grid gap-6 lg:grid-cols-3">
            <!-- Timeline status -->
            <div
                class="self-start rounded-lg bg-flat-muted p-6 lg:sticky lg:top-24"
            >
                <h2 class="text-base font-bold">Status Pesanan</h2>
                <ol v-if="!isCancelled" class="mt-5 flex flex-col">
                    <li
                        v-for="(step, index) in ORDER_STEPS"
                        :key="step.key"
                        class="flex gap-3"
                    >
                        <div class="flex flex-col items-center">
                            <span
                                class="z-10 flex size-4 shrink-0 items-center justify-center rounded-full"
                                :class="
                                    isStepDone(index)
                                        ? 'bg-flat-primary'
                                        : index === currentStepIndex
                                          ? 'bg-flat-accent ring-4 ring-flat-accent/25'
                                          : 'bg-flat-border'
                                "
                            >
                                <Check
                                    v-if="isStepDone(index)"
                                    class="size-2.5 text-white"
                                />
                            </span>
                            <span
                                v-if="index < ORDER_STEPS.length - 1"
                                class="mt-1 w-0.5 flex-1 bg-flat-border"
                            />
                        </div>
                        <div class="pb-6">
                            <p
                                class="text-sm font-bold"
                                :class="
                                    index <= currentStepIndex
                                        ? ''
                                        : 'text-gray-500'
                                "
                            >
                                {{ step.label }}
                            </p>
                        </div>
                    </li>
                </ol>
                <div
                    v-else
                    class="flex items-start gap-2 rounded-md bg-red-50 px-3 py-2.5"
                >
                    <span class="mt-1 flex size-3.5 shrink-0 rounded-full bg-red-700" />
                    <div>
                        <p class="text-sm font-bold text-red-700">
                            Dibatalkan
                        </p>
                        <p class="text-xs text-gray-500">
                            Pesanan ini dibatalkan.
                        </p>
                    </div>
                </div>
            </div>

            <!-- Kolom kanan: item + pengiriman + pembayaran -->
            <div class="flex flex-col gap-6 lg:col-span-2">
                <!-- Item Pesanan -->
                <div class="rounded-lg bg-flat-muted p-6">
                    <h2 class="text-base font-bold">Item Pesanan</h2>
                    <ul class="mt-4 divide-y-2 divide-flat-border rounded-lg bg-white">
                        <li
                            v-for="item in order.items"
                            :key="item.id"
                            class="flex items-start justify-between gap-3 px-4 py-3"
                        >
                            <div class="min-w-0">
                                <p class="text-sm font-bold">
                                    {{ item.judul_snapshot }}
                                    <span
                                        v-if="item.is_preorder"
                                        class="ml-1 rounded-md bg-sky-100 px-1.5 py-0.5 text-[10px] font-bold text-sky-800"
                                    >
                                        Pre-Order
                                    </span>
                                </p>
                                <p class="text-xs text-gray-500">
                                    {{
                                        item.edition_snapshot ??
                                        'Cetakan ke-1'
                                    }}
                                    <template
                                        v-if="
                                            item.promo_discount_amount >
                                                0 ||
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
                                <p class="text-sm font-bold tabular-nums">
                                    <Money :value="item.price_final" />
                                    <span class="text-gray-500">
                                        × {{ item.qty }}
                                    </span>
                                </p>
                                <p
                                    class="text-xs text-gray-500 tabular-nums"
                                >
                                    <Money
                                        :value="item.price_final * item.qty"
                                    />
                                </p>
                            </div>
                        </li>
                    </ul>

                    <div class="mt-4 flex flex-col gap-1 text-sm">
                        <div class="flex justify-between">
                            <span class="text-gray-500">Subtotal</span>
                            <span class="tabular-nums"
                                ><Money :value="subtotalBeforeVoucher"
                            /></span>
                        </div>
                        <div
                            v-if="order.voucher_discount_amount > 0"
                            class="flex justify-between"
                        >
                            <span class="text-gray-500"
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
                            <span
                                class="font-medium text-red-700 tabular-nums"
                                ><Money
                                    :value="-order.voucher_discount_amount"
                            /></span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-500">Ongkir</span>
                            <span class="tabular-nums"
                                ><Money :value="order.shipping_cost"
                            /></span>
                        </div>
                        <div
                            class="flex justify-between border-t-2 border-flat-border pt-2 font-extrabold"
                        >
                            <span>Total</span>
                            <span class="tabular-nums text-flat-primary"
                                ><Money :value="order.total"
                            /></span>
                        </div>
                    </div>
                </div>

                <div class="grid gap-6 sm:grid-cols-2">
                    <!-- Pengiriman (kirim) / Ambil Sendiri -->
                    <div class="rounded-lg bg-flat-muted p-6">
                        <h2
                            class="flex items-center gap-2 text-base font-bold"
                        >
                            <Truck class="size-4 text-flat-primary" />
                            {{ isAmbil ? 'Ambil Sendiri' : 'Pengiriman' }}
                        </h2>
                        <div class="mt-3 flex flex-col gap-3 text-sm">
                            <template v-if="!isAmbil">
                                <p class="flex items-start gap-2">
                                    <MapPin
                                        class="mt-0.5 size-4 shrink-0 text-gray-500"
                                    />
                                    <span class="whitespace-pre-wrap">{{
                                        addressText
                                    }}</span>
                                </p>
                                <p class="flex items-center gap-2">
                                    <Truck
                                        class="size-4 shrink-0 text-gray-500"
                                    />
                                    <span>
                                        {{ order.ekspedisi ?? 'Belum diisi' }}
                                        <template v-if="order.ongkir_estimasi">
                                            · {{ order.ongkir_estimasi }}
                                        </template>
                                    </span>
                                </p>
                                <p
                                    v-if="order.awb"
                                    class="flex items-center gap-2"
                                >
                                    <span class="font-mono text-xs font-bold">
                                        AWB: {{ order.awb }}
                                    </span>
                                    <a
                                        v-if="order.biteship_courier_link"
                                        :href="order.biteship_courier_link"
                                        target="_blank"
                                        rel="noopener"
                                        class="text-xs font-semibold underline decoration-dotted underline-offset-2 hover:text-flat-primary"
                                    >
                                        Lacak
                                    </a>
                                </p>
                            </template>
                            <p
                                v-else
                                class="flex items-start gap-2 text-gray-500"
                            >
                                <PackageCheck class="mt-0.5 size-4 shrink-0" />
                                <span>
                                    Pesanan diambil langsung di toko — tanpa
                                    pengiriman & ongkir.
                                </span>
                            </p>
                        </div>
                    </div>

                    <!-- Pembayaran -->
                    <div class="rounded-lg bg-flat-muted p-6">
                        <h2
                            class="flex items-center gap-2 text-base font-bold"
                        >
                            <Landmark class="size-4 text-flat-primary" />
                            Pembayaran
                        </h2>
                        <div class="mt-3 flex flex-col gap-3 text-sm">
                            <!-- Pembelian di toko: tunai lunas / transfer menunggu -->
                            <template v-if="order.sumber_pembelian === 'toko'">
                                <p
                                    v-if="order.payment_status === 'lunas'"
                                    class="flex items-center gap-1.5 font-bold text-emerald-600"
                                >
                                    <CheckCircle2 class="size-4" />
                                    Dibayar di Toko
                                </p>
                                <p v-else class="flex flex-col gap-1">
                                    <span
                                        class="flex items-center gap-1.5 font-bold"
                                    >
                                        <Clock class="size-4" />
                                        Menunggu konfirmasi pembayaran
                                    </span>
                                    <span class="text-xs text-gray-500">
                                        Admin akan memverifikasi pembayaran
                                        transfer Anda.
                                    </span>
                                </p>
                                <p
                                    class="flex items-center gap-2 text-gray-500"
                                >
                                    <Wallet class="size-4 shrink-0" />
                                    <span
                                        >Metode: {{ order.metode_bayar }}</span
                                    >
                                </p>
                            </template>

                            <!-- Website: menunggu → cara membayar + upload bukti -->
                            <template
                                v-else-if="order.payment_status === 'menunggu'"
                            >
                                <p
                                    class="flex items-center gap-2 text-gray-500"
                                >
                                    <Wallet class="size-4 shrink-0" />
                                    <span
                                        >Metode: {{ order.metode_bayar }}</span
                                    >
                                </p>
                                <template v-if="bankAccounts.length">
                                    <ul class="grid gap-2">
                                        <li
                                            v-for="account in bankAccounts"
                                            :key="account.id"
                                            class="rounded-md bg-white px-3 py-2"
                                        >
                                            <p class="font-bold">
                                                {{ account.bank_name }}
                                            </p>
                                            <p class="font-mono tabular-nums">
                                                {{ account.account_number }}
                                            </p>
                                            <p class="text-xs text-gray-500">
                                                a.n.
                                                {{ account.account_holder }}
                                            </p>
                                        </li>
                                    </ul>
                                </template>
                                <p v-else class="text-xs text-gray-500">
                                    Hubungi kami untuk instruksi pembayaran.
                                </p>

                                <!-- Preview bukti setelah upload -->
                                <div
                                    v-if="buktiUrl"
                                    class="flex flex-col gap-2"
                                >
                                    <p
                                        class="flex items-center gap-1.5 text-xs font-bold text-emerald-600"
                                    >
                                        <CheckCircle2 class="size-3.5" />
                                        Bukti transfer terkirim — menunggu
                                        verifikasi admin.
                                    </p>
                                    <img
                                        :src="buktiUrl"
                                        alt="Bukti transfer"
                                        class="max-h-56 w-full rounded-md border-2 border-flat-border bg-white object-contain"
                                    />
                                    <a
                                        :href="buktiUrl"
                                        target="_blank"
                                        rel="noopener"
                                        class="inline-flex min-h-10 items-center justify-center gap-2 rounded-md border-2 border-flat-border bg-white px-3 text-xs font-semibold transition-all duration-200 hover:bg-flat-muted"
                                    >
                                        <FileImage class="size-4" />
                                        Buka Bukti
                                    </a>
                                </div>

                                <!-- Upload bukti -->
                                <Form
                                    v-else
                                    :action="
                                        MyOrderPcdController.uploadBukti(
                                            order.id,
                                        ).url
                                    "
                                    method="post"
                                    class="flex flex-col items-start gap-2"
                                    v-slot="{ processing }"
                                >
                                    <label
                                        for="bukti"
                                        class="text-xs text-gray-500"
                                        >Upload bukti transfer</label
                                    >
                                    <div class="flex w-full items-center gap-2">
                                        <input
                                            id="bukti"
                                            type="file"
                                            name="bukti"
                                            accept="image/jpeg,image/png,image/webp"
                                            class="max-w-48 rounded-md border-2 border-transparent bg-white px-2 py-2 text-xs outline-none focus:border-flat-primary disabled:opacity-60"
                                            :disabled="processing"
                                            @change="onFilePick"
                                        />
                                        <button
                                            type="submit"
                                            class="inline-flex min-h-10 items-center justify-center gap-2 rounded-md bg-flat-primary px-4 text-xs font-semibold text-white transition-all duration-200 hover:scale-105 hover:bg-flat-primary-dark disabled:opacity-60"
                                            :disabled="processing"
                                        >
                                            <Upload class="size-4" />
                                            {{
                                                processing
                                                    ? 'Mengirim...'
                                                    : 'Kirim Bukti'
                                            }}
                                        </button>
                                    </div>
                                    <p class="text-xs text-gray-500">
                                        {{
                                            selectedFile ||
                                            'JPG/PNG/WebP maks 2MB — pesanan yang sudah mengirim bukti tidak akan dibatalkan otomatis.'
                                        }}
                                    </p>
                                </Form>

                                <p
                                    class="flex items-center gap-1.5 rounded-md bg-amber-50 px-3 py-2 text-xs text-amber-700"
                                >
                                    <Clock class="size-3.5 shrink-0" />
                                    Selesaikan pembayaran dalam 24 jam — jika
                                    tidak, pesanan otomatis dibatalkan.
                                </p>
                            </template>

                            <!-- Website: lunas -->
                            <template v-else>
                                <p
                                    class="flex items-center gap-1.5 font-bold text-emerald-600"
                                >
                                    <CheckCircle2 class="size-4" />
                                    Pembayaran sudah dikonfirmasi lunas.
                                </p>
                                <p class="text-xs text-gray-500">
                                    Pesanan diproses dan dikirim sesuai alamat
                                    pengiriman.
                                </p>
                                <p
                                    class="flex items-center gap-2 text-gray-500"
                                >
                                    <Wallet class="size-4 shrink-0" />
                                    <span
                                        >Metode: {{ order.metode_bayar }}</span
                                    >
                                </p>
                            </template>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

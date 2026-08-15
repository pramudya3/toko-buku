<script setup lang="ts">
import { Form, Head, Link, router, useHttp } from '@inertiajs/vue3';
import { Loader2, Minus, PackageCheck, Plus, Trash2, Truck } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import { toast } from 'vue-sonner';
import CartController from '@/actions/App/Http/Controllers/CheckoutController';
import AddressFields from '@/components/AddressFields.vue';
import type { AddressValue } from '@/components/AddressFields.vue';
import EmptyState from '@/components/EmptyState.vue';
import InputError from '@/components/InputError.vue';
import Money from '@/components/Money.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
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
import CustomerLayout from '@/layouts/customer/CustomerLayout.vue';

type CartBook = {
    id: string;
    judul: string;
    kode_sku: string | null;
    harga: number;
    stok: number;
};

type CartEdition = {
    id: string;
    cetakan_ke: number;
    harga_jual: number;
    is_active: boolean;
    stok: number;
};

type CartItem = {
    book: CartBook;
    qty: number;
    edition_label: string | null;
    editions: CartEdition[];
    price_original: number;
    promo_discount: number;
    promo_name: string | null;
    bundle_discount: number;
    bundle_qty: number;
    tier_discount: number;
    unit_final: number;
    item_total: number;
};

type CartGroup = {
    key: string;
    name: string;
    discount_percent: number | null;
    items: CartItem[];
    subtotal: number;
    discount_total: number;
    total: number;
};

type UserInfo = {
    name: string;
    whatsapp_number: string | null;
    email: string | null;
    alamat: string | null;
    provinsi: string | null;
    kabupaten_kota: string | null;
    kecamatan: string | null;
    kelurahan: string | null;
    village_code: string | null;
    kode_pos: string | null;
};

type ShippingOption = {
    courier_code: string;
    courier_name: string;
    service_code: string;
    price: number;
    weight: number;
    estimation: string | null;
};

type BankAccount = {
    id: string;
    bank_name: string;
    account_number: string;
    account_holder: string;
};

const props = defineProps<{
    groups: CartGroup[];
    selectedGroups: string[];
    paymentOptions: Record<string, string>;
    bankAccounts: BankAccount[];
    user: UserInfo | null;
}>();

defineOptions({
    layout: CustomerLayout,
});

const selectedPayment = ref(Object.keys(props.paymentOptions)[0] ?? 'transfer');

// Grup yang diproses — dikelola server, client hanya baca dari prop.
// Tidak ada ref/client-state — semua komputasi langsung dari props.selectedGroups.

const activeItems = computed(() =>
    props.groups
        .filter((group) => props.selectedGroups.includes(group.key))
        .flatMap((group) => group.items),
);

// Subtotal dasar (harga asli × qty, sebelum diskon) — hanya grup terpilih.
const subtotal = computed(() =>
    activeItems.value.reduce(
        (sum, item) => sum + item.price_original * item.qty,
        0,
    ),
);

const promoDiscountTotal = computed(() =>
    activeItems.value.reduce(
        (sum, item) => sum + item.promo_discount * item.qty,
        0,
    ),
);

const bundleDiscountTotal = computed(() =>
    activeItems.value.reduce(
        // Diskon bundle hanya utk 1 SET pertama (bundle_qty eksemplar).
        (sum, item) => sum + item.bundle_discount * item.bundle_qty,
        0,
    ),
);

const tierDiscountTotal = computed(() =>
    activeItems.value.reduce(
        (sum, item) => sum + item.tier_discount * item.qty,
        0,
    ),
);

// Diskon per nama promo (harga satuan) — tampil seperti tabel promosi.
const promoDiscountsByName = computed(() => {
    const totals = new Map<string, number>();

    for (const item of activeItems.value) {
        if (item.promo_discount <= 0) {
            continue;
        }

        const name = item.promo_name ?? 'Promo';
        totals.set(
            name,
            (totals.get(name) ?? 0) + item.promo_discount * item.qty,
        );
    }

    return [...totals.entries()].map(([name, value]) => ({ name, value }));
});

// Diskon bundle per nama grup (= nama promo bundle) — grup yang dicentang saja.
const bundleDiscountsByName = computed(() => {
    const totals = new Map<string, number>();

    for (const group of props.groups) {
        if (!props.selectedGroups.includes(group.key)) {
            continue;
        }

        const total = group.items.reduce(
            (sum, item) => sum + item.bundle_discount * item.bundle_qty,
            0,
        );

        if (total > 0) {
            totals.set(group.name, (totals.get(group.name) ?? 0) + total);
        }
    }

    return [...totals.entries()].map(([name, value]) => ({ name, value }));
});

const totalDiscount = computed(
    () =>
        promoDiscountTotal.value +
        bundleDiscountTotal.value +
        tierDiscountTotal.value,
);

const totalAfterDiscount = computed(() => subtotal.value - totalDiscount.value);

// Tidak ada grup yang dipilih untuk diproses.
const hasActiveGroup = computed(() => props.selectedGroups.length > 0);

// Data pembeli — diikat via v-model agar tombol submit bisa dicek.
const namaPembeli = ref(props.user?.name ?? '');

// Tombol "Buat Pesanan" aktif hanya saat data pembeli + alamat lengkap.
const isFormComplete = computed(() => {
    const a = address.value;

    return (
        namaPembeli.value.trim() !== '' &&
        a.provinsi !== '' &&
        a.kabupaten_kota !== '' &&
        a.kecamatan !== '' &&
        a.kelurahan !== '' &&
        a.kode_pos !== '' &&
        a.alamat.trim() !== ''
    );
});

// ── Ongkos kirim (RajaOngkir) ──
const shippingCosts = ref<ShippingOption[]>([]);
const shippingWeight = ref<number | null>(null);
const ongkirLoading = ref(false);
const ongkirError = ref('');
const ongkirStale = ref(false);
const selectedCourier = ref('');

// Metode pengambilan: kirim (default) / ambil sendiri — ambil sendiri
// tidak perlu alamat & ongkir.
const fulfillmentMethod = ref<'kirim' | 'ambil'>('kirim');
const isAmbil = computed(() => fulfillmentMethod.value === 'ambil');

function chooseFulfillment(method: 'kirim' | 'ambil'): void {
    // Jangan clear hasil cek ongkir — balik ke "Kirim" mengembalikan ongkir
    // yang sudah dipilih (tanpa hit API tambahan). Total menghitung ongkir
    // hanya saat metode kirim.
    fulfillmentMethod.value = method;
}
function toggleGroup(key: string, checked: boolean) {
    router.post(
        CartController.toggleGroup().url,
        { group_key: key, checked },
        { preserveScroll: true, preserveState: true },
    );
}

const address = ref<AddressValue>({
    provinsi: props.user?.provinsi ?? '',
    kabupaten_kota: props.user?.kabupaten_kota ?? '',
    kecamatan: props.user?.kecamatan ?? '',
    kelurahan: props.user?.kelurahan ?? '',
    village_code: props.user?.village_code ?? '',
    kode_pos: props.user?.kode_pos ?? '',
    alamat: props.user?.alamat ?? '',
});

// useHttp: request HTTP standalone (JSON) — bukan useForm yang mengirim
// request Inertia (header X-Inertia) sehingga menolak respons JSON ongkir.
const ongkirRequest = useHttp<{
    village_name: string;
    district_name: string;
    selected_groups: string[];
}>();
let ongkirTimer: ReturnType<typeof setTimeout> | undefined;

// Value Select berbentuk "courier_code:service_code" — layanan tersimpan
// lewat hidden input, bukan hilang seperti sebelumnya.
const selectedShippingValue = ref('');

// Opsi yang benar-benar dipilih user (courier:service) — bukan asumsi
// layanan pertama dari kurir (bug lama: semua order tersimpan NULL).
const selectedShippingOption = computed(() => {
    if (selectedShippingValue.value !== '') {
        return (
            shippingCosts.value.find(
                (option) =>
                    `${option.courier_code}:${option.service_code}` ===
                    selectedShippingValue.value,
            ) ?? null
        );
    }

    // Fallback: belum pilih layanan → opsi pertama kurir terpilih.
    return (
        shippingCosts.value.find(
            (option) => option.courier_code === selectedCourier.value,
        ) ?? null
    );
});

const selectedServiceCode = computed(
    () => selectedShippingOption.value?.service_code ?? '',
);

const shippingCost = computed(() => selectedShippingOption.value?.price ?? 0);

// Alamat / keranjang / pilihan grup berubah → hasil ongkir lama tidak berlaku.
// Reset tanpa auto-fetch: hit API hanya saat tombol "Cek Ongkir" ditekan (hemat kuota).
watch(
    () => [
        address.value.kecamatan,
        address.value.kelurahan,
        props.groups,
        props.selectedGroups,
    ],
    () => {
        selectedCourier.value = '';
        selectedShippingValue.value = '';
        shippingCosts.value = [];
        shippingWeight.value = null;
        ongkirError.value = '';
    },
    { deep: true },
);

function checkOngkir(): void {
    if (!address.value.kode_pos) {
        ongkirError.value =
            'Pilih alamat lengkap (kecamatan & kelurahan) dulu.';

        return;
    }

    if (props.selectedGroups.length === 0) {
        ongkirError.value =
            'Centang minimal satu grup item untuk dihitung ongkirnya.';

        return;
    }

    clearTimeout(ongkirTimer);

    ongkirLoading.value = true;
    ongkirError.value = '';
    ongkirStale.value = false;

    ongkirTimer = setTimeout(() => {
        ongkirRequest.transform(() => ({
            postal_code: address.value.kode_pos,
            // Ongkir hanya dihitung untuk grup yang dipilih proses.
            selected_groups: props.selectedGroups,
        }));

        ongkirRequest.post(CartController.shippingCosts().url, {
            onSuccess: (data: unknown) => {
                const payload = data as {
                    weight_kg?: number;
                    costs?: ShippingOption[];
                };

                shippingWeight.value = payload.weight_kg ?? null;
                shippingCosts.value = payload.costs ?? [];

                // Kurir terpilih tidak tersedia di daftar baru → reset.
                if (
                    selectedCourier.value &&
                    !shippingCosts.value.some(
                        (option) =>
                            option.courier_code === selectedCourier.value,
                    )
                ) {
                    selectedCourier.value = '';
                    selectedShippingValue.value = '';
                }

                ongkirError.value =
                    shippingCosts.value.length === 0
                        ? 'Tidak ada ekspedisi tersedia untuk alamat ini.'
                        : '';
            },
            onError: (errors) => {
                ongkirError.value =
                    errors.message ?? 'Gagal menghitung ongkir, coba lagi.';
            },
            onFinish: () => {
                ongkirLoading.value = false;
            },
        });
    }, 200);
}

const grandTotal = computed(
    () => totalAfterDiscount.value + (isAmbil.value ? 0 : shippingCost.value),
);

/**
 * Keranjang berubah (qty/item) setelah cek ongkir → hasil basi, wajib
 * cek ulang (berat berbeda). Tanpa auto-call — hemat hit API.
 */
function invalidateOngkir(): void {
    if (shippingCosts.value.length === 0 && selectedCourier.value === '') {
        return;
    }

    selectedShippingValue.value = '';
    selectedCourier.value = '';
    shippingCosts.value = [];
    ongkirStale.value = true;
}

function updateQty(item: CartItem, delta: number) {
    const qty = item.qty + delta;

    invalidateOngkir();

    if (qty <= 0) {
        router.post(CartController.remove(item.book.id).url, undefined, {
            preserveScroll: true,
            // Pertahankan pilihan grup saat keranjang dimuat ulang — tanpa ini
            // checkbox reset ke semua dicentang setiap kali qty berubah.
            preserveState: true,
        });

        return;
    }

    // Qty maksimal = stok tersedia.
    if (qty > item.book.stok) {
        return;
    }

    router.post(
        CartController.updateQty(item.book.id).url,
        { qty },
        { preserveScroll: true, preserveState: true },
    );
}

function removeItem(item: CartItem) {
    router.post(CartController.remove(item.book.id).url, undefined, {
        preserveScroll: true,
        preserveState: true,
    });
}

function changeEdition(item: CartItem, editionId: string) {
    router.post(
        CartController.updateEdition(item.book.id).url,
        {
            book_edition_id: editionId,
        },
        {
            preserveScroll: true,
            preserveState: true,
        },
    );
}

// UUID cetakan yang sedang dipilih — dari label ("Cetakan ke-N") ke id asli.
function currentEditionId(item: CartItem): string | undefined {
    return item.editions.find(
        (e) => `Cetakan ke-${e.cetakan_ke}` === item.edition_label,
    )?.id;
}

function removeGroup(group: CartGroup) {
    router.post(
        CartController.removeGroup().url,
        { group_key: group.key },
        {
            preserveScroll: true,
            preserveState: true,
        },
    );
}

function onFormError() {
    toast.error(
        'Gagal memproses pesanan — periksa kembali isian yang wajib diisi.',
    );
}
</script>

<template>
    <Head title="Checkout" />

    <div class="flex flex-col gap-6 pb-36 lg:pb-0">
        <div>
            <h1 class="text-2xl font-bold tracking-tight">Checkout</h1>
            <p class="text-sm text-muted-foreground">
                Lengkapi data diri dan alamat pengiriman.
            </p>
        </div>

        <Form
            :action="CartController.store().url"
            method="post"
            class="flex flex-col gap-6"
            v-slot="{ errors, processing }"
            @error="onFormError"
        >
            <div
                v-if="groups.length"
                class="grid grid-cols-1 gap-6 lg:grid-cols-2"
            >
                <div class="flex flex-col gap-6">
                    <Card>
                        <CardContent class="flex flex-col gap-6">
                            <div>
                                <h2 class="text-sm font-semibold">
                                    Data Pembeli
                                </h2>
                                <div
                                    class="mt-3 grid grid-cols-1 gap-4 md:grid-cols-2"
                                >
                                    <div class="grid gap-2">
                                        <Label for="nama_pembeli"
                                            >Nama Lengkap *</Label
                                        >
                                        <Input
                                            id="nama_pembeli"
                                            name="nama_pembeli"
                                            v-model="namaPembeli"
                                            required
                                        />
                                        <InputError
                                            :message="errors.nama_pembeli"
                                        />
                                    </div>
                                    <div class="grid gap-2">
                                        <Label for="whatsapp_pembeli"
                                            >WhatsApp</Label
                                        >
                                        <Input
                                            id="whatsapp_pembeli"
                                            name="whatsapp_pembeli"
                                            :default-value="
                                                user?.whatsapp_number ??
                                                undefined
                                            "
                                            placeholder="08xxxxxxxxxx"
                                        />
                                    </div>
                                    <div class="grid gap-2 md:col-span-2">
                                        <Label for="email_pembeli"
                                            >Email (opsional)</Label
                                        >
                                        <Input
                                            id="email_pembeli"
                                            name="email_pembeli"
                                            type="email"
                                            :default-value="
                                                user?.email ?? undefined
                                            "
                                        />
                                        <InputError
                                            :message="errors.email_pembeli"
                                        />
                                    </div>
                                </div>
                            </div>
                            <div class="border-t pt-6">
                                <h2 class="text-sm font-semibold">
                                    Metode Pengambilan
                                </h2>
                                <div class="mt-3 grid grid-cols-2 gap-2">
                                    <button
                                        type="button"
                                        class="flex items-center gap-2 rounded-lg border p-3 text-left text-sm transition-colors"
                                        :class="
                                            !isAmbil
                                                ? 'border-primary bg-primary/5'
                                                : ''
                                        "
                                        @click="chooseFulfillment('kirim')"
                                    >
                                        <Truck class="size-4 shrink-0" />
                                        <span>
                                            <span class="block font-medium">
                                                Kirim
                                            </span>
                                            <span
                                                class="text-xs text-muted-foreground"
                                            >
                                                Via ekspedisi
                                            </span>
                                        </span>
                                    </button>
                                    <button
                                        type="button"
                                        class="flex items-center gap-2 rounded-lg border p-3 text-left text-sm transition-colors"
                                        :class="
                                            isAmbil
                                                ? 'border-primary bg-primary/5'
                                                : ''
                                        "
                                        @click="chooseFulfillment('ambil')"
                                    >
                                        <PackageCheck class="size-4 shrink-0" />
                                        <span>
                                            <span class="block font-medium">
                                                Ambil Sendiri
                                            </span>
                                            <span
                                                class="text-xs text-muted-foreground"
                                            >
                                                Di toko, tanpa ongkir
                                            </span>
                                        </span>
                                    </button>
                                </div>
                                <input
                                    type="hidden"
                                    name="metode_pengambilan"
                                    :value="fulfillmentMethod"
                                />
                            </div>

                            <div v-if="!isAmbil" class="border-t pt-6">
                                <h2 class="text-sm font-semibold">
                                    Alamat Pengiriman
                                </h2>
                                <div class="mt-3">
                                    <AddressFields
                                        v-model="address"
                                        :endpoint="'public'"
                                    />
                                </div>
                            </div>

                            <div class="border-t pt-6">
                                <h2 class="text-sm font-semibold">
                                    Pembayaran & Pengiriman
                                </h2>
                                <div
                                    class="mt-3 grid grid-cols-1 gap-4 md:grid-cols-2"
                                >
                                    <div class="grid gap-2">
                                        <Label for="metode_bayar"
                                            >Metode Bayar *</Label
                                        >
                                        <Select
                                            v-model="selectedPayment"
                                            name="metode_bayar"
                                        >
                                            <SelectTrigger id="metode_bayar">
                                                <SelectValue />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectItem
                                                    v-for="(
                                                        label, value
                                                    ) in paymentOptions"
                                                    :key="value"
                                                    :value="value"
                                                >
                                                    {{ label }}
                                                </SelectItem>
                                            </SelectContent>
                                        </Select>
                                        <InputError
                                            :message="errors.metode_bayar"
                                        />
                                        <div
                                            v-if="
                                                selectedPayment ===
                                                    'transfer' &&
                                                bankAccounts.length
                                            "
                                            class="mt-2 rounded-lg border bg-muted/40 px-3 py-2 text-xs text-muted-foreground"
                                        >
                                            <p
                                                class="mb-1 font-medium text-foreground"
                                            >
                                                Transfer ke rekening:
                                            </p>
                                            <p
                                                v-for="account in bankAccounts"
                                                :key="account.id"
                                                class="flex flex-wrap items-center gap-2"
                                            >
                                                <span class="font-medium">
                                                    {{ account.bank_name }}
                                                </span>
                                                <span
                                                    class="font-mono break-all"
                                                >
                                                    {{ account.account_number }}
                                                </span>
                                                <span
                                                    class="text-muted-foreground"
                                                >
                                                    a.n.
                                                    {{ account.account_holder }}
                                                </span>
                                            </p>
                                        </div>
                                    </div>
                                    <div
                                        v-if="!isAmbil"
                                        class="grid gap-2 md:col-span-2"
                                    >
                                        <Label for="ekspedisi"
                                            >Ekspedisi & Ongkir</Label
                                        >
                                        <div class="flex items-end gap-2">
                                            <div
                                                class="grid min-w-0 flex-1 gap-2"
                                            >
                                                <Select
                                                    v-model="
                                                        selectedShippingValue
                                                    "
                                                    :disabled="
                                                        ongkirLoading ||
                                                        shippingCosts.length ===
                                                            0
                                                    "
                                                    @update:model-value="
                                                        (val) => {
                                                            const value =
                                                                String(
                                                                    val ?? '',
                                                                );
                                                            const [courier] =
                                                                value.split(
                                                                    ':',
                                                                );
                                                            selectedShippingValue =
                                                                value;
                                                            selectedCourier =
                                                                courier ?? '';
                                                        }
                                                    "
                                                >
                                                    <SelectTrigger
                                                        id="ekspedisi"
                                                    >
                                                        <SelectValue
                                                            :placeholder="
                                                                ongkirLoading
                                                                    ? 'Menghitung ongkir...'
                                                                    : address.kelurahan
                                                                      ? 'Pilih ekspedisi'
                                                                      : 'Pilih alamat lengkap dulu'
                                                            "
                                                        />
                                                    </SelectTrigger>
                                                    <SelectContent
                                                        class="max-h-64"
                                                    >
                                                        <SelectItem
                                                            v-for="option in shippingCosts"
                                                            :key="`${option.courier_code}:${option.service_code}`"
                                                            :value="`${option.courier_code}:${option.service_code}`"
                                                        >
                                                            {{
                                                                option.courier_name
                                                            }}
                                                            — Rp
                                                            {{
                                                                option.price.toLocaleString(
                                                                    'id-ID',
                                                                )
                                                            }}
                                                            <span
                                                                v-if="
                                                                    option.estimation
                                                                "
                                                                class="text-xs text-muted-foreground"
                                                            >
                                                                ({{
                                                                    option.estimation
                                                                }})
                                                            </span>
                                                        </SelectItem>
                                                    </SelectContent>
                                                </Select>
                                                <input
                                                    type="hidden"
                                                    name="ekspedisi"
                                                    :value="selectedCourier"
                                                />
                                                <input
                                                    type="hidden"
                                                    name="courier_service_code"
                                                    :value="selectedServiceCode"
                                                />
                                            </div>
                                            <Button
                                                type="button"
                                                variant="outline"
                                                :disabled="
                                                    ongkirLoading ||
                                                    !address.kode_pos
                                                "
                                                @click="checkOngkir"
                                            >
                                                <Loader2
                                                    v-if="ongkirLoading"
                                                    class="size-4 animate-spin"
                                                />
                                                {{
                                                    ongkirLoading
                                                        ? 'Menghitung...'
                                                        : 'Cek Ongkir'
                                                }}
                                            </Button>
                                        </div>
                                        <p
                                            v-if="ongkirLoading"
                                            class="text-xs text-muted-foreground"
                                        >
                                            Menghitung ongkir ke
                                            {{ address.kelurahan }}...
                                        </p>
                                        <p
                                            v-else-if="ongkirError"
                                            class="text-xs text-destructive"
                                        >
                                            {{ ongkirError }}
                                        </p>
                                        <p
                                            v-else-if="ongkirStale"
                                            class="text-xs text-amber-600 dark:text-amber-400"
                                        >
                                            Berat berubah — tekan Cek Ongkir
                                            lagi.
                                        </p>
                                        <p
                                            v-else-if="
                                                shippingCosts.length &&
                                                shippingWeight !== null
                                            "
                                            class="text-xs text-muted-foreground"
                                        >
                                            Berat paket {{ shippingWeight }} kg
                                            ·
                                            {{ shippingCosts.length }} ekspedisi
                                            tersedia
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                </div>

                <div
                    class="order-first h-fit lg:sticky lg:top-20 lg:order-last"
                >
                    <Card>
                        <CardHeader>
                            <CardTitle class="text-base font-medium"
                                >Ringkasan Pesanan</CardTitle
                            >
                        </CardHeader>
                        <CardContent class="flex flex-col gap-4">
                            <!-- ── Grup item + checkbox pilih proses ── -->
                            <div
                                v-for="group in groups"
                                :key="group.key"
                                class="flex flex-col gap-2 rounded-lg border p-3 transition-opacity"
                                :class="
                                    !props.selectedGroups.includes(group.key) &&
                                    'opacity-60'
                                "
                            >
                                <div class="flex items-center gap-2">
                                    <label
                                        class="flex min-w-0 cursor-pointer items-center gap-2"
                                    >
                                        <Checkbox
                                            :model-value="
                                                props.selectedGroups.includes(
                                                    group.key,
                                                )
                                            "
                                            @update:model-value="
                                                (
                                                    v:
                                                        | boolean
                                                        | 'indeterminate',
                                                ) =>
                                                    toggleGroup(
                                                        group.key,
                                                        v === true,
                                                    )
                                            "
                                        />
                                        <span
                                            class="min-w-0 text-sm font-semibold"
                                        >
                                            {{ group.name }}
                                        </span>
                                    </label>
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        size="icon-sm"
                                        class="ml-auto size-8 shrink-0 text-destructive"
                                        title="Hapus seluruh grup"
                                        @click="removeGroup(group)"
                                    >
                                        <Trash2 class="size-4" />
                                    </Button>
                                </div>

                                <ul class="flex flex-col gap-2">
                                    <li
                                        v-for="item in group.items"
                                        :key="`${item.book.id}-${item.edition_label ?? ''}`"
                                        class="rounded-lg bg-muted/30 px-3 py-2.5"
                                    >
                                        <div
                                            class="flex items-start justify-between gap-2"
                                        >
                                            <p
                                                class="line-clamp-2 min-w-0 flex-1 text-sm font-medium"
                                            >
                                                {{ item.book.judul }}
                                            </p>
                                            <Button
                                                v-if="group.key === 'regular'"
                                                type="button"
                                                variant="ghost"
                                                size="icon-sm"
                                                class="size-7 shrink-0 text-destructive"
                                                title="Hapus item"
                                                @click="removeItem(item)"
                                            >
                                                <Trash2 class="size-3.5" />
                                            </Button>
                                        </div>
                                        <div
                                            class="mt-1.5 flex items-center justify-between gap-2"
                                        >
                                            <div
                                                class="flex min-w-0 items-center gap-1.5"
                                            >
                                                <Select
                                                    v-if="
                                                        group.key ===
                                                            'regular' &&
                                                        item.editions.length > 1
                                                    "
                                                    :model-value="
                                                        currentEditionId(item)
                                                    "
                                                    @update:model-value="
                                                        (val) =>
                                                            changeEdition(
                                                                item,
                                                                val as string,
                                                            )
                                                    "
                                                >
                                                    <SelectTrigger
                                                        class="h-7 w-auto max-w-44 min-w-0 gap-1 rounded-md border px-1.5 text-xs"
                                                    >
                                                        <SelectValue
                                                            placeholder="Cetakan"
                                                        />
                                                    </SelectTrigger>
                                                    <SelectContent>
                                                        <SelectItem
                                                            v-for="edition in item.editions"
                                                            :key="edition.id"
                                                            :value="edition.id"
                                                            :disabled="
                                                                edition.stok <=
                                                                0
                                                            "
                                                        >
                                                            Cetakan ke-{{
                                                                edition.cetakan_ke
                                                            }}
                                                            ·
                                                            {{
                                                                edition.harga_jual.toLocaleString(
                                                                    'id-ID',
                                                                )
                                                            }}
                                                        </SelectItem>
                                                    </SelectContent>
                                                </Select>
                                                <p
                                                    v-else
                                                    class="min-w-0 truncate text-xs text-muted-foreground"
                                                >
                                                    <template
                                                        v-if="
                                                            item.edition_label
                                                        "
                                                    >
                                                        {{ item.edition_label }}
                                                        ·
                                                    </template>
                                                    <Money
                                                        :value="
                                                            item.price_original
                                                        "
                                                    />
                                                    <span>
                                                        × {{ item.qty }}
                                                    </span>
                                                </p>
                                            </div>
                                            <div
                                                v-if="group.key === 'regular'"
                                                class="flex shrink-0 items-center gap-1 rounded-md border px-0.5 py-0.5"
                                            >
                                                <Button
                                                    type="button"
                                                    variant="ghost"
                                                    size="icon-sm"
                                                    class="size-6"
                                                    :disabled="item.qty <= 1"
                                                    @click="updateQty(item, -1)"
                                                >
                                                    <Minus class="size-3.5" />
                                                </Button>
                                                <span
                                                    class="w-6 text-center text-sm tabular-nums"
                                                    >{{ item.qty }}</span
                                                >
                                                <Button
                                                    type="button"
                                                    variant="ghost"
                                                    size="icon-sm"
                                                    class="size-6"
                                                    :disabled="
                                                        item.qty >=
                                                        item.book.stok
                                                    "
                                                    @click="updateQty(item, 1)"
                                                >
                                                    <Plus class="size-3.5" />
                                                </Button>
                                            </div>
                                            <Money
                                                :value="item.item_total"
                                                class="shrink-0 text-sm font-medium"
                                            />
                                        </div>
                                    </li>
                                </ul>
                            </div>

                            <!-- ── Ringkasan (grup tercentang saja) ── -->
                            <div
                                class="flex items-center justify-between border-t pt-3"
                            >
                                <span class="text-sm text-muted-foreground"
                                    >Subtotal</span
                                >
                                <Money
                                    :value="subtotal"
                                    class="font-semibold"
                                />
                            </div>
                            <div
                                v-for="discount in promoDiscountsByName"
                                :key="discount.name"
                                class="flex items-center justify-between text-sm"
                            >
                                <span class="text-muted-foreground">
                                    {{ discount.name }}
                                </span>
                                <Money
                                    :value="-discount.value"
                                    class="font-medium text-destructive"
                                />
                            </div>
                            <div
                                v-for="discount in bundleDiscountsByName"
                                :key="discount.name"
                                class="flex items-center justify-between text-sm"
                            >
                                <span class="text-muted-foreground">
                                    {{ discount.name }} (bundle)
                                </span>
                                <Money
                                    :value="-discount.value"
                                    class="font-medium text-destructive"
                                />
                            </div>
                            <div
                                v-if="tierDiscountTotal > 0"
                                class="flex items-center justify-between text-sm"
                            >
                                <span class="text-muted-foreground"
                                    >Diskon tier</span
                                >
                                <Money
                                    :value="-tierDiscountTotal"
                                    class="font-medium text-destructive"
                                />
                            </div>
                            <div
                                v-if="!isAmbil && selectedCourier"
                                class="flex items-center justify-between text-sm"
                            >
                                <span class="text-muted-foreground"
                                    >Ongkir ({{
                                        selectedShippingOption?.courier_name
                                    }})</span
                                >
                                <Money
                                    :value="shippingCost"
                                    class="font-medium"
                                />
                            </div>
                            <div
                                class="flex items-center justify-between border-t pt-3"
                            >
                                <span class="text-sm font-semibold">Total</span>
                                <Money
                                    :value="grandTotal"
                                    class="font-semibold"
                                />
                            </div>
                            <p
                                v-if="!hasActiveGroup"
                                class="text-xs font-medium text-destructive"
                            >
                                Pilih minimal 1 grup
                            </p>
                            <p
                                v-else-if="!isFormComplete"
                                class="text-xs font-medium text-destructive"
                            >
                                Lengkapi data pembeli & alamat.
                            </p>
                            <Button
                                type="submit"
                                size="lg"
                                class="hidden lg:inline-flex"
                                :disabled="
                                    processing ||
                                    !hasActiveGroup ||
                                    !isFormComplete
                                "
                            >
                                {{
                                    processing ? 'Memproses...' : 'Buat Pesanan'
                                }}
                            </Button>
                        </CardContent>
                    </Card>
                </div>
            </div>

            <!-- Bottom bar mobile: total + tombol submit selalu terlihat -->
            <div
                v-if="groups.length"
                class="fixed inset-x-0 bottom-16 z-50 border-t border-b bg-background/95 p-4 backdrop-blur lg:hidden"
            >
                <div
                    class="mx-auto flex max-w-2xl items-center justify-between gap-3"
                >
                    <div>
                        <p class="text-xs text-muted-foreground">Total</p>
                        <Money
                            :value="grandTotal"
                            class="text-lg font-semibold"
                        />
                    </div>
                    <Button
                        type="submit"
                        size="lg"
                        :disabled="
                            processing || !hasActiveGroup || !isFormComplete
                        "
                    >
                        {{ processing ? 'Memproses...' : 'Buat Pesanan' }}
                    </Button>
                </div>
                <span
                    v-if="!isFormComplete"
                    class="text-xs font-medium text-destructive"
                >
                    Lengkapi data pembeli & alamat.
                </span>
            </div>

            <EmptyState
                v-else
                icon="/img/empty-cart.png"
                title="Keranjang kosong"
                description="Belum ada buku di keranjang."
            >
                <Button size="sm" as-child>
                    <Link :href="'/buku'">Lihat Katalog</Link>
                </Button>
            </EmptyState>
            <InputError :message="errors.items" />
        </Form>
    </div>
</template>

<script setup lang="ts">
/**
 * Checkout proto-d — /pcd/checkout. Alur penuh yang sama dengan storefront
 * lama: grup bundle, pilihan proses, alamat (AddressFields), cek ongkir,
 * metode bayar, dan pembuatan order via CheckoutPcdController (POST /pcd/checkout).
 */
import { Form, Head, router, useHttp } from '@inertiajs/vue3';
import { Loader2, Minus, PackageCheck, Plus, Trash2, Truck } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import { toast } from 'vue-sonner';
import CartController from '@/actions/App/Http/Controllers/CheckoutController';
import CheckoutPcdController from '@/actions/App/Http/Controllers/CheckoutPcdController';
import AddressFields from '@/components/AddressFields.vue';
import type { AddressValue } from '@/components/AddressFields.vue';
import Money from '@/components/Money.vue';
import StorefrontPcdLayout from '@/layouts/customer/StorefrontPcdLayout.vue';

type CartBook = {
    id: string;
    judul: string;
    harga: number;
    stok: number;
    is_preorder: boolean;
};

type CartEdition = {
    id: string;
    cetakan_ke: number;
    harga_jual: number;
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

defineOptions({ layout: StorefrontPcdLayout });

const selectedPayment = ref(Object.keys(props.paymentOptions)[0] ?? 'transfer');

const activeItems = computed(() =>
    props.groups
        .filter((group) => props.selectedGroups.includes(group.key))
        .flatMap((group) => group.items),
);

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

const totalDiscount = computed(
    () =>
        promoDiscountTotal.value +
        bundleDiscountTotal.value +
        tierDiscountTotal.value,
);

const totalAfterDiscount = computed(() => subtotal.value - totalDiscount.value);
const hasActiveGroup = computed(() => props.selectedGroups.length > 0);

const namaPembeli = ref(props.user?.name ?? '');

const address = ref<AddressValue>({
    provinsi: props.user?.provinsi ?? '',
    kabupaten_kota: props.user?.kabupaten_kota ?? '',
    kecamatan: props.user?.kecamatan ?? '',
    kelurahan: props.user?.kelurahan ?? '',
    village_code: props.user?.village_code ?? '',
    kode_pos: props.user?.kode_pos ?? '',
    alamat: props.user?.alamat ?? '',
});

// ── Ongkir (RajaOngkir) ──
const shippingCosts = ref<ShippingOption[]>([]);
const shippingWeight = ref<number | null>(null);
const ongkirLoading = ref(false);
const ongkirError = ref('');
const ongkirStale = ref(false);
const selectedCourier = ref('');
const selectedShippingValue = ref('');
const ongkirRequest = useHttp<{
    postal_code: string;
    selected_groups: string[];
}>();

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

const fulfillmentMethod = ref<'kirim' | 'ambil'>('kirim');
const isAmbil = computed(() => fulfillmentMethod.value === 'ambil');

function chooseFulfillment(method: 'kirim' | 'ambil'): void {
    fulfillmentMethod.value = method;
}

function toggleGroup(key: string, checked: boolean): void {
    router.post(
        CartController.toggleGroup().url,
        { group_key: key, checked },
        { preserveScroll: true, preserveState: true },
    );
}

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

    ongkirLoading.value = true;
    ongkirError.value = '';
    ongkirStale.value = false;

    ongkirRequest.transform(() => ({
        postal_code: address.value.kode_pos,
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

            if (
                selectedCourier.value &&
                !shippingCosts.value.some(
                    (option) => option.courier_code === selectedCourier.value,
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
        onError: (errors: { message?: string }) => {
            ongkirError.value =
                errors.message ?? 'Gagal menghitung ongkir, coba lagi.';
        },
        onFinish: () => {
            ongkirLoading.value = false;
        },
    });
}

const grandTotal = computed(
    () => totalAfterDiscount.value + (isAmbil.value ? 0 : shippingCost.value),
);

function invalidateOngkir(): void {
    if (shippingCosts.value.length === 0 && selectedCourier.value === '') {
        return;
    }

    selectedShippingValue.value = '';
    selectedCourier.value = '';
    shippingCosts.value = [];
    ongkirStale.value = true;
}

function updateQty(item: CartItem, delta: number): void {
    const qty = item.qty + delta;
    invalidateOngkir();

    if (qty <= 0) {
        router.post(CartController.remove(item.book.id).url, undefined, {
            preserveScroll: true,
            preserveState: true,
        });

        return;
    }

    const capQty = item.book.is_preorder ? 99 : item.book.stok;

    if (qty > capQty) {
        return;
    }

    router.post(
        CartController.updateQty(item.book.id).url,
        { qty },
        { preserveScroll: true, preserveState: true },
    );
}

function removeItem(item: CartItem): void {
    router.post(CartController.remove(item.book.id).url, undefined, {
        preserveScroll: true,
        preserveState: true,
    });
}

function removeGroup(group: CartGroup): void {
    router.post(
        CartController.removeGroup().url,
        { group_key: group.key },
        {
            preserveScroll: true,
            preserveState: true,
        },
    );
}

function changeEdition(item: CartItem, editionId: string): void {
    invalidateOngkir();
    router.post(
        CartController.updateEdition(item.book.id).url,
        { book_edition_id: editionId },
        {
            preserveScroll: true,
            preserveState: true,
        },
    );
}

function currentEditionId(item: CartItem): string | undefined {
    return item.editions.find(
        (e) => `Cetakan ke-${e.cetakan_ke}` === item.edition_label,
    )?.id;
}

function onShippingChange(): void {
    const [courier] = selectedShippingValue.value.split(':');
    selectedCourier.value = courier ?? '';
}

function onFormError(): void {
    toast.error(
        'Gagal memproses pesanan — periksa kembali isian yang wajib diisi.',
    );
}
</script>

<template>
    <Head title="Checkout — Pustaka Cahaya Peradaban" />

    <div class="mx-auto max-w-6xl px-4 pt-12 pb-32 md:px-6 md:pt-16">
        <h1
            class="font-serif text-3xl font-semibold tracking-tight md:text-4xl"
        >
            Checkout
        </h1>
        <p class="mt-3 text-sm text-pcd-muted">
            Isi alamat, pilih cara bayar, selesai. Pesanan dikonfirmasi via
            WhatsApp.
        </p>

        <Form
            :action="CheckoutPcdController.store().url"
            method="post"
            class="mt-10 grid gap-10 lg:grid-cols-[1fr_380px] lg:gap-14"
            v-slot="{ errors, processing }"
            @error="onFormError"
        >
            <!-- Kolom form -->
            <div class="lg:order-1">
                <h2 class="text-base font-semibold">Data Penerima</h2>
                <div class="mt-5 space-y-5">
                    <div>
                        <label for="pcd-nama" class="text-sm font-medium"
                            >Nama lengkap</label
                        >
                        <input
                            id="pcd-nama"
                            name="nama_pembeli"
                            v-model="namaPembeli"
                            type="text"
                            autocomplete="name"
                            required
                            class="mt-2 w-full rounded-lg border border-pcd-hairline bg-pcd-surface px-4 py-3.5 text-sm transition-colors outline-none placeholder:text-pcd-muted/70 focus:border-pcd-accent focus:ring-2 focus:ring-pcd-accent/25"
                            placeholder="cth. Siti Rahayu"
                        />
                        <p
                            v-if="errors.nama_pembeli"
                            class="mt-1.5 text-xs text-red-700"
                        >
                            {{ errors.nama_pembeli }}
                        </p>
                    </div>
                    <div class="grid gap-5 sm:grid-cols-2">
                        <div>
                            <label for="pcd-wa" class="text-sm font-medium"
                                >Nomor WhatsApp</label
                            >
                            <input
                                id="pcd-wa"
                                name="whatsapp_pembeli"
                                type="tel"
                                inputmode="tel"
                                autocomplete="tel"
                                :default-value="
                                    user?.whatsapp_number ?? undefined
                                "
                                class="mt-2 w-full rounded-lg border border-pcd-hairline bg-pcd-surface px-4 py-3.5 text-sm transition-colors outline-none placeholder:text-pcd-muted/70 focus:border-pcd-accent focus:ring-2 focus:ring-pcd-accent/25"
                                placeholder="cth. 0812-3456-7890"
                            />
                        </div>
                        <div>
                            <label for="pcd-email" class="text-sm font-medium"
                                >Email
                                <span class="font-normal text-pcd-muted"
                                    >(opsional)</span
                                ></label
                            >
                            <input
                                id="pcd-email"
                                name="email_pembeli"
                                type="email"
                                autocomplete="email"
                                :default-value="user?.email ?? undefined"
                                class="mt-2 w-full rounded-lg border border-pcd-hairline bg-pcd-surface px-4 py-3.5 text-sm transition-colors outline-none placeholder:text-pcd-muted/70 focus:border-pcd-accent focus:ring-2 focus:ring-pcd-accent/25"
                            />
                        </div>
                    </div>
                </div>

                <h2 class="mt-10 text-base font-semibold">
                    Metode Pengambilan
                </h2>
                <div class="mt-4 grid grid-cols-2 gap-3">
                    <button
                        type="button"
                        class="flex items-start gap-3 rounded-lg border p-4 text-left transition-colors"
                        :class="
                            !isAmbil
                                ? 'border-pcd-ink bg-pcd-surface ring-1 ring-pcd-ink'
                                : 'border-pcd-hairline bg-pcd-surface'
                        "
                        @click="chooseFulfillment('kirim')"
                    >
                        <Truck
                            class="mt-0.5 size-5 shrink-0 text-pcd-accent"
                            aria-hidden="true"
                        />
                        <span>
                            <span class="block text-sm font-semibold"
                                >Kirim</span
                            >
                            <span class="mt-0.5 block text-xs text-pcd-muted"
                                >Via ekspedisi</span
                            >
                        </span>
                    </button>
                    <button
                        type="button"
                        class="flex items-start gap-3 rounded-lg border p-4 text-left transition-colors"
                        :class="
                            isAmbil
                                ? 'border-pcd-ink bg-pcd-surface ring-1 ring-pcd-ink'
                                : 'border-pcd-hairline bg-pcd-surface'
                        "
                        @click="chooseFulfillment('ambil')"
                    >
                        <PackageCheck
                            class="mt-0.5 size-5 shrink-0 text-pcd-accent"
                            aria-hidden="true"
                        />
                        <span>
                            <span class="block text-sm font-semibold"
                                >Ambil Sendiri</span
                            >
                            <span class="mt-0.5 block text-xs text-pcd-muted"
                                >Di toko, tanpa ongkir</span
                            >
                        </span>
                    </button>
                </div>
                <input
                    type="hidden"
                    name="metode_pengambilan"
                    :value="fulfillmentMethod"
                />

                <div v-if="!isAmbil" class="mt-10">
                    <h2 class="text-base font-semibold">Alamat Pengiriman</h2>
                    <div class="mt-5">
                        <AddressFields v-model="address" />
                    </div>

                    <h2 class="mt-10 text-base font-semibold">
                        Ekspedisi &amp; Ongkir
                    </h2>
                    <div
                        class="mt-4 flex flex-col gap-3 sm:flex-row sm:items-center"
                    >
                        <select
                            v-model="selectedShippingValue"
                            :disabled="
                                ongkirLoading || shippingCosts.length === 0
                            "
                            class="min-h-12 w-full flex-1 rounded-lg border border-pcd-hairline bg-pcd-surface px-4 text-sm transition-colors outline-none focus:border-pcd-accent focus:ring-2 focus:ring-pcd-accent/25 disabled:opacity-60 sm:max-w-sm"
                            @change="onShippingChange"
                        >
                            <option value="" disabled>
                                {{
                                    ongkirLoading
                                        ? 'Menghitung ongkir...'
                                        : address.kelurahan
                                          ? 'Pilih ekspedisi'
                                          : 'Pilih alamat lengkap dulu'
                                }}
                            </option>
                            <option
                                v-for="option in shippingCosts"
                                :key="`${option.courier_code}:${option.service_code}`"
                                :value="`${option.courier_code}:${option.service_code}`"
                            >
                                {{ option.courier_name }} — Rp
                                {{ option.price.toLocaleString('id-ID') }}
                                <template v-if="option.estimation">
                                    ({{ option.estimation }})</template
                                >
                            </option>
                        </select>
                        <button
                            type="button"
                            class="inline-flex min-h-12 items-center justify-center gap-2 rounded-lg border border-pcd-hairline bg-pcd-surface px-6 text-sm font-medium transition-colors hover:border-pcd-ink focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-pcd-accent-strong disabled:opacity-60"
                            :disabled="ongkirLoading || !address.kode_pos"
                            @click="checkOngkir"
                        >
                            <Loader2
                                v-if="ongkirLoading"
                                class="size-4 animate-spin"
                                aria-hidden="true"
                            />
                            {{ ongkirLoading ? 'Menghitung...' : 'Cek Ongkir' }}
                        </button>
                    </div>
                    <p v-if="ongkirLoading" class="mt-2 text-xs text-pcd-muted">
                        Menghitung ongkir ke {{ address.kelurahan }}...
                    </p>
                    <p
                        v-else-if="ongkirError"
                        class="mt-2 text-xs text-red-700"
                    >
                        {{ ongkirError }}
                    </p>
                    <p
                        v-else-if="ongkirStale"
                        class="mt-2 text-xs text-amber-700"
                    >
                        Berat berubah — tekan Cek Ongkir lagi.
                    </p>
                    <p
                        v-else-if="
                            shippingCosts.length && shippingWeight !== null
                        "
                        class="mt-2 text-xs text-pcd-muted"
                    >
                        Berat paket {{ shippingWeight }} kg ·
                        {{ shippingCosts.length }} ekspedisi tersedia
                    </p>
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

                <h2 class="mt-10 text-base font-semibold">Cara Bayar</h2>
                <div class="mt-4 space-y-3">
                    <label
                        v-for="(label, value) in paymentOptions"
                        :key="value"
                        class="block cursor-pointer"
                    >
                        <input
                            v-model="selectedPayment"
                            type="radio"
                            name="metode_bayar"
                            :value="value"
                            class="peer sr-only"
                        />
                        <span
                            class="flex min-h-[68px] items-center rounded-lg border bg-pcd-surface px-4 py-3.5 text-sm font-semibold transition-colors peer-checked:border-pcd-ink peer-checked:ring-1 peer-checked:ring-pcd-ink peer-focus-visible:outline-2 peer-focus-visible:outline-offset-2 peer-focus-visible:outline-pcd-accent-strong"
                            >{{ label }}</span
                        >
                    </label>
                    <p v-if="errors.metode_bayar" class="text-xs text-red-700">
                        {{ errors.metode_bayar }}
                    </p>
                    <div
                        v-if="
                            selectedPayment === 'transfer' &&
                            bankAccounts.length
                        "
                        class="rounded-lg border border-pcd-hairline bg-pcd-surface px-4 py-3 text-xs text-pcd-muted"
                    >
                        <p class="mb-1 font-medium text-pcd-ink">
                            Transfer ke rekening:
                        </p>
                        <p
                            v-for="account in bankAccounts"
                            :key="account.id"
                            class="flex flex-wrap items-center gap-2 py-0.5"
                        >
                            <span class="font-medium">{{
                                account.bank_name
                            }}</span>
                            <span class="font-mono tracking-wide">{{
                                account.account_number
                            }}</span>
                            <span>a.n. {{ account.account_holder }}</span>
                        </p>
                    </div>
                </div>
            </div>

            <!-- Ringkasan -->
            <aside class="lg:order-2" aria-labelledby="pcd-ringkasan">
                <div
                    class="rounded-xl border border-pcd-hairline bg-pcd-surface p-6 lg:sticky lg:top-24"
                >
                    <h2 id="pcd-ringkasan" class="text-base font-semibold">
                        Ringkasan Pesanan
                    </h2>

                    <div
                        v-if="groups.length === 0"
                        class="mt-6 py-10 text-center"
                    >
                        <p class="text-sm text-pcd-muted">
                            Keranjang masih kosong.
                        </p>
                    </div>

                    <template v-else>
                        <div
                            v-for="group in groups"
                            :key="group.key"
                            class="mt-5 rounded-lg border border-pcd-hairline p-4 transition-opacity"
                            :class="{
                                'opacity-60': !selectedGroups.includes(
                                    group.key,
                                ),
                            }"
                        >
                            <div class="flex items-center gap-2">
                                <label
                                    class="flex min-w-0 cursor-pointer items-center gap-2"
                                >
                                    <input
                                        type="checkbox"
                                        class="size-4 accent-pcd-accent-strong"
                                        :checked="
                                            selectedGroups.includes(group.key)
                                        "
                                        :aria-label="`Proses ${group.name}`"
                                        @change="
                                            toggleGroup(
                                                group.key,
                                                (
                                                    $event.target as HTMLInputElement
                                                ).checked,
                                            )
                                        "
                                    />
                                    <span
                                        class="min-w-0 truncate text-sm font-semibold"
                                        >{{ group.name }}</span
                                    >
                                </label>
                                <button
                                    type="button"
                                    class="ml-auto flex size-9 shrink-0 items-center justify-center rounded-lg text-pcd-muted transition-colors hover:text-red-700"
                                    :aria-label="`Hapus seluruh grup ${group.name}`"
                                    @click="removeGroup(group)"
                                >
                                    <Trash2 class="size-4" aria-hidden="true" />
                                </button>
                            </div>

                            <ul class="mt-3 space-y-3">
                                <li
                                    v-for="item in group.items"
                                    :key="`${item.book.id}-${item.edition_label}`"
                                    class="flex items-start gap-3"
                                >
                                    <div class="min-w-0 flex-1">
                                        <p class="truncate text-sm font-medium">
                                            {{ item.book.judul }}
                                        </p>
                                        <div
                                            class="mt-1 flex items-center gap-2"
                                        >
                                            <select
                                                v-if="item.editions.length > 1"
                                                class="min-h-9 rounded-md border border-pcd-hairline bg-pcd-surface px-2 text-xs outline-none"
                                                :value="currentEditionId(item)"
                                                @change="
                                                    changeEdition(
                                                        item,
                                                        (
                                                            $event.target as HTMLSelectElement
                                                        ).value,
                                                    )
                                                "
                                            >
                                                <option
                                                    v-for="edition in item.editions"
                                                    :key="edition.id"
                                                    :value="edition.id"
                                                >
                                                    Cetakan ke-{{
                                                        edition.cetakan_ke
                                                    }}
                                                    · Rp
                                                    {{
                                                        edition.harga_jual.toLocaleString(
                                                            'id-ID',
                                                        )
                                                    }}
                                                </option>
                                            </select>
                                            <span
                                                v-else
                                                class="text-xs text-pcd-muted"
                                            >
                                                {{
                                                    item.edition_label
                                                        ? `${item.edition_label} · `
                                                        : ''
                                                }}<Money
                                                    :value="item.price_original"
                                                />
                                            </span>
                                        </div>
                                    </div>
                                    <div
                                        v-if="group.key === 'regular'"
                                        class="flex shrink-0 items-center rounded-md border border-pcd-hairline"
                                    >
                                        <button
                                            type="button"
                                            class="flex size-9 items-center justify-center text-pcd-muted transition-colors hover:text-pcd-ink disabled:opacity-40"
                                            :disabled="item.qty <= 1"
                                            :aria-label="`Kurangi ${item.book.judul}`"
                                            @click="updateQty(item, -1)"
                                        >
                                            <Minus
                                                class="size-3.5"
                                                aria-hidden="true"
                                            />
                                        </button>
                                        <span
                                            class="w-6 text-center text-sm tabular-nums"
                                            >{{ item.qty }}</span
                                        >
                                        <button
                                            type="button"
                                            class="flex size-9 items-center justify-center text-pcd-muted transition-colors hover:text-pcd-ink disabled:opacity-40"
                                            :disabled="
                                                item.qty >=
                                                (item.book.is_preorder
                                                    ? 99
                                                    : item.book.stok)
                                            "
                                            :aria-label="`Tambah ${item.book.judul}`"
                                            @click="updateQty(item, 1)"
                                        >
                                            <Plus
                                                class="size-3.5"
                                                aria-hidden="true"
                                            />
                                        </button>
                                    </div>
                                    <div
                                        class="flex shrink-0 flex-col items-end gap-1"
                                    >
                                        <Money
                                            :value="item.item_total"
                                            class="text-sm font-semibold"
                                        />
                                        <button
                                            type="button"
                                            class="flex size-8 items-center justify-center rounded-md text-pcd-muted transition-colors hover:text-red-700"
                                            :aria-label="`Hapus ${item.book.judul} dari keranjang`"
                                            @click="removeItem(item)"
                                        >
                                            <Trash2
                                                class="size-3.5"
                                                aria-hidden="true"
                                            />
                                        </button>
                                    </div>
                                </li>
                            </ul>
                        </div>

                        <dl
                            class="mt-6 space-y-2.5 border-t border-pcd-hairline pt-5 text-sm"
                        >
                            <div class="flex items-center justify-between">
                                <dt class="text-pcd-muted">Subtotal</dt>
                                <dd class="font-medium tabular-nums">
                                    <Money :value="subtotal" />
                                </dd>
                            </div>
                            <div
                                v-if="totalDiscount > 0"
                                class="flex items-center justify-between"
                            >
                                <dt class="text-pcd-muted">Diskon</dt>
                                <dd
                                    class="font-medium text-red-700 tabular-nums"
                                >
                                    -<Money :value="totalDiscount" />
                                </dd>
                            </div>
                            <div
                                v-if="!isAmbil"
                                class="flex items-center justify-between"
                            >
                                <dt class="text-pcd-muted">Ongkir</dt>
                                <dd class="font-medium tabular-nums">
                                    <Money
                                        v-if="shippingCosts.length"
                                        :value="shippingCost"
                                    />
                                    <span v-else>Belum dihitung</span>
                                </dd>
                            </div>
                            <div
                                class="flex items-center justify-between border-t border-pcd-hairline pt-3"
                            >
                                <dt class="font-semibold">Total</dt>
                                <dd class="text-lg font-semibold tabular-nums">
                                    <Money :value="grandTotal" />
                                </dd>
                            </div>
                        </dl>
                    </template>

                    <input
                        v-for="key in selectedGroups"
                        :key="key"
                        type="hidden"
                        name="selected_groups[]"
                        :value="key"
                    />

                    <button
                        type="submit"
                        :disabled="processing || !hasActiveGroup"
                        class="mt-6 inline-flex min-h-12 w-full items-center justify-center gap-2 rounded-lg bg-pcd-accent-strong px-6 text-sm font-semibold text-white transition-colors hover:bg-pcd-ink focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-pcd-accent-strong disabled:opacity-50"
                    >
                        <Loader2
                            v-if="processing"
                            class="size-4 animate-spin"
                            aria-hidden="true"
                        />
                        Buat Pesanan
                    </button>
                    <p class="mt-3 text-center text-xs text-pcd-muted">
                        Pesanan dikonfirmasi melalui WhatsApp dalam 1×24 jam.
                    </p>
                </div>
            </aside>
        </Form>
    </div>
</template>

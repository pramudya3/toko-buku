<script setup lang="ts">
import { Form, Head, Link, useHttp } from '@inertiajs/vue3';
import { Loader2, Minus, Plus, Search, Trash2 } from '@lucide/vue';
import { computed, ref } from 'vue';
import { toast } from 'vue-sonner';
import OrderController from '@/actions/App/Http/Controllers/Admin/OrderController';
import AddressFields from '@/components/AddressFields.vue';
import type { AddressValue } from '@/components/AddressFields.vue';
import EmptyState from '@/components/EmptyState.vue';
import FormErrorAlert from '@/components/FormErrorAlert.vue';
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
import { Textarea } from '@/components/ui/textarea';
import { index as indexRoute } from '@/routes/admin/orders';
import {
    books as bookOptions,
    customers as customerOptions,
} from '@/routes/admin/orders/options';

type Customer = {
    id: string;
    name: string;
    whatsapp_number: string | null;
    status_pelanggan: string;
    alamat: string | null;
    provinsi: string | null;
    kabupaten_kota: string | null;
    kecamatan: string | null;
    kode_pos: string | null;
};

type Book = {
    id: string;
    judul: string;
    kode_sku: string | null;
    harga: number;
    stok: number;
    berat_gr: number | null;
    editions?: {
        id: string;
        cetakan_ke: number;
        harga_jual: number;
        is_active: boolean;
    }[];
};

type CartItem = {
    book: Book;
    qty: number;
    edition_id: string | null;
};

type TierDiscountRule = {
    tier: string;
    min_qty: number;
    discount_percent: number;
};

const props = defineProps<{
    customers: Customer[];
    books: Book[];
    paymentOptions: Record<string, string>;
    couriers: Record<string, string>;
    tierDiscounts: TierDiscountRule[];
}>();

const search = ref('');
const customerSearch = ref('');
const customerListOpen = ref(false);
const cart = ref<CartItem[]>([]);
const customerId = ref<string>('');
const availableBooks = ref<Book[]>(props.books);
const availableCustomers = ref<Customer[]>(props.customers);
const buyerName = ref('');
const buyerWhatsapp = ref('');
const buyerAddress = ref<AddressValue>({
    provinsi: '',
    kabupaten_kota: '',
    kecamatan: '',
    kelurahan: '',
    village_code: '',
    kode_pos: '',
    alamat: '',
});
const isDropship = ref(false);
const bookSearchRequest = useHttp({ search: '' });
const customerSearchRequest = useHttp({ search: '' });

let bookSearchTimer: ReturnType<typeof setTimeout> | undefined;
let customerSearchTimer: ReturnType<typeof setTimeout> | undefined;

const filteredBooks = computed(() => {
    const query = search.value.toLowerCase();

    if (!query) {
        return [];
    }

    return availableBooks.value.filter(
        (book) =>
            book.judul.toLowerCase().includes(query) ||
            (book.kode_sku?.toLowerCase().includes(query) ?? false),
    );
});

const subtotal = computed(() =>
    cart.value.reduce((sum, item) => sum + itemPrice(item) * item.qty, 0),
);

/**
 * Harga item mengikuti cetakan terpilih (fallback: harga buku).
 */
function itemPrice(item: CartItem): number {
    const edition = item.book.editions?.find((e) => e.id === item.edition_id);

    return edition?.harga_jual ?? item.book.harga;
}

/**
 * Persentase diskon tier utk qty tertentu — sama dengan logika PricingService.
 */
function tierDiscountPercent(tier: string, qty: number): number {
    const rule = props.tierDiscounts
        .filter((r) => r.tier === tier && r.min_qty <= qty)
        .sort((a, b) => b.min_qty - a.min_qty)[0];

    return rule?.discount_percent ?? 0;
}

function itemTierDiscount(item: CartItem): number {
    const percent = tierDiscountPercent(selectedTier.value, item.qty);

    return Math.floor((itemPrice(item) * percent) / 100);
}

const totalItems = computed(() =>
    cart.value.reduce((sum, item) => sum + item.qty, 0),
);

const selectedTier = computed(() => {
    const customer = [...props.customers, ...availableCustomers.value].find(
        (c) => String(c.id) === customerId.value,
    );

    return customer?.status_pelanggan ?? '';
});

const tierDiscountTotal = computed(() =>
    cart.value.reduce((sum, item) => sum + itemTierDiscount(item), 0),
);

const totalAfterTierDiscount = computed(() =>
    Math.max(0, subtotal.value - tierDiscountTotal.value),
);

// ── Ongkir (api.co.id) — order manual ──
type ShippingOption = {
    courier_code: string;
    courier_name: string;
    price: number;
    weight: number;
    estimation: string | null;
};

const shippingCosts = ref<ShippingOption[]>([]);
const ongkirLoading = ref(false);
const ongkirError = ref('');
const selectedCourier = ref('');
const ongkirRequest = useHttp<{
    village_name: string;
    district_name: string;
    weight_kg: number;
}>();

// Berat total items (gram → kg, min 0,1) — bucket 0,5 kg di service.
const totalWeightKg = computed(() => {
    const grams = cart.value.reduce(
        (sum, item) => sum + (item.book.berat_gr ?? 0) * item.qty,
        0,
    );

    return Math.max(0.1, Math.round((grams / 1000) * 100) / 100);
});

const selectedShippingOption = computed(
    () =>
        shippingCosts.value.find(
            (c) => c.courier_code === selectedCourier.value,
        ) ?? null,
);

const shippingCost = computed(() => selectedShippingOption.value?.price ?? 0);
const shippingEstimation = computed(
    () => selectedShippingOption.value?.estimation ?? null,
);
const totalWithShipping = computed(
    () => totalAfterTierDiscount.value + shippingCost.value,
);

function checkOngkir() {
    if (!buyerAddress.value.kode_pos || cart.value.length === 0) {
        return;
    }

    ongkirLoading.value = true;
    ongkirError.value = '';
    selectedCourier.value = '';
    shippingCosts.value = [];

    ongkirRequest.transform(() => ({
        postal_code: buyerAddress.value.kode_pos,
        weight_kg: totalWeightKg.value,
    }));

    ongkirRequest.post(OrderController.checkOngkir().url, {
        onSuccess: (data: unknown) => {
            const payload = data as {
                weight_kg?: number;
                costs?: ShippingOption[];
            };

            shippingCosts.value = payload.costs ?? [];
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
}

function searchBooks() {
    clearTimeout(bookSearchTimer);

    if (!search.value.trim()) {
        availableBooks.value = props.books;

        return;
    }

    bookSearchTimer = setTimeout(() => {
        bookSearchRequest.get(
            bookOptions({ query: { search: search.value.trim() } }).url,
            {
                onSuccess: (data) => {
                    availableBooks.value = data as Book[];
                },
            },
        );
    }, 250);
}

function searchCustomers() {
    customerListOpen.value = true;
    clearTimeout(customerSearchTimer);

    if (!customerSearch.value.trim()) {
        availableCustomers.value = props.customers;

        return;
    }

    customerSearchTimer = setTimeout(() => {
        customerSearchRequest.get(
            customerOptions({
                query: { search: customerSearch.value.trim() },
            }).url,
            {
                onSuccess: (data) => {
                    availableCustomers.value = data as Customer[];
                },
            },
        );
    }, 250);
}

function selectCustomer(customer: Customer) {
    customerId.value = String(customer.id);
    customerSearch.value = customer.name;
    buyerName.value = customer.name;
    buyerWhatsapp.value = customer.whatsapp_number ?? '';
    buyerAddress.value = {
        provinsi: customer.provinsi ?? '',
        kabupaten_kota: customer.kabupaten_kota ?? '',
        kecamatan: customer.kecamatan ?? '',
        kelurahan: '',
        village_code: '',
        kode_pos: customer.kode_pos ?? '',
        alamat: customer.alamat ?? '',
    };
    customerListOpen.value = false;
}

function onCustomerSearchBlur() {
    // Delay agar klik pada item dropdown tetap terdeteksi.
    setTimeout(() => {
        customerListOpen.value = false;
    }, 150);
}

function addToCart(book: Book) {
    const existing = cart.value.find(
        (item) =>
            item.book.id === book.id &&
            item.edition_id ===
                (book.editions?.find((e) => e.is_active)?.id ?? null),
    );

    if (existing) {
        existing.qty += 1;
    } else {
        cart.value.push({
            book,
            qty: 1,
            edition_id: book.editions?.find((e) => e.is_active)?.id ?? null,
        });
    }
}

function changeQty(item: CartItem, delta: number) {
    item.qty += delta;

    if (item.qty <= 0) {
        removeFromCart(item);
    }
}

function removeFromCart(item: CartItem) {
    cart.value = cart.value.filter(
        (i) =>
            !(i.book.id === item.book.id && i.edition_id === item.edition_id),
    );
}

function requireItems() {
    if (cart.value.length === 0) {
        toast.error('Tambahkan minimal 1 item buku.');

        return false;
    }

    return true;
}

function onFormError() {
    toast.error('Gagal menyimpan — periksa kembali isian yang wajib diisi.');
}

const today = new Date().toLocaleDateString('id-ID', {
    day: '2-digit',
    month: 'long',
    year: 'numeric',
    timeZone: 'Asia/Jakarta',
});
</script>

<template>
    <Head title="Buat Pesanan" />

    <div class="flex flex-col gap-4 p-4 md:p-6">
        <div>
            <h1 class="text-xl font-semibold tracking-tight">
                Buat Pesanan Manual
            </h1>
            <p class="text-sm text-muted-foreground">
                Membuat pesanan baru secara manual
            </p>
        </div>

        <Form
            v-bind="OrderController.store.form()"
            class="flex flex-col gap-4"
            v-slot="{ errors, processing, submit }"
            @error="onFormError"
        >
            <FormErrorAlert :errors="errors" />
            <!-- 1 · Pembeli -->
            <Card>
                <CardHeader>
                    <CardTitle class="text-base font-medium">Pembeli</CardTitle>
                </CardHeader>
                <CardContent class="flex flex-col gap-4">
                    <div class="relative">
                        <Search
                            class="absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
                        />
                        <Input
                            v-model="customerSearch"
                            class="pl-9"
                            placeholder="Cari pelanggan yang sudah ada..."
                            @input="searchCustomers"
                            @blur="onCustomerSearchBlur"
                        />
                        <div
                            v-if="customerListOpen && customerSearch.trim()"
                            class="absolute z-10 mt-1 w-full overflow-hidden rounded-md border bg-background shadow-md"
                        >
                            <template v-if="availableCustomers.length">
                                <button
                                    v-for="customer in availableCustomers"
                                    :key="customer.id"
                                    type="button"
                                    class="flex w-full items-center justify-between gap-2 px-3 py-2 text-left text-sm hover:bg-muted/50"
                                    @click="selectCustomer(customer)"
                                >
                                    <span class="font-medium">
                                        {{ customer.name }}
                                    </span>
                                    <span class="text-xs text-muted-foreground">
                                        {{ customer.whatsapp_number ?? '—' }} ·
                                        {{ customer.status_pelanggan }}
                                    </span>
                                </button>
                            </template>
                            <p
                                v-else
                                class="px-3 py-2 text-sm text-muted-foreground"
                            >
                                Tidak ditemukan — isi manual di bawah.
                            </p>
                        </div>
                        <input
                            type="hidden"
                            name="user_id"
                            :value="customerId"
                        />
                    </div>
                    <div class="grid gap-4 md:grid-cols-2">
                        <div class="grid gap-2">
                            <Label for="nama_pembeli">Nama Pembeli *</Label>
                            <Input
                                id="nama_pembeli"
                                name="nama_pembeli"
                                v-model="buyerName"
                                :aria-invalid="
                                    errors.nama_pembeli ? true : undefined
                                "
                                placeholder="Nama lengkap"
                                required
                            />
                        </div>
                        <div class="grid gap-2">
                            <Label for="whatsapp_pembeli"
                                >WhatsApp Pembeli</Label
                            >
                            <Input
                                id="whatsapp_pembeli"
                                name="whatsapp_pembeli"
                                v-model="buyerWhatsapp"
                                placeholder="08xxxxxxxxxx"
                            />
                        </div>
                    </div>
                    <p class="text-xs text-muted-foreground">
                        Pelanggan tidak ditemukan? Isi langsung nama & WhatsApp
                        — data akan tersimpan sebagai pembeli manual.
                    </p>
                </CardContent>
            </Card>

            <!-- 2 · Pengiriman & Pembayaran -->
            <Card>
                <CardHeader>
                    <CardTitle class="text-base font-medium"
                        >Pengiriman & Pembayaran</CardTitle
                    >
                </CardHeader>
                <CardContent class="flex flex-col gap-4">
                    <AddressFields
                        :key="`address-${customerId}-${buyerName}`"
                        v-model="buyerAddress"
                    />
                    <div class="grid gap-4 md:grid-cols-2">
                        <div class="grid gap-2">
                            <Label for="metode_bayar">Metode Bayar *</Label>
                            <Select
                                name="metode_bayar"
                                default-value="transfer"
                            >
                                <SelectTrigger id="metode_bayar">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem
                                        v-for="(label, value) in paymentOptions"
                                        :key="value"
                                        :value="value"
                                    >
                                        {{ label }}
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                        </div>
                        <div class="grid gap-2 md:col-span-2">
                            <Label for="ekspedisi">Ekspedisi & Ongkir</Label>
                            <div class="flex items-end gap-2">
                                <div class="grid flex-1 gap-2">
                                    <Select
                                        v-model="selectedCourier"
                                        name="ekspedisi"
                                        :disabled="
                                            ongkirLoading ||
                                            shippingCosts.length === 0
                                        "
                                    >
                                        <SelectTrigger id="ekspedisi">
                                            <SelectValue
                                                :placeholder="
                                                    ongkirLoading
                                                        ? 'Menghitung ongkir...'
                                                        : buyerAddress.kelurahan
                                                          ? 'Pilih ekspedisi'
                                                          : 'Pilih alamat lengkap dulu'
                                                "
                                            />
                                        </SelectTrigger>
                                        <SelectContent class="max-h-64">
                                            <SelectItem
                                                v-for="option in shippingCosts"
                                                :key="option.courier_code"
                                                :value="option.courier_code"
                                            >
                                                {{ option.courier_name }} — Rp
                                                {{
                                                    option.price.toLocaleString(
                                                        'id-ID',
                                                    )
                                                }}
                                                <span
                                                    v-if="option.estimation"
                                                    class="text-xs text-muted-foreground"
                                                >
                                                    ({{ option.estimation }})
                                                </span>
                                            </SelectItem>
                                        </SelectContent>
                                    </Select>
                                </div>
                                <Button
                                    type="button"
                                    variant="outline"
                                    :disabled="
                                        ongkirLoading ||
                                        !buyerAddress.kode_pos ||
                                        cart.length === 0
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
                                {{ buyerAddress.kelurahan }}...
                            </p>
                            <p
                                v-else-if="ongkirError"
                                class="text-xs text-destructive"
                            >
                                {{ ongkirError }}
                            </p>
                            <p
                                v-else-if="shippingCosts.length"
                                class="text-xs text-muted-foreground"
                            >
                                Berat {{ totalWeightKg }} kg ·
                                {{ shippingCosts.length }} ekspedisi tersedia
                            </p>
                            <input
                                type="hidden"
                                name="shipping_cost"
                                :value="shippingCost"
                            />
                            <input
                                type="hidden"
                                name="ongkir_estimasi"
                                :value="shippingEstimation ?? ''"
                            />
                        </div>
                    </div>
                </CardContent>
            </Card>

            <!-- 3 · Dropship -->
            <Card>
                <CardHeader>
                    <CardTitle class="text-base font-medium"
                        >Dropship</CardTitle
                    >
                </CardHeader>
                <CardContent class="flex flex-col gap-4">
                    <Label class="flex items-center gap-2">
                        <input
                            type="hidden"
                            name="is_dropship"
                            :value="isDropship ? '1' : '0'"
                        />
                        <Checkbox v-model="isDropship" />
                        Order dropship (kirim ke end-customer)
                    </Label>
                    <div v-if="isDropship" class="grid gap-4 md:grid-cols-3">
                        <div class="grid gap-2">
                            <Label for="end_customer_name"
                                >Nama End-Customer</Label
                            >
                            <Input
                                id="end_customer_name"
                                name="end_customer_name"
                                placeholder="Nama penerima akhir"
                            />
                        </div>
                        <div class="grid gap-2">
                            <Label for="end_customer_whatsapp"
                                >WhatsApp End-Customer</Label
                            >
                            <Input
                                id="end_customer_whatsapp"
                                name="end_customer_whatsapp"
                                placeholder="08xxxxxxxxxx"
                            />
                        </div>
                        <div class="grid gap-2">
                            <Label for="end_customer_address"
                                >Alamat End-Customer</Label
                            >
                            <Textarea
                                id="end_customer_address"
                                name="end_customer_address"
                                rows="1"
                            />
                        </div>
                    </div>
                </CardContent>
            </Card>

            <!-- 4 · Item Buku -->
            <Card>
                <CardHeader>
                    <CardTitle class="text-base font-medium"
                        >Item Buku</CardTitle
                    >
                </CardHeader>
                <CardContent class="flex flex-col gap-4">
                    <div class="relative">
                        <Search
                            class="absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
                        />
                        <Input
                            v-model="search"
                            class="pl-9"
                            placeholder="Cari judul / SKU untuk menambahkan..."
                            @input="searchBooks"
                        />
                        <div
                            v-if="search.trim()"
                            class="absolute z-10 mt-1 w-full overflow-hidden rounded-md border bg-background shadow-md"
                        >
                            <button
                                v-for="book in filteredBooks"
                                :key="book.id"
                                type="button"
                                class="flex w-full items-center justify-between gap-2 px-3 py-2 text-left text-sm hover:bg-muted/50"
                                @click="
                                    () => {
                                        addToCart(book);
                                        search = '';
                                    }
                                "
                            >
                                <span class="truncate font-medium">
                                    {{ book.judul }}
                                </span>
                                <span
                                    class="shrink-0 text-xs text-muted-foreground"
                                >
                                    {{ book.kode_sku }} ·
                                    <Money :value="book.harga" /> · stok
                                    {{ book.stok }}
                                </span>
                            </button>
                            <p
                                v-if="!filteredBooks.length"
                                class="px-3 py-2 text-sm text-muted-foreground"
                            >
                                Buku tidak ditemukan.
                            </p>
                        </div>
                    </div>

                    <!-- Invoice Preview -->
                    <div
                        v-if="cart.length"
                        class="overflow-hidden rounded-lg border"
                    >
                        <div
                            class="flex flex-wrap items-center justify-between gap-2 border-b bg-muted/50 px-4 py-3"
                        >
                            <div>
                                <p class="text-sm font-semibold">Invoice</p>
                                <p class="text-xs text-muted-foreground">
                                    Pembeli: {{ buyerName || '—' }} ·
                                    {{ totalItems }} item
                                    <template v-if="selectedTier">
                                        · Tier:
                                        {{ selectedTier }}
                                    </template>
                                </p>
                            </div>
                            <p class="text-xs text-muted-foreground">
                                {{ today }}
                            </p>
                        </div>
                        <table class="w-full text-sm">
                            <thead>
                                <tr
                                    class="border-b bg-muted/50 text-left text-xs text-muted-foreground"
                                >
                                    <th class="px-2 py-2"></th>
                                    <th class="px-4 py-2">Buku</th>
                                    <th class="px-4 py-2">Harga</th>
                                    <th class="px-4 py-2">Qty</th>
                                    <th class="px-4 py-2 text-right">
                                        Diskon Tier
                                    </th>
                                    <th class="px-4 py-2 text-right">
                                        Subtotal
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr
                                    v-for="(item, index) in cart"
                                    :key="`${item.book.id}-${item.edition_id ?? ''}`"
                                    class="border-b last:border-0"
                                >
                                    <td class="px-2 py-2">
                                        <Button
                                            type="button"
                                            variant="ghost"
                                            size="icon-sm"
                                            class="text-destructive"
                                            title="Hapus item"
                                            @click="removeFromCart(item)"
                                        >
                                            <Trash2 class="size-3.5" />
                                        </Button>
                                    </td>
                                    <td class="px-4 py-2">
                                        <p class="font-medium">
                                            {{ item.book.judul }}
                                        </p>
                                        <p
                                            class="text-xs text-muted-foreground"
                                        >
                                            {{ item.book.kode_sku }}
                                        </p>
                                    </td>
                                    <td class="px-4 py-2">
                                        <Money :value="itemPrice(item)" />
                                    </td>
                                    <td class="px-4 py-2">
                                        <!-- Pilih cetakan bila buku punya >1 -->
                                        <template
                                            v-if="
                                                (item.book.editions ?? [])
                                                    .length > 1
                                            "
                                        >
                                            <Select
                                                :model-value="
                                                    item.edition_id
                                                        ? String(
                                                              item.edition_id,
                                                          )
                                                        : undefined
                                                "
                                                @update:model-value="
                                                    (val) => {
                                                        item.edition_id = val
                                                            ? String(val)
                                                            : null;
                                                    }
                                                "
                                            >
                                                <SelectTrigger class="h-8 w-44">
                                                    <SelectValue
                                                        placeholder="Cetakan"
                                                    />
                                                </SelectTrigger>
                                                <SelectContent>
                                                    <SelectItem
                                                        v-for="edition in item
                                                            .book.editions"
                                                        :key="edition.id"
                                                        :value="
                                                            String(edition.id)
                                                        "
                                                    >
                                                        Cetakan
                                                        {{ edition.cetakan_ke }}
                                                        — Rp
                                                        {{
                                                            new Intl.NumberFormat(
                                                                'id-ID',
                                                            ).format(
                                                                edition.harga_jual,
                                                            )
                                                        }}
                                                    </SelectItem>
                                                </SelectContent>
                                            </Select>
                                        </template>
                                        <span
                                            v-else
                                            class="text-xs text-muted-foreground"
                                        >
                                            Cetakan ke-1
                                        </span>
                                    </td>
                                    <td class="px-4 py-2">
                                        <div class="flex items-center gap-2">
                                            <Button
                                                type="button"
                                                variant="outline"
                                                size="icon-sm"
                                                @click="changeQty(item, -1)"
                                            >
                                                <Minus class="size-3.5" />
                                            </Button>
                                            <span
                                                class="w-8 text-center tabular-nums"
                                                >{{ item.qty }}</span
                                            >
                                            <Button
                                                type="button"
                                                variant="outline"
                                                size="icon-sm"
                                                @click="changeQty(item, 1)"
                                            >
                                                <Plus class="size-3.5" />
                                            </Button>
                                        </div>
                                        <input
                                            type="hidden"
                                            :name="`items[${index}][book_id]`"
                                            :value="item.book.id"
                                        />
                                        <input
                                            type="hidden"
                                            :name="`items[${index}][book_edition_id]`"
                                            :value="item.edition_id ?? ''"
                                        />
                                        <input
                                            type="hidden"
                                            :name="`items[${index}][qty]`"
                                            :value="item.qty"
                                        />
                                    </td>
                                    <td
                                        class="px-4 py-2 text-right text-destructive tabular-nums"
                                    >
                                        <template
                                            v-if="itemTierDiscount(item) > 0"
                                        >
                                            -
                                            <Money
                                                :value="itemTierDiscount(item)"
                                            />
                                        </template>
                                        <span
                                            v-else
                                            class="text-muted-foreground"
                                            >—</span
                                        >
                                    </td>
                                    <td
                                        class="px-4 py-2 text-right tabular-nums"
                                    >
                                        <Money
                                            :value="itemPrice(item) * item.qty"
                                        />
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                        <div
                            class="flex flex-wrap items-center justify-between gap-2 border-t px-4 py-3"
                        >
                            <div></div>
                            <div class="text-sm">
                                <p
                                    v-if="tierDiscountTotal > 0"
                                    class="flex justify-between gap-6 text-destructive"
                                >
                                    <span
                                        >Diskon tier ({{ selectedTier }})</span
                                    >
                                    <span
                                        >-
                                        <Money :value="tierDiscountTotal" />
                                    </span>
                                </p>
                                <p
                                    v-if="selectedCourier"
                                    class="mt-1 flex justify-between gap-6 text-sm text-muted-foreground"
                                >
                                    <span
                                        >Ongkir ({{
                                            selectedShippingOption?.courier_name
                                        }})</span
                                    >
                                    <Money :value="shippingCost" />
                                </p>
                                <p
                                    class="mt-1 flex justify-between gap-6 pt-1 font-semibold"
                                >
                                    <span>Total</span>
                                    <Money :value="totalWithShipping" />
                                </p>
                            </div>
                        </div>
                    </div>
                    <EmptyState
                        v-else
                        title="Belum ada item"
                        description="Cari buku di atas untuk menambahkannya ke invoice."
                    />
                </CardContent>
            </Card>

            <div class="flex items-center gap-3">
                <Button
                    type="button"
                    :disabled="processing"
                    @click="
                        () => {
                            if (requireItems()) {
                                submit();
                            }
                        }
                    "
                >
                    {{
                        processing
                            ? 'Menyimpan...'
                            : 'Simpan Pesanan (Menunggu Konfirmasi)'
                    }}
                </Button>
                <Button variant="outline" type="button" as-child>
                    <Link :href="indexRoute().url">Batal</Link>
                </Button>
            </div>
        </Form>
    </div>
</template>

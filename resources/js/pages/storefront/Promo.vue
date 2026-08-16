<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import { ShoppingBag, Tag, TicketPercent } from '@lucide/vue';
import { computed, ref } from 'vue';
import CartController from '@/actions/App/Http/Controllers/CheckoutController';
import BookCoverPlaceholder from '@/components/BookCoverPlaceholder.vue';
import EmptyState from '@/components/EmptyState.vue';
import Money from '@/components/Money.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogScrollContent,
    DialogTitle,
} from '@/components/ui/dialog';
import CustomerLayout from '@/layouts/customer/CustomerLayout.vue';
import { bookShowUrl } from '@/lib/slug';
import { show as showRoute } from '@/routes/books';

type BundleBook = {
    id: string;
    judul: string;
    cover_url: string | null;
    price_original: number;
    unit_price: number;
    unit_discount: number;
    unit_final: number;
    stok: number;
};

type Bundle = {
    id: string;
    promo_name: string;
    discount_percent: number;
    books: BundleBook[];
    total_original: number;
    total_discount: number;
    total_final: number;
};

type PromoBook = {
    id: string;
    judul: string;
    cover_url: string | null;
    harga: number;
    price_breakdown: {
        original_price: number;
        promo_discount: number;
        final_price: number;
        promo_name: string | null;
    } | null;
};

type Promo = {
    id: string;
    promo_name: string;
    promo_type: 'percentage' | 'fixed';
    discount_percentage: number | null;
    promo_value: number | null;
    start_date: string;
    end_date: string;
    is_global: boolean;
    books: PromoBook[];
};

type Voucher = {
    id: string;
    nama: string;
    kode: string | null;
    voucher_type: 'percentage' | 'fixed';
    discount_scope: 'item' | 'ongkir';
    discount_percentage: number | null;
    discount_value: number | null;
    min_order_amount: number;
    max_uses: number | null;
    usages_count: number;
    start_date: string;
    end_date: string;
};

const props = defineProps<{
    bundles: Bundle[];
    promos: Promo[];
    vouchers: Voucher[];
    filters: { search?: string };
}>();

defineOptions({
    layout: CustomerLayout,
});

const activeTab = ref<'all' | 'bundle' | 'item'>('all');

// Paket bundle yang sedang dibuka di modal detail.
const activeBundle = ref<Bundle | null>(null);

// Pencarian dari header — filter kartu promo secara lokal (nama promo /
// judul buku, case-insensitive). Data sudah dimuat penuh dari server.
const searchQuery = computed(() =>
    (props.filters.search ?? '').trim().toLowerCase(),
);

function matchesSearch(
    needle: string,
    books: Array<{ judul: string }>,
): boolean {
    return (
        needle.toLowerCase().includes(searchQuery.value) ||
        books.some((book) =>
            book.judul.toLowerCase().includes(searchQuery.value),
        )
    );
}

const filteredBundles = computed(() =>
    searchQuery.value
        ? props.bundles.filter((bundle) =>
              matchesSearch(bundle.promo_name, bundle.books),
          )
        : props.bundles,
);

const filteredPromos = computed(() =>
    searchQuery.value
        ? props.promos.filter((promo) =>
              matchesSearch(promo.promo_name, promo.books),
          )
        : props.promos,
);

const hasResults = computed(
    () =>
        filteredBundles.value.length > 0 ||
        filteredPromos.value.length > 0 ||
        props.vouchers.length > 0,
);

// Jumlah hari tersisa promo — untuk badge "berakhir dalam X hari".
function daysLeft(endDate: string): number {
    const end = new Date(`${endDate}T23:59:59`).getTime();
    const diff = Math.ceil((end - Date.now()) / 86_400_000);

    return Math.max(0, diff);
}

function countdownLabel(endDate: string): string {
    const days = daysLeft(endDate);

    return days === 0 ? 'Berakhir hari ini' : `Berakhir dalam ${days} hari`;
}

function promoBadge(promo: Promo): string {
    return promo.promo_type === 'percentage'
        ? `-${promo.discount_percentage ?? 0}%`
        : 'Harga tetap';
}

function bundleOutOfStockCount(bundle: Bundle): number {
    return bundle.books.filter((book) => book.stok <= 0).length;
}

function voucherValueLabel(voucher: Voucher): string {
    const value =
        voucher.voucher_type === 'percentage'
            ? `${voucher.discount_percentage}%`
            : `Rp ${voucher.discount_value?.toLocaleString('id-ID')}`;

    return voucher.discount_scope === 'ongkir'
        ? `${value} ongkos kirim`
        : `${value} semua buku`;
}
</script>

<template>
    <Head title="Promo" />

    <div class="flex flex-col gap-6">
        <div>
            <h1 class="text-2xl font-bold tracking-tight">Promo</h1>
            <p class="text-sm text-muted-foreground">
                Semua penawaran — paket hemat & diskon per buku
            </p>
        </div>

        <EmptyState
            v-if="
                !props.bundles.length &&
                !props.promos.length &&
                !props.vouchers.length
            "
            :lucide-icon="Tag"
            title="Belum ada promo aktif"
            description="Cek kembali nanti — promo baru segera hadir."
        >
            <Button size="sm" as-child>
                <Link :href="'/'">Lihat Katalog</Link>
            </Button>
        </EmptyState>

        <!-- Hasil pencarian kosong (promo ada, tapi tidak cocok) -->
        <EmptyState
            v-else-if="!hasResults"
            :lucide-icon="Tag"
            title="Promo tidak ditemukan"
            :description="`Tidak ada promo yang cocok dengan pencarian “${props.filters.search}”.`"
        />

        <template v-else>
            <!-- Tab: Semua / Paket Hemat / Per Item -->
            <div
                class="flex w-fit items-center gap-1 rounded-lg border bg-muted/40 p-1"
            >
                <button
                    v-for="tab in [
                        { value: 'all', label: 'Semua' },
                        { value: 'bundle', label: 'Paket Hemat' },
                        { value: 'item', label: 'Per Item' },
                    ]"
                    :key="tab.value"
                    type="button"
                    class="rounded-md px-3 py-1.5 text-sm font-medium transition-colors"
                    :class="
                        activeTab === tab.value
                            ? 'bg-background text-foreground shadow-sm'
                            : 'text-muted-foreground hover:text-foreground'
                    "
                    @click="activeTab = tab.value as 'all' | 'bundle' | 'item'"
                >
                    {{ tab.label }}
                </button>
            </div>

            <!-- ── Paket Hemat ── -->
            <div
                v-if="activeTab !== 'item' && filteredBundles.length"
                class="flex flex-col gap-3"
            >
                <div class="flex items-center gap-2">
                    <Tag class="size-5 text-primary" />
                    <h2 class="text-lg font-bold tracking-tight">
                        Paket Hemat
                    </h2>
                </div>
                <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    <div
                        v-for="bundle in filteredBundles"
                        :key="bundle.id"
                        class="flex flex-col gap-3 rounded-xl border p-4 transition-shadow hover:shadow-md"
                    >
                        <div class="flex items-start gap-3">
                            <!-- Cover buku paket (tumpuk) -->
                            <div class="relative">
                                <div class="flex -space-x-3">
                                    <template
                                        v-for="book in bundle.books.slice(0, 3)"
                                        :key="book.id"
                                    >
                                        <div
                                            class="size-16 shrink-0 overflow-hidden rounded-md border bg-muted shadow-sm"
                                        >
                                            <img
                                                v-if="book.cover_url"
                                                :src="book.cover_url"
                                                :alt="book.judul"
                                                class="h-full w-full object-cover"
                                            />
                                            <BookCoverPlaceholder
                                                v-else
                                                :title="book.judul"
                                                class="h-full w-full"
                                            />
                                        </div>
                                    </template>
                                </div>
                                <span
                                    v-if="bundle.books.length > 3"
                                    class="absolute -right-1.5 -bottom-1.5 rounded-full bg-primary px-1.5 py-0.5 text-[10px] font-bold text-primary-foreground shadow-sm"
                                >
                                    +{{ bundle.books.length - 3 }}
                                </span>
                            </div>
                            <span
                                class="ml-auto rounded-md bg-destructive px-1.5 py-0.5 text-xs font-bold text-destructive-foreground"
                            >
                                -{{ bundle.discount_percent }}%
                            </span>
                        </div>

                        <div>
                            <p class="font-semibold">{{ bundle.promo_name }}</p>
                            <p class="text-xs text-muted-foreground">
                                {{ bundle.books.length }} buku · diskon
                                {{ bundle.discount_percent }}% saat beli semua
                            </p>
                        </div>

                        <div class="flex flex-col gap-0.5">
                            <div class="flex flex-wrap items-baseline gap-x-2">
                                <Money
                                    :value="bundle.total_final"
                                    class="font-bold text-primary"
                                />
                                <Money
                                    :value="bundle.total_original"
                                    class="text-xs text-muted-foreground line-through"
                                />
                            </div>
                            <p class="text-xs font-medium text-destructive">
                                Hemat
                                <Money :value="bundle.total_discount" />
                            </p>
                        </div>

                        <p
                            v-if="bundleOutOfStockCount(bundle) > 0"
                            class="text-xs font-medium text-destructive"
                        >
                            {{ bundleOutOfStockCount(bundle) }} buku stok habis
                        </p>

                        <Button
                            variant="outline"
                            size="sm"
                            class="mt-auto w-full"
                            @click="activeBundle = bundle"
                        >
                            Lihat Paket
                        </Button>
                    </div>
                </div>
            </div>

            <!-- ── Promo per item ── -->
            <div
                v-if="activeTab !== 'bundle' && filteredPromos.length"
                class="flex flex-col gap-3"
            >
                <div class="flex items-center gap-2">
                    <ShoppingBag class="size-5 text-primary" />
                    <h2 class="text-lg font-bold tracking-tight">
                        Promo per Item
                    </h2>
                </div>
                <div class="grid gap-4 lg:grid-cols-2">
                    <div
                        v-for="promo in filteredPromos"
                        :key="promo.id"
                        class="flex flex-col gap-3 rounded-xl border p-4 transition-shadow hover:shadow-md"
                    >
                        <div class="flex items-start gap-3">
                            <div class="min-w-0 flex-1">
                                <p class="font-semibold">
                                    {{ promo.promo_name }}
                                </p>
                                <p class="text-xs text-muted-foreground">
                                    {{ countdownLabel(promo.end_date) }}
                                    <span v-if="promo.is_global"
                                        >· berlaku semua buku</span
                                    >
                                </p>
                            </div>
                            <span
                                class="rounded-md bg-destructive px-1.5 py-0.5 text-xs font-bold text-destructive-foreground"
                            >
                                {{ promoBadge(promo) }}
                            </span>
                        </div>

                        <p
                            v-if="promo.promo_type === 'fixed'"
                            class="text-xs text-muted-foreground"
                        >
                            Harga tetap
                            <Money
                                :value="promo.promo_value ?? 0"
                                class="font-medium text-foreground"
                            />
                            per buku
                        </p>

                        <ul v-if="promo.books.length" class="divide-y">
                            <li
                                v-for="book in promo.books"
                                :key="book.id"
                                class="flex items-center gap-3 py-2.5"
                            >
                                <Link
                                    :href="showRoute.url(bookShowUrl(book))"
                                    class="size-12 shrink-0 overflow-hidden rounded-md border bg-muted"
                                >
                                    <img
                                        v-if="book.cover_url"
                                        :src="book.cover_url"
                                        :alt="book.judul"
                                        class="h-full w-full object-cover"
                                    />
                                    <BookCoverPlaceholder
                                        v-else
                                        :title="book.judul"
                                        class="h-full w-full"
                                    />
                                </Link>
                                <div class="min-w-0 flex-1">
                                    <Link
                                        :href="showRoute.url(bookShowUrl(book))"
                                        class="line-clamp-1 text-sm font-medium hover:underline"
                                    >
                                        {{ book.judul }}
                                    </Link>
                                    <p class="text-xs text-muted-foreground">
                                        <template
                                            v-if="
                                                book.price_breakdown
                                                    ?.promo_discount
                                            "
                                        >
                                            <span class="line-through">
                                                <Money :value="book.harga" />
                                            </span>
                                            <span
                                                class="ml-1.5 font-medium text-primary"
                                            >
                                                <Money
                                                    :value="
                                                        book.price_breakdown
                                                            .final_price
                                                    "
                                                />
                                            </span>
                                        </template>
                                        <Money v-else :value="book.harga" />
                                    </p>
                                </div>
                            </li>
                        </ul>
                        <p
                            v-else-if="promo.is_global"
                            class="text-xs text-muted-foreground"
                        >
                            Berlaku otomatis untuk semua buku di katalog.
                        </p>
                    </div>
                </div>
            </div>
            <!-- ── Voucher diskon ── -->
            <div v-if="props.vouchers.length" class="flex flex-col gap-3">
                <div class="flex items-center gap-2">
                    <TicketPercent class="size-5 text-primary" />
                    <h2 class="text-lg font-bold tracking-tight">Voucher</h2>
                </div>
                <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    <div
                        v-for="voucher in props.vouchers"
                        :key="voucher.id"
                        class="flex flex-col gap-2 rounded-xl border p-4 transition-shadow hover:shadow-md"
                    >
                        <div class="flex items-start justify-between gap-2">
                            <p class="min-w-0 font-semibold">
                                {{ voucher.nama }}
                            </p>
                            <span
                                class="shrink-0 rounded-md bg-destructive px-1.5 py-0.5 text-xs font-bold text-destructive-foreground"
                            >
                                {{ voucherValueLabel(voucher) }}
                            </span>
                        </div>
                        <p class="text-xs text-muted-foreground">
                            {{ countdownLabel(voucher.end_date) }}
                            <template v-if="voucher.min_order_amount > 0">
                                · min. belanja
                                {{
                                    voucher.min_order_amount.toLocaleString(
                                        'id-ID',
                                    )
                                }}
                            </template>
                        </p>
                        <p class="text-xs text-muted-foreground">
                            <template v-if="voucher.kode">
                                Kode
                                <code
                                    class="rounded bg-muted px-1 py-0.5 font-mono font-medium"
                                    >{{ voucher.kode }}</code
                                >
                            </template>
                            <template v-if="voucher.max_uses !== null">
                                · sisa
                                {{ voucher.max_uses - voucher.usages_count }}
                            </template>
                        </p>
                        <p class="mt-auto text-xs text-muted-foreground">
                            Pilih di halaman Checkout saat belanja.
                        </p>
                    </div>
                </div>
            </div>
        </template>
    </div>

    <!-- ── Modal: Detail Paket Hemat ── -->
    <Dialog
        :open="activeBundle !== null"
        @update:open="(v) => !v && (activeBundle = null)"
    >
        <DialogScrollContent class="max-w-lg">
            <DialogHeader>
                <DialogTitle class="flex items-center gap-2">
                    {{ activeBundle?.promo_name }}
                    <span
                        v-if="activeBundle"
                        class="rounded-md bg-destructive px-1.5 py-0.5 text-xs font-bold text-destructive-foreground"
                    >
                        -{{ activeBundle.discount_percent }}%
                    </span>
                </DialogTitle>
                <DialogDescription>
                    Diskon {{ activeBundle?.discount_percent }}% berlaku saat
                    membeli semua buku dalam paket ini
                </DialogDescription>
            </DialogHeader>

            <div class="max-h-[45vh] overflow-y-auto border-y">
                <ul v-if="activeBundle" class="divide-y">
                    <li
                        v-for="book in activeBundle.books"
                        :key="book.id"
                        class="flex items-center gap-3 py-2.5"
                    >
                        <Link
                            :href="showRoute.url(bookShowUrl(book))"
                            class="size-12 shrink-0 overflow-hidden rounded-md border bg-muted"
                        >
                            <img
                                v-if="book.cover_url"
                                :src="book.cover_url"
                                :alt="book.judul"
                                class="h-full w-full object-cover"
                            />
                            <BookCoverPlaceholder
                                v-else
                                :title="book.judul"
                                class="h-full w-full"
                            />
                        </Link>
                        <div class="min-w-0 flex-1">
                            <Link
                                :href="showRoute.url(bookShowUrl(book))"
                                class="line-clamp-1 text-sm font-medium hover:underline"
                            >
                                {{ book.judul }}
                            </Link>
                            <p class="text-xs text-muted-foreground">
                                <span class="line-through">
                                    <Money :value="book.price_original" />
                                </span>
                                <span class="ml-1.5 font-medium text-primary">
                                    <Money :value="book.unit_final" />
                                </span>
                                <span
                                    v-if="book.unit_discount > 0"
                                    class="ml-1.5 font-medium text-destructive"
                                >
                                    − <Money :value="book.unit_discount" />
                                </span>
                                <span
                                    v-if="book.stok <= 0"
                                    class="ml-1.5 rounded bg-neutral-100 px-1.5 py-0.5 font-medium text-neutral-500"
                                >
                                    Habis
                                </span>
                            </p>
                        </div>
                    </li>
                </ul>
            </div>

            <div
                class="flex flex-col gap-1.5 rounded-lg border bg-muted/30 p-3 text-sm"
            >
                <div class="flex items-center justify-between">
                    <span class="text-muted-foreground"
                        >Harga satuan total</span
                    >
                    <Money :value="activeBundle?.total_original ?? 0" />
                </div>
                <div class="flex items-center justify-between">
                    <span class="text-muted-foreground"
                        >Diskon paket (-{{
                            activeBundle?.discount_percent
                        }}%)</span
                    >
                    <Money
                        :value="-(activeBundle?.total_discount ?? 0)"
                        class="font-medium text-destructive"
                    />
                </div>
                <div class="flex items-center justify-between border-t pt-1.5">
                    <span class="font-semibold">Harga paket</span>
                    <Money
                        :value="activeBundle?.total_final ?? 0"
                        class="font-bold text-primary"
                    />
                </div>
            </div>

            <DialogFooter class="flex-col gap-2 sm:flex-row">
                <p
                    v-if="
                        activeBundle && bundleOutOfStockCount(activeBundle) > 0
                    "
                    class="text-xs font-medium text-destructive"
                >
                    {{ bundleOutOfStockCount(activeBundle) }} buku dalam paket
                    sedang stok habis — hanya buku tersedia yang ditambahkan.
                </p>
                <Form
                    v-if="activeBundle"
                    :action="CartController.addBulk().url"
                    method="post"
                    class="w-full"
                >
                    <input
                        v-for="book in activeBundle.books"
                        :key="book.id"
                        type="hidden"
                        name="book_ids[]"
                        :value="String(book.id)"
                    />
                    <Button type="submit" class="w-full">
                        Masukkan Semua ke Keranjang
                    </Button>
                </Form>
            </DialogFooter>
        </DialogScrollContent>
    </Dialog>
</template>

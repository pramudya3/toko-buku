<script setup lang="ts">
/**
 * Detail buku proto-d — /pcd/buku/{bookUrl}. Data nyata: buku, cetakan
 * (editions), stok sellable, breakdown promo dari PricingService.
 */
import { Form, Head, Link } from '@inertiajs/vue3';
import { MessageCircle, Minus, Plus, ShoppingCart } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import CartController from '@/actions/App/Http/Controllers/CheckoutController';
import BookCoverPlaceholder from '@/components/BookCoverPlaceholder.vue';
import Money from '@/components/Money.vue';
import StorefrontPcdLayout from '@/layouts/customer/StorefrontPcdLayout.vue';
import { catalog as catalogUrl } from '@/routes/pcd/books';

type BookEdition = {
    id: string;
    cetakan_ke: number;
    harga_jual: number;
    is_active: boolean;
    stok_sellable?: number;
};

type Book = {
    id: string;
    judul: string;
    penulis: string | null;
    penerbit: string | null;
    tahun: number | null;
    isbn: string | null;
    sinopsis: string | null;
    harga: number;
    stok: number;
    cover_url: string | null;
    is_preorder: boolean;
    category: { id: string; nama: string } | null;
    jumlah_halaman: number | null;
    berat_gr: number | null;
    dimensi: string | null;
    jenis_kertas: string | null;
    bahasa: string | null;
    editions?: BookEdition[];
    price_breakdown?: {
        original_price: number;
        promo_discount: number;
        final_price: number;
        promo_name: string | null;
    } | null;
};

const props = defineProps<{
    book: Book;
    requested?: boolean;
}>();

defineOptions({ layout: StorefrontPcdLayout });

const editions = computed<BookEdition[]>(() => props.book.editions ?? []);
const selectedEdition = ref<BookEdition | null>(
    editions.value.find((e) => e.is_active) ?? editions.value[0] ?? null,
);

const isPreorder = computed(() => props.book.is_preorder === true);
const maxQty = computed(() => {
    if (isPreorder.value) {
        return 99;
    }

    if (selectedEdition.value) {
        return Math.max(selectedEdition.value.stok_sellable ?? 0, 0);
    }

    return Math.max(props.book.stok, 0);
});

const qty = ref(1);
watch(selectedEdition, () => {
    qty.value = Math.min(qty.value, Math.max(maxQty.value, 1));
});

const isDefaultEdition = computed(() => {
    if (selectedEdition.value === null || editions.value.length === 0) {
        return true;
    }

    return (
        editions.value.findIndex((e) => e.id === selectedEdition.value?.id) ===
        editions.value.findIndex((e) => e.is_active)
    );
});

const displayPrice = computed(() => {
    const breakdown = props.book.price_breakdown;

    if (isDefaultEdition.value && breakdown?.promo_discount) {
        return breakdown.final_price;
    }

    return selectedEdition.value?.harga_jual ?? props.book.harga;
});

const displayOriginal = computed(() => {
    const breakdown = props.book.price_breakdown;

    if (isDefaultEdition.value && breakdown?.promo_discount) {
        return breakdown.original_price;
    }

    return null;
});

const discountPercent = computed(() => {
    const breakdown = props.book.price_breakdown;

    if (!breakdown?.promo_discount || !breakdown.original_price) {
        return 0;
    }

    return Math.round(
        (breakdown.promo_discount / breakdown.original_price) * 100,
    );
});

const stockHint = computed(() => {
    if (isPreorder.value) {
        return 'Pre-order: diproses setelah stok tersedia.';
    }

    return maxQty.value === 0
        ? 'Stok habis'
        : `Stok tersedia: ${maxQty.value} unit`;
});

const canAdd = computed(() => isPreorder.value || maxQty.value > 0);

const specs = computed(() =>
    [
        { label: 'ISBN', value: props.book.isbn },
        { label: 'Penerbit', value: props.book.penerbit },
        { label: 'Tahun Terbit', value: props.book.tahun },
        { label: 'Jumlah Halaman', value: props.book.jumlah_halaman },
        {
            label: 'Berat',
            value: props.book.berat_gr ? `${props.book.berat_gr} gr` : null,
        },
        { label: 'Dimensi', value: props.book.dimensi },
        { label: 'Jenis Kertas', value: props.book.jenis_kertas },
        { label: 'Bahasa', value: props.book.bahasa },
    ].filter((s) => s.value !== null && s.value !== ''),
);

const formatRupiah = (value: number) => value.toLocaleString('id-ID');
</script>

<template>
    <Head :title="`${book.judul} — Pustaka Cahaya Peradaban`" />

    <div class="mx-auto max-w-6xl px-4 pt-10 pb-28 md:px-6 md:pt-16 md:pb-32">
        <!-- Breadcrumb -->
        <nav class="text-sm text-gray-500" aria-label="Breadcrumb">
            <ol class="flex items-center gap-1.5">
                <li>
                    <Link
                        :href="catalogUrl().url"
                        class="transition-colors hover:text-flat-primary"
                        >Toko</Link
                    >
                </li>
                <li aria-hidden="true">/</li>
                <li class="text-flat-ink" aria-current="page">
                    {{ book.judul }}
                </li>
            </ol>
        </nav>

        <div
            class="mt-8 grid gap-12 md:mt-12 lg:grid-cols-[minmax(0,420px)_1fr] lg:gap-16"
        >
            <!-- Sampul -->
            <div
                class="mx-auto w-56 sm:w-64 lg:mx-0 lg:w-full lg:max-w-[340px]"
            >
                <div
                    class="overflow-hidden rounded-md border-2 border-flat-border"
                >
                    <img
                        v-if="book.cover_url"
                        :src="book.cover_url"
                        :alt="`Sampul buku ${book.judul}`"
                        class="aspect-[5/7] w-full object-cover"
                    />
                    <BookCoverPlaceholder
                        v-else
                        :title="book.judul"
                        class="aspect-[5/7] w-full"
                    />
                </div>
            </div>

            <!-- Info + CTA -->
            <div class="lg:pt-6">
                <p
                    class="text-xs font-semibold tracking-[0.16em] text-flat-primary uppercase"
                >
                    {{ book.category?.nama ?? 'Buku' }}
                </p>
                <h1
                    class="mt-3 text-3xl leading-tight font-extrabold tracking-tight md:text-4xl"
                >
                    {{ book.judul }}
                </h1>
                <p v-if="book.penulis" class="mt-2 text-sm text-gray-500">
                    oleh {{ book.penulis }}
                </p>

                <div class="mt-6 flex flex-wrap items-baseline gap-x-3 gap-y-1">
                    <p class="text-3xl font-extrabold tabular-nums text-flat-primary">
                        <Money :value="displayPrice" />
                    </p>
                    <p
                        v-if="discountPercent > 0"
                        class="rounded-md bg-flat-accent px-2 py-0.5 text-xs font-bold text-white tabular-nums"
                    >
                        -{{ discountPercent }}%
                    </p>
                    <p
                        v-if="displayOriginal"
                        class="text-sm text-gray-400 tabular-nums line-through"
                    >
                        <Money :value="displayOriginal" />
                    </p>
                </div>
                <p class="mt-1.5 text-xs text-gray-500">{{ stockHint }}</p>

                <!-- Cetakan (edition) -->
                <fieldset v-if="editions.length > 1" class="mt-7">
                    <legend class="text-sm font-medium">Pilih cetakan</legend>
                    <select
                        v-model="selectedEdition"
                        class="mt-3 min-h-12 w-full max-w-xs rounded-md border-2 border-transparent bg-flat-muted px-4 text-sm transition-colors outline-none focus:border-flat-primary focus:bg-white sm:w-auto"
                    >
                        <option
                            v-for="edition in editions"
                            :key="edition.id"
                            :value="edition"
                        >
                            Cetakan ke-{{ edition.cetakan_ke }} — Rp
                            {{ formatRupiah(edition.harga_jual) }}
                        </option>
                    </select>
                </fieldset>

                <!-- Satu CTA utama -->
                <Form
                    id="addToCartForm"
                    :action="CartController.add().url"
                    method="post"
                    class="mt-7"
                >
                    <div class="flex flex-col gap-3 sm:flex-row">
                        <div
                            class="flex items-center rounded-md border-2 border-flat-border bg-white"
                        >
                            <button
                                type="button"
                                class="flex size-12 items-center justify-center text-gray-500 transition-colors hover:text-flat-ink disabled:opacity-40"
                                :disabled="qty <= 1"
                                aria-label="Kurangi jumlah"
                                @click="qty = Math.max(1, qty - 1)"
                            >
                                <Minus class="size-4" aria-hidden="true" />
                            </button>
                            <input
                                v-model.number="qty"
                                type="number"
                                min="1"
                                :max="maxQty"
                                name="qty"
                                class="w-12 bg-transparent text-center text-sm tabular-nums outline-none"
                                aria-label="Jumlah"
                            />
                            <button
                                type="button"
                                class="flex size-12 items-center justify-center text-gray-500 transition-colors hover:text-flat-ink disabled:opacity-40"
                                :disabled="qty >= maxQty && !isPreorder"
                                aria-label="Tambah jumlah"
                                @click="
                                    qty = Math.min(
                                        isPreorder ? 99 : maxQty,
                                        qty + 1,
                                    )
                                "
                            >
                                <Plus class="size-4" aria-hidden="true" />
                            </button>
                        </div>
                        <input type="hidden" name="book_id" :value="book.id" />
                        <input
                            v-if="selectedEdition"
                            type="hidden"
                            name="book_edition_id"
                            :value="selectedEdition.id"
                        />
                        <button
                            type="submit"
                            :disabled="!canAdd"
                            class="inline-flex min-h-12 flex-1 items-center justify-center gap-2 rounded-md bg-flat-primary px-6 text-sm font-semibold text-white transition-all duration-200 hover:scale-105 hover:bg-flat-primary-dark focus-visible:ring-2 focus-visible:ring-flat-primary focus-visible:ring-offset-2 focus-visible:outline-none disabled:opacity-50"
                        >
                            <ShoppingCart class="size-4" aria-hidden="true" />
                            {{ canAdd ? 'Tambah ke Keranjang' : 'Stok Habis' }}
                        </button>
                    </div>
                </Form>

                <a
                    href="#"
                    class="mt-3 inline-flex min-h-12 items-center gap-2 text-sm font-medium text-gray-500 transition-colors hover:text-flat-primary"
                >
                    <MessageCircle class="size-4" aria-hidden="true" />
                    Tanya via WhatsApp
                </a>

                <!-- Meta buku -->
                <dl
                    v-if="specs.length > 0"
                    class="mt-8 grid grid-cols-2 gap-x-6 gap-y-3 border-t-2 border-flat-border pt-6 text-sm sm:grid-cols-3"
                >
                    <div v-for="spec in specs" :key="spec.label">
                        <dt class="text-xs text-gray-500">{{ spec.label }}</dt>
                        <dd
                            class="mt-1 font-medium"
                            :class="{
                                'font-mono text-xs tracking-wide':
                                    spec.label === 'ISBN',
                            }"
                        >
                            {{ spec.value }}
                        </dd>
                    </div>
                </dl>

                <p class="mt-6 text-xs text-gray-500">
                    Pembayaran transfer bank atau tunai saat buku tiba · Garansi
                    buku rusak diganti.
                </p>
            </div>
        </div>

        <!-- Sinopsis -->
        <section
            v-if="book.sinopsis"
            class="mt-16 max-w-2xl border-t-2 border-flat-border pt-12 md:mt-24"
            aria-labelledby="sinopsis"
        >
            <h2
                id="sinopsis"
                class="text-2xl font-extrabold tracking-tight"
            >
                Tentang Buku Ini
            </h2>
            <p
                class="mt-6 text-[17px] leading-[1.8] whitespace-pre-line text-flat-ink/90"
            >
                {{ book.sinopsis }}
            </p>
        </section>

        <section class="mt-16 border-t-2 border-flat-border pt-12 md:mt-24">
            <p class="text-center text-sm text-gray-500">
                Mencari judul lain?
            </p>
            <p class="mt-2 text-center">
                <Link
                    :href="catalogUrl().url"
                    class="inline-flex min-h-12 items-center gap-1.5 text-lg font-extrabold tracking-tight transition-colors hover:text-flat-primary"
                >
                    Lihat Semua Buku
                </Link>
            </p>
        </section>
    </div>

    <!-- Sticky bar (mobile): harga + satu CTA -->
    <div
        class="fixed inset-x-0 bottom-0 z-40 border-t-2 border-flat-border bg-white lg:hidden"
    >
        <div
            class="flex items-center gap-3 px-4 py-3 pb-[calc(0.75rem+env(safe-area-inset-bottom))]"
        >
            <div class="min-w-0">
                <p class="text-base font-bold tabular-nums text-flat-primary">
                    <Money :value="displayPrice" />
                </p>
                <p class="truncate text-[11px] text-gray-500">
                    {{ book.judul }} ·
                    {{
                        isPreorder
                            ? 'Pre-order'
                            : selectedEdition
                              ? `Cetakan ke-${selectedEdition.cetakan_ke}`
                              : ''
                    }}
                </p>
            </div>
            <button
                type="submit"
                form="addToCartForm"
                :disabled="!canAdd"
                class="ml-auto inline-flex min-h-12 flex-1 items-center justify-center gap-2 rounded-md bg-flat-primary px-5 text-sm font-semibold text-white transition-all duration-200 hover:scale-105 hover:bg-flat-primary-dark focus-visible:ring-2 focus-visible:ring-flat-primary focus-visible:ring-offset-2 focus-visible:outline-none disabled:opacity-50 sm:max-w-xs"
            >
                <ShoppingCart class="size-4" aria-hidden="true" />
                {{ canAdd ? 'Tambah' : 'Stok Habis' }}
            </button>
        </div>
    </div>
</template>

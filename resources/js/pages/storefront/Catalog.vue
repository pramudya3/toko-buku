<script setup lang="ts">
import { Form, Head, Link, router, useHttp } from '@inertiajs/vue3';
import { Loader2, Search } from '@lucide/vue';
import { ref, watch } from 'vue';
import CartController from '@/actions/App/Http/Controllers/CheckoutController';
import BookCoverPlaceholder from '@/components/BookCoverPlaceholder.vue';
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
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import CustomerLayout from '@/layouts/customer/CustomerLayout.vue';
import { bookShowUrl } from '@/lib/slug';
import {
    catalog as catalogUrl,
    loadMore as loadMoreUrl,
    show as showRoute,
} from '@/routes/books';

type Book = {
    id: string;
    judul: string;
    penulis: string | null;
    harga: number;
    stok: number;
    cover_url: string | null;
    category: { id: string; nama: string } | null;
    price_breakdown?: {
        original_price: number;
        promo_discount: number;
        final_price: number;
        promo_name: string | null;
    } | null;
};

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

type Props = {
    books: {
        data: Book[];
        current_page: number;
        last_page: number;
        total: number;
        per_page: number;
    };
    categories: Array<{ id: string; nama: string }>;
    bundles: Bundle[];
    filters: { search?: string; category_id?: string };
};

defineOptions({
    layout: CustomerLayout,
});

const props = defineProps<Props>();

// Paket bundle yang sedang dibuka di modal detail.
const activeBundle = ref<Bundle | null>(null);

const allCategories = '__all__';
const search = ref(props.filters.search ?? '');
const categoryId = ref(props.filters.category_id ?? allCategories);
const books = ref<Book[]>(props.books.data);
const currentPage = ref(props.books.current_page);
const lastPage = ref(props.books.last_page);
const loadingMore = ref(false);
const loadMoreRequest = useHttp();

// Token generasi load-more — response yang sudah basi (filter berubah)
// diabaikan supaya tidak mencampur hasil lama ke daftar baru.
let loadMoreToken = 0;

let timer: ReturnType<typeof setTimeout> | undefined;

watch([search, categoryId], () => {
    // Filter berubah → response load-more lama tidak berlaku lagi.
    loadMoreToken++;
    loadingMore.value = false;

    clearTimeout(timer);
    timer = setTimeout(() => {
        router.get(
            catalogUrl().url,
            {
                search: search.value || undefined,
                category_id:
                    categoryId.value === allCategories
                        ? undefined
                        : categoryId.value,
            },
            { preserveState: true, replace: true },
        );
    }, 350);
});

// Sync daftar buku saat props berubah (search/filter reload dengan preserveState).
watch(
    () => props.books,
    (newBooks) => {
        books.value = newBooks.data;
        currentPage.value = newBooks.current_page;
        lastPage.value = newBooks.last_page;
    },
);

// Jumlah buku stok habis di dalam sebuah paket.
function bundleOutOfStockCount(bundle: Bundle): number {
    return bundle.books.filter((book) => book.stok <= 0).length;
}

// Persentase diskon untuk badge — dihitung dari price_breakdown (0 jika tidak ada promo).
function discountPercent(book: Book): number {
    const breakdown = book.price_breakdown;

    if (!breakdown?.promo_discount || !breakdown.original_price) {
        return 0;
    }

    return Math.round(
        (breakdown.promo_discount / breakdown.original_price) * 100,
    );
}

function loadMore() {
    if (loadingMore.value || currentPage.value >= lastPage.value) {
        return;
    }

    loadingMore.value = true;
    const token = ++loadMoreToken;

    loadMoreRequest.get(
        loadMoreUrl({
            query: {
                page: currentPage.value + 1,
                search: search.value || undefined,
                category_id:
                    categoryId.value === allCategories
                        ? undefined
                        : categoryId.value,
            },
        }).url,
        {
            onSuccess: (data) => {
                if (token !== loadMoreToken) {
                    return; // filter berubah — hasil ini basi
                }

                const page = data as {
                    data: Book[];
                    current_page: number;
                    last_page: number;
                };

                books.value = [...books.value, ...page.data];
                currentPage.value = page.current_page;
                lastPage.value = page.last_page;
            },
            onFinish: () => {
                if (token === loadMoreToken) {
                    loadingMore.value = false;
                }
            },
        },
    );
}
</script>

<template>
    <Head title="Katalog Buku">
        <meta
            name="description"
            content="Katalog buku toko — fiksi, non-fiksi, pendidikan, dan religi. Belanja mudah, harga bersahabat."
        />
        <meta
            property="og:title"
            content="Katalog Buku — Pustaka Cahaya Peradaban"
        />
        <meta
            property="og:description"
            content="Temukan buku berkualitas untuk semua kalangan."
        />
    </Head>

    <div class="flex flex-col gap-6">
        <div>
            <h1 class="text-2xl font-bold tracking-tight">Katalog Buku</h1>
            <p class="text-sm text-muted-foreground">
                Jelajahi koleksi buku toko kami
            </p>
        </div>

        <div class="grid gap-4 md:grid-cols-2">
            <div class="relative">
                <Search
                    class="absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
                />
                <Input
                    v-model="search"
                    class="pl-9"
                    placeholder="Cari judul / penulis..."
                />
            </div>
            <Select v-model="categoryId">
                <SelectTrigger>
                    <SelectValue placeholder="Semua kategori" />
                </SelectTrigger>
                <SelectContent>
                    <SelectItem :value="allCategories"
                        >Semua kategori</SelectItem
                    >
                    <SelectItem
                        v-for="category in categories"
                        :key="category.id"
                        :value="String(category.id)"
                    >
                        {{ category.nama }}
                    </SelectItem>
                </SelectContent>
            </Select>
        </div>

        <!-- ── Paket Hemat: promo bundle aktif ── -->
        <div v-if="bundles.length" class="flex flex-col gap-3">
            <div>
                <h2 class="text-lg font-bold tracking-tight">Paket Hemat</h2>
                <p class="text-sm text-muted-foreground">
                    Beli paket, hemat lebih banyak
                </p>
            </div>
            <!-- Mobile: scroll horizontal — Desktop: grid 3 kolom -->
            <div
                class="flex snap-x snap-mandatory gap-4 overflow-x-auto pb-2 lg:grid lg:grid-cols-3 lg:overflow-visible"
            >
                <div
                    v-for="bundle in bundles"
                    :key="bundle.id"
                    class="flex w-[85vw] max-w-[280px] shrink-0 snap-start flex-col gap-3 rounded-xl border p-4 transition-shadow hover:shadow-md lg:w-auto"
                >
                    <div class="flex items-start gap-3">
                        <!-- Cover buku paket (tumpuk) -->
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
                        class="mt-auto line-clamp-2 text-xs text-muted-foreground"
                    >
                        {{ bundle.books.map((b) => b.judul).join(', ') }}
                    </p>

                    <p
                        v-if="bundleOutOfStockCount(bundle) > 0"
                        class="text-xs font-medium text-destructive"
                    >
                        {{ bundleOutOfStockCount(bundle) }} buku stok habis
                    </p>

                    <Button
                        variant="outline"
                        size="sm"
                        class="w-full"
                        @click="activeBundle = bundle"
                    >
                        Lihat Paket
                    </Button>
                </div>
            </div>
        </div>

        <div
            v-if="books.length"
            class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4"
        >
            <Link
                v-for="book in books"
                :key="book.id"
                :href="showRoute.url(bookShowUrl(book))"
                class="flex flex-col overflow-hidden rounded-xl border transition-shadow hover:shadow-md"
            >
                <div
                    class="relative flex aspect-[2/3] items-center justify-center overflow-hidden bg-muted text-4xl"
                >
                    <span
                        v-if="discountPercent(book) > 0"
                        class="absolute top-2 left-2 rounded-md bg-destructive px-1.5 py-0.5 text-xs font-bold text-destructive-foreground shadow-sm"
                    >
                        -{{ discountPercent(book) }}%
                    </span>
                    <img
                        v-if="book.cover_url"
                        :src="book.cover_url"
                        :alt="book.judul"
                        class="h-full w-full object-contain"
                    />
                    <BookCoverPlaceholder
                        v-else
                        :title="book.judul"
                        class="h-full w-full"
                    />
                </div>
                <div class="flex flex-1 flex-col gap-1 p-4">
                    <p class="line-clamp-2 font-medium">{{ book.judul }}</p>
                    <p class="text-xs text-muted-foreground">
                        {{ book.penulis ?? '—' }}
                    </p>
                    <div class="mt-auto flex items-center justify-between pt-2">
                        <div class="flex flex-col">
                            <template
                                v-if="book.price_breakdown?.promo_discount"
                            >
                                <Money
                                    :value="book.price_breakdown.final_price"
                                    class="font-semibold text-primary"
                                />
                                <Money
                                    :value="book.harga"
                                    class="text-xs text-muted-foreground line-through"
                                />
                            </template>
                            <template v-else>
                                <Money
                                    :value="book.harga"
                                    class="font-semibold"
                                />
                            </template>
                        </div>
                        <span
                            v-if="book.stok === 0"
                            class="rounded-full bg-neutral-100 px-2 py-0.5 text-xs font-medium text-neutral-500"
                        >
                            Habis
                        </span>
                    </div>
                </div>
            </Link>
        </div>

        <p v-else class="py-10 text-center text-sm text-muted-foreground">
            Tidak ada buku yang cocok dengan pencarian.
        </p>

        <div
            v-if="books.length && currentPage < lastPage"
            class="flex justify-center"
        >
            <Button variant="outline" :disabled="loadingMore" @click="loadMore">
                <Loader2 v-if="loadingMore" class="size-4 animate-spin" />
                {{ loadingMore ? 'Memuat...' : 'Muat Lebih Banyak' }}
            </Button>
        </div>
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
                                    −
                                    <Money :value="book.unit_discount" />
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

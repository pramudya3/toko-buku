<script setup lang="ts">
import { Head, Link, router, useHttp } from '@inertiajs/vue3';
import { Loader2, Search } from '@lucide/vue';
import { ref, watch } from 'vue';
import BookCoverPlaceholder from '@/components/BookCoverPlaceholder.vue';
import Money from '@/components/Money.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import CustomerLayout from '@/layouts/customer/CustomerLayout.vue';
import { catalog as catalogUrl, loadMore as loadMoreUrl } from '@/routes/books';

type Book = {
    id: number;
    judul: string;
    penulis: string | null;
    harga: number;
    stok: number;
    is_preorder: boolean;
    cover_url: string | null;
    category: { id: number; nama: string } | null;
};

type Props = {
    books: {
        data: Book[];
        current_page: number;
        last_page: number;
        total: number;
        per_page: number;
    };
    categories: Array<{ id: number; nama: string }>;
    filters: { search?: string; category_id?: string };
};

defineOptions({
    layout: CustomerLayout,
});

const props = defineProps<Props>();

const allCategories = '__all__';
const search = ref(props.filters.search ?? '');
const categoryId = ref(props.filters.category_id ?? allCategories);
const books = ref<Book[]>(props.books.data);
const currentPage = ref(props.books.current_page);
const lastPage = ref(props.books.last_page);
const loadingMore = ref(false);
const loadMoreRequest = useHttp();

let timer: ReturnType<typeof setTimeout> | undefined;

watch([search, categoryId], () => {
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

function loadMore() {
    if (loadingMore.value || currentPage.value >= lastPage.value) {
        return;
    }

    loadingMore.value = true;

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
                loadingMore.value = false;
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
        <meta property="og:title" content="Katalog Buku — Toko Buku Online" />
        <meta
            property="og:description"
            content="Temukan buku berkualitas untuk semua kalangan."
        />
    </Head>

    <div class="flex flex-col gap-6">
        <div>
            <h1 class="text-2xl font-bold tracking-tight">Katalog Buku</h1>
            <p class="text-sm text-muted-foreground">
                {{ props.books.total }} buku tersedia
            </p>
        </div>

        <div class="grid gap-4 md:grid-cols-3">
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
                    <SelectItem :value="allCategories">Semua kategori</SelectItem>
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

        <div
            v-if="books.length"
            class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4"
        >
            <Link
                v-for="book in books"
                :key="book.id"
                :href="`/buku/${book.id}`"
                class="flex flex-col overflow-hidden rounded-xl border transition-shadow hover:shadow-md"
            >
                <div
                    class="flex aspect-[2/3] max-h-56 items-center justify-center overflow-hidden bg-muted text-4xl"
                >
                    <img
                        v-if="book.cover_url"
                        :src="book.cover_url"
                        :alt="book.judul"
                        class="h-full w-full object-cover"
                    />
                    <BookCoverPlaceholder v-else :title="book.judul" class="h-full w-full" />
                </div>
                <div class="flex flex-1 flex-col gap-1 p-4">
                    <p class="line-clamp-2 font-medium">{{ book.judul }}</p>
                    <p class="text-xs text-muted-foreground">
                        {{ book.penulis ?? '—' }}
                    </p>
                    <div class="mt-auto flex items-center justify-between pt-2">
                        <Money :value="book.harga" class="font-semibold" />
                        <span
                            v-if="book.is_preorder"
                            class="rounded-full bg-blue-100 px-2 py-0.5 text-xs font-medium text-blue-700"
                        >
                            Preorder
                        </span>
                        <span
                            v-else-if="book.stok === 0"
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

        <div v-if="books.length && currentPage < lastPage" class="flex justify-center">
            <Button variant="outline" :disabled="loadingMore" @click="loadMore">
                <Loader2 v-if="loadingMore" class="size-4 animate-spin" />
                {{ loadingMore ? 'Memuat...' : 'Muat Lebih Banyak' }}
            </Button>
        </div>
    </div>
</template>

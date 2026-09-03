<script setup lang="ts">
import { useHttp } from '@inertiajs/vue3';
import { Loader2, Search } from '@lucide/vue';
import { ref } from 'vue';
import { Input } from '@/components/ui/input';

export type BookOption = {
    id: string;
    judul: string;
    kode_sku: string | null;
    penulis?: string | null;
    penterjemah?: string | null;
    stok?: number;
};

const props = withDefaults(
    defineProps<{
        /** Base URL endpoint options buku (wayfinder `.url()`). */
        baseUrl: string;
        placeholder?: string;
        emptyHint?: string;
    }>(),
    {
        placeholder: 'Cari judul / SKU / penulis / penerjemah buku...',
        emptyHint: 'Tidak ada buku ditemukan.',
    },
);

const emit = defineEmits<{
    select: [book: BookOption];
}>();

// Pencarian paginated: focus → halaman 1, ketik → reset, scroll bawah → append.
const search = ref('');
const listOpen = ref(false);
const results = ref<BookOption[]>([]);
const page = ref(1);
const lastPage = ref(1);
const total = ref(0);
const loading = ref(false);
const request = useHttp({ search: '', page: 1 });

let searchTimer: ReturnType<typeof setTimeout> | undefined;

function fetchBooks(reset: boolean) {
    if (loading.value) {
        return;
    }

    loading.value = true;

    const nextPage = reset ? 1 : page.value + 1;

    request.search = search.value.trim();
    request.page = nextPage;

    request.get(props.baseUrl, {
        onSuccess: (data) => {
            const payload = data as {
                data: BookOption[];
                current_page: number;
                last_page: number;
                total: number;
            };

            results.value = reset
                ? payload.data
                : [...results.value, ...payload.data];
            page.value = payload.current_page;
            lastPage.value = payload.last_page;
            total.value = payload.total;
        },
        onFinish: () => {
            loading.value = false;
        },
    });
}

function openList() {
    // Jangan buka list saat search kosong — hindari menutupi form lain di dialog
    if (search.value.trim().length === 0 && !results.value.length) {
        listOpen.value = false;

        return;
    }

    listOpen.value = true;

    if (!results.value.length) {
        fetchBooks(true);
    }
}

function searchBooks() {
    clearTimeout(searchTimer);

    // Buka list saat mulai mengetik (1 huruf langsung muncul)
    if (search.value.trim().length > 0) {
        listOpen.value = true;
    } else {
        listOpen.value = false;
        results.value = [];

        return;
    }

    searchTimer = setTimeout(() => {
        fetchBooks(true);
    }, 250);
}

function loadMore() {
    if (page.value >= lastPage.value) {
        return;
    }

    fetchBooks(false);
}

function onScroll(event: Event) {
    const el = event.currentTarget as HTMLElement;

    if (el.scrollTop + el.clientHeight >= el.scrollHeight - 24) {
        loadMore();
    }
}

function onBlur() {
    // Delay agar klik pada item dropdown tetap terdeteksi.
    setTimeout(() => {
        listOpen.value = false;
    }, 150);
}

function stokVariant(stok: number | undefined): string {
    if (stok === undefined) {
        return 'text-muted-foreground';
    }

    if (stok <= 0) {
        return 'text-destructive font-semibold';
    }

    if (stok < 5) {
        return 'text-amber-600 dark:text-amber-400';
    }

    return 'text-muted-foreground';
}

function select(book: BookOption) {
    emit('select', book);

    // Reset internal — pencarian berikutnya mulai bersih.
    search.value = '';
    results.value = [];
    page.value = 1;
    lastPage.value = 1;
    total.value = 0;
    listOpen.value = false;
}
</script>

<template>
    <div class="relative w-full">
        <div class="relative">
            <Search
                class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
            />
            <Input
                v-model="search"
                class="pl-9"
                :placeholder="placeholder"
                @input="searchBooks"
                @focus="openList"
                @blur="onBlur"
            />
        </div>
        <div
            v-if="listOpen"
            class="absolute top-full left-0 z-10 mt-1 max-h-64 w-full overflow-y-auto rounded-md border bg-popover shadow-md"
            @scroll="onScroll"
        >
            <button
                v-for="book in results"
                :key="book.id"
                type="button"
                class="flex w-full flex-col items-start gap-0.5 px-3 py-2 text-left hover:bg-accent"
                @click="select(book)"
            >
                <span class="font-medium">{{ book.judul }}</span>
                <span
                    v-if="
                        book.kode_sku ||
                        book.penulis ||
                        book.penterjemah ||
                        book.stok !== undefined
                    "
                    class="w-full truncate text-xs text-muted-foreground"
                >
                    <template v-if="book.kode_sku">{{
                        book.kode_sku
                    }}</template>
                    <template v-if="book.penulis">
                        · Penulis: {{ book.penulis }}
                    </template>
                    <template v-if="book.penterjemah">
                        · Penerjemah: {{ book.penterjemah }}
                    </template>
                    <template v-if="book.stok !== undefined">
                        ·
                        <span :class="stokVariant(book.stok)"
                            >Stok: {{ book.stok }}</span
                        >
                    </template>
                </span>
            </button>
            <div
                v-if="loading"
                class="flex items-center justify-center gap-2 py-3"
            >
                <Loader2 class="size-4 animate-spin text-muted-foreground" />
                <span class="text-sm text-muted-foreground">Memuat...</span>
            </div>
        </div>
    </div>
</template>

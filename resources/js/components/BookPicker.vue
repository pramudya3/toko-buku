<script setup lang="ts">
import { useHttp } from '@inertiajs/vue3';
import { Loader2, Search } from '@lucide/vue';
import { ref } from 'vue';
import { Input } from '@/components/ui/input';

export type BookOption = {
    id: string;
    judul: string;
    kode_sku: string | null;
};

const props = withDefaults(
    defineProps<{
        /** Base URL endpoint options buku (wayfinder `.url()`). */
        baseUrl: string;
        placeholder?: string;
        emptyHint?: string;
    }>(),
    {
        placeholder: 'Cari judul / SKU buku...',
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
    listOpen.value = true;

    if (!results.value.length) {
        fetchBooks(true);
    }
}

function searchBooks() {
    clearTimeout(searchTimer);

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
        <div
            v-if="listOpen"
            class="absolute z-10 mt-1 max-h-64 w-full overflow-y-auto rounded-md border bg-popover shadow-md"
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
                    v-if="book.kode_sku"
                    class="text-xs text-muted-foreground"
                >
                    {{ book.kode_sku }}
                </span>
            </button>
            <p
                v-if="!results.length && !loading"
                class="px-3 py-2 text-sm text-muted-foreground"
            >
                {{ emptyHint }}
            </p>
            <div v-if="loading" class="flex justify-center py-1.5">
                <Loader2 class="size-3.5 animate-spin text-muted-foreground" />
            </div>
        </div>
    </div>
</template>

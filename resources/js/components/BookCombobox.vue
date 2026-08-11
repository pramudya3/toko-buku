<script setup lang="ts">
import { useHttp } from '@inertiajs/vue3';
import { Check, ChevronsUpDown, Loader2, Search, X } from '@lucide/vue';
import { PopoverContent, PopoverRoot, PopoverTrigger } from 'reka-ui';
import { computed, ref, watch } from 'vue';
import { Input } from '@/components/ui/input';
import { books as bookOptions } from '@/routes/admin/promotions/options';

type Book = {
    id: string;
    judul: string;
    kode_sku: string | null;
    harga: number;
    cover_url?: string | null;
};

const props = withDefaults(
    defineProps<{
        modelValue: Book[];
        placeholder?: string;
        emptyHint?: string;
    }>(),
    {
        placeholder: 'Pilih buku...',
        emptyHint: 'Belum ada buku dipilih.',
    },
);

const emit = defineEmits<{
    'update:modelValue': [books: Book[]];
}>();

const MIN_SEARCH_LENGTH = 2;

const open = ref(false);
const query = ref('');
const results = ref<Book[]>([]);
const page = ref(1);
const lastPage = ref(1);
const isSearching = ref(false);
const request = useHttp({ search: '', page: 1 });
let searchTimer: ReturnType<typeof setTimeout> | undefined;

const selected = computed(() => props.modelValue);

// Pencarian paginated: buka → halaman 1, ketik → reset, scroll bawah → append.
function fetchBooks(reset: boolean) {
    if (isSearching.value) {
        return;
    }

    isSearching.value = true;

    const nextPage = reset ? 1 : page.value + 1;
    const q = query.value.trim();

    request.search = q.length >= MIN_SEARCH_LENGTH ? q : '';
    request.page = nextPage;

    request.get(bookOptions().url, {
        onSuccess: (data) => {
            const payload = data as {
                data: Book[];
                current_page: number;
                last_page: number;
                total: number;
            };

            results.value = reset
                ? payload.data
                : [...results.value, ...payload.data];
            page.value = payload.current_page;
            lastPage.value = payload.last_page;
        },
        onFinish: () => {
            isSearching.value = false;
        },
    });
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

const showEmpty = computed(
    () => !isSearching.value && results.value.length === 0,
);

function isSelected(bookId: string): boolean {
    return selected.value.some((book) => book.id === bookId);
}

function toggle(book: Book) {
    const exists = isSelected(book.id);

    emit(
        'update:modelValue',
        exists
            ? selected.value.filter((b) => b.id !== book.id)
            : [...selected.value, book],
    );
}

/**
 * Pencarian server (debounce): reset ke halaman 1 dengan query, atau
 * tanpa query (browse semua buku) saat kolom dikosongkan.
 */
watch(query, () => {
    clearTimeout(searchTimer);

    searchTimer = setTimeout(() => {
        fetchBooks(true);
    }, 250);
});

function onOpenChange(value: boolean) {
    open.value = value;

    if (value) {
        // Pertama kali dibuka → muat halaman 1 (semua buku, browse via scroll).
        if (!results.value.length) {
            fetchBooks(true);
        }
    } else {
        query.value = '';
        results.value = [];
        page.value = 1;
        lastPage.value = 1;
    }
}
</script>

<template>
    <PopoverRoot v-model:open="open" @update:open="onOpenChange">
        <PopoverTrigger as-child>
            <!-- div (bukan button) karena berisi tombol hapus chip → HTML valid -->
            <div
                role="button"
                tabindex="0"
                class="flex min-h-9 w-full cursor-pointer items-center justify-between gap-2 rounded-md border bg-transparent px-3 py-1.5 text-left text-sm transition-[color,box-shadow] outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 aria-invalid:border-destructive aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40"
            >
                <span
                    v-if="selected.length"
                    class="flex flex-wrap items-center gap-1 py-0.5"
                >
                    <span
                        v-for="book in selected"
                        :key="book.id"
                        class="inline-flex items-center gap-1 rounded-full bg-primary/10 px-2 py-0.5 text-xs font-medium text-primary"
                    >
                        {{ book.judul }}
                        <button
                            type="button"
                            class="rounded-full p-0.5 text-primary/60 transition-colors hover:bg-primary/15 hover:text-primary"
                            :title="`Hapus ${book.judul}`"
                            @click.stop.prevent="toggle(book)"
                        >
                            <X class="size-3" />
                        </button>
                    </span>
                </span>
                <span v-else class="py-0.5 text-muted-foreground">
                    {{ placeholder }}
                </span>
                <ChevronsUpDown class="size-4 shrink-0 text-muted-foreground" />
            </div>
        </PopoverTrigger>

        <PopoverContent
            align="start"
            side="bottom"
            class="z-50 w-(--reka-popover-trigger-width) min-w-[280px] rounded-lg border bg-popover p-0 shadow-lg"
        >
            <!-- Filter teks -->
            <div class="relative border-b p-2">
                <Search
                    class="absolute top-1/2 left-4 size-4 -translate-y-1/2 text-muted-foreground"
                />
                <Input
                    v-model="query"
                    class="pl-8"
                    placeholder="Cari judul atau SKU..."
                    autofocus
                />
                <Loader2
                    v-if="isSearching"
                    class="absolute top-1/2 right-4 size-4 -translate-y-1/2 animate-spin text-muted-foreground"
                />
            </div>

            <!-- Daftar opsi -->
            <div
                class="max-h-64 overflow-y-auto p-1"
                @scroll="onScroll"
            >
                <p
                    v-if="showEmpty"
                    class="px-3 py-6 text-center text-xs text-muted-foreground"
                >
                    Tidak ada buku ditemukan
                    <template v-if="query.trim()"> untuk "{{ query }}"</template>.
                </p>
                <template v-else>
                    <button
                        v-for="book in results"
                        :key="book.id"
                        type="button"
                        class="flex w-full items-center gap-2 rounded-md px-2 py-2 text-left text-sm transition-colors hover:bg-muted/60"
                        :class="isSelected(book.id) && 'bg-primary/5'"
                        @click="toggle(book)"
                    >
                        <span
                            class="grid size-4 shrink-0 place-content-center rounded-[4px] border"
                            :class="
                                isSelected(book.id)
                                    ? 'border-primary bg-primary text-primary-foreground'
                                    : 'border-input'
                            "
                        >
                            <Check v-if="isSelected(book.id)" class="size-3" />
                        </span>
                        <img
                            v-if="book.cover_url"
                            :src="book.cover_url"
                            :alt="book.judul"
                            class="size-8 shrink-0 rounded-sm border object-cover"
                        />
                        <span class="min-w-0 flex-1 truncate font-medium">
                            {{ book.judul }}
                        </span>
                        <span
                            v-if="book.kode_sku"
                            class="text-xs text-muted-foreground"
                        >
                            {{ book.kode_sku }}
                        </span>
                    </button>
                </template>
            </div>
        </PopoverContent>
    </PopoverRoot>

    <p
        v-if="selected.length === 0 && emptyHint"
        class="text-sm text-muted-foreground"
    >
        {{ emptyHint }}
    </p>
</template>

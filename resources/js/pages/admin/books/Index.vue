<script setup lang="ts">
defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Beranda', href: '/admin/dashboard' },
            { title: 'Buku', href: '/admin/books' },
        ],
    },
});

import { Head, Link, router } from '@inertiajs/vue3';
import { Plus, Search, Upload, X } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import BookController from '@/actions/App/Http/Controllers/Admin/BookController';
import ConfirmDeleteDialog from '@/components/ConfirmDeleteDialog.vue';
import DataTable from '@/components/DataTable.vue';
import type { DataTableColumn } from '@/components/DataTable.vue';
import DataTableActions from '@/components/DataTableActions.vue';
import ImportCsvDialog from '@/components/ImportCsvDialog.vue';
import Money from '@/components/Money.vue';
import StatusBadge from '@/components/StatusBadge.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { create, index as indexRoute, edit } from '@/routes/admin/books';

type Book = {
    id: string;
    kode_sku: string | null;
    judul: string;
    penulis: string | null;
    isbn: string | null;
    harga: number;
    stok: number;
    cover_url: string | null;
    aktif: boolean;
    is_preorder: boolean;
    preorder_eta: string | null;
    category: { id: string; nama: string } | null;
    order_items_count: number;
};

type Props = {
    books: {
        data: Book[];
        current_page: number;
        last_page: number;
        total: number;
        per_page: number;
        links: Array<{ url: string | null; label: string; active: boolean }>;
    };
    categories: Array<{ id: string; nama: string }>;
    lowStockThreshold: number;
    filters: {
        search?: string;
        category_id?: string;
        status?: string;
        low_stock?: string;
    };
};

const props = defineProps<Props>();

const columns: DataTableColumn[] = [
    { key: 'judul', header: 'Buku' },
    { key: 'kode_sku', header: 'SKU', cellClass: 'font-mono text-xs' },
    { key: 'category', header: 'Kategori' },
    { key: 'harga', header: 'Harga', cellClass: 'text-right tabular-nums' },
    { key: 'stok', header: 'Stok' },
    { key: 'aktif', header: 'Status' },
    { key: 'aksi', header: 'Aksi', srOnly: true, cellClass: 'text-right' },
];

const allCategories = '__all_categories__';
const allStatuses = '__all_statuses__';
const allStocks = '__all_stocks__';
const stockFilters = ['1', 'kosong'];
const search = ref(props.filters.search ?? '');
const importOpen = ref(false);
const categoryId = ref(props.filters.category_id ?? allCategories);
const status = ref(props.filters.status ?? allStatuses);
const lowStock = ref(
    stockFilters.includes(props.filters.low_stock ?? '')
        ? props.filters.low_stock!
        : allStocks,
);

// Snapshot tidak dipakai: default = kondisi tanpa filter, sehingga
// filter yang datang dari URL (mis. link dashboard low_stock=kosong)
// tetap bisa di-reset ke kondisi netral.
const hasActiveFilters = computed(
    () =>
        search.value !== '' ||
        categoryId.value !== allCategories ||
        status.value !== allStatuses ||
        lowStock.value !== allStocks,
);

let filterTimer: ReturnType<typeof setTimeout> | undefined;

function applyFilters() {
    clearTimeout(filterTimer);
    filterTimer = setTimeout(() => {
        router.get(
            indexRoute().url,
            {
                search: search.value || undefined,
                category_id:
                    categoryId.value === allCategories
                        ? undefined
                        : categoryId.value,
                status: status.value === allStatuses ? undefined : status.value,
                low_stock:
                    lowStock.value === allStocks ? undefined : lowStock.value,
                per_page:
                    new URLSearchParams(window.location.search).get(
                        'per_page',
                    ) || undefined,
            },
            {
                preserveState: true,
                replace: true,
            },
        );
    }, 350);
}

function resetFilters() {
    search.value = '';
    categoryId.value = allCategories;
    status.value = allStatuses;
    lowStock.value = allStocks;
    applyFilters();
}

watch([search, categoryId, status, lowStock], applyFilters);

function confirmDelete(book: Book) {
    deletingBook.value = book;
}

const deletingBook = ref<Book | null>(null);

function executeDelete() {
    if (!deletingBook.value) {
        return;
    }

    const book = deletingBook.value;

    deletingBook.value = null;
    router.delete(BookController.destroy(book.id).url, {
        preserveScroll: true,
    });
}
</script>

<template>
    <Head title="Buku" />

    <div class="mx-auto flex w-full max-w-7xl flex-col gap-3 p-3 md:p-4">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div>
                <h1 class="text-xl font-semibold tracking-tight">
                    Katalog Buku
                </h1>
                <p class="text-sm text-muted-foreground">
                    Mengelola katalog buku toko
                </p>
            </div>
            <div class="flex items-center gap-2">
                <Button variant="outline" @click="importOpen = true">
                    <Upload class="size-4" />
                    Import CSV
                </Button>
                <Button as-child>
                    <Link :href="create()">
                        <Plus class="size-4" />
                        Buat Buku
                    </Link>
                </Button>
            </div>
        </div>

        <div
            class="flex w-full flex-col divide-y divide-border overflow-hidden rounded-md border bg-card md:w-fit md:flex-row md:items-stretch md:divide-x md:divide-y-0"
        >
            <div class="relative flex items-center">
                <Search
                    class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
                />
                <Input
                    v-model="search"
                    class="h-11 w-full rounded-none border-0 bg-transparent pl-9 shadow-none focus-visible:border-transparent focus-visible:ring-0 md:h-9 md:w-56"
                    placeholder="Cari judul, penulis, ISBN, SKU..."
                />
            </div>

            <div class="md:flex md:items-center">
                <p
                    class="px-3 pt-2 text-xs font-medium text-muted-foreground md:hidden"
                >
                    Kategori
                </p>
                <Select v-model="categoryId">
                    <SelectTrigger
                        class="h-11 w-full rounded-none border-0 bg-transparent px-3 shadow-none focus-visible:border-transparent focus-visible:ring-0 md:h-9 md:w-40"
                    >
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

            <div class="md:flex md:items-center">
                <p
                    class="px-3 pt-2 text-xs font-medium text-muted-foreground md:hidden"
                >
                    Status
                </p>
                <Select v-model="status">
                    <SelectTrigger
                        class="h-11 w-full rounded-none border-0 bg-transparent px-3 shadow-none focus-visible:border-transparent focus-visible:ring-0 md:h-9 md:w-40"
                    >
                        <SelectValue placeholder="Semua status" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem :value="allStatuses"
                            >Semua status</SelectItem
                        >
                        <SelectItem value="aktif">Aktif</SelectItem>
                        <SelectItem value="nonaktif">Nonaktif</SelectItem>
                    </SelectContent>
                </Select>
            </div>

            <div class="md:flex md:items-center">
                <p
                    class="px-3 pt-2 text-xs font-medium text-muted-foreground md:hidden"
                >
                    Stok
                </p>
                <Select v-model="lowStock">
                    <SelectTrigger
                        class="h-11 w-full rounded-none border-0 bg-transparent px-3 shadow-none focus-visible:border-transparent focus-visible:ring-0 md:h-9 md:w-44"
                    >
                        <SelectValue placeholder="Semua stok" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem :value="allStocks">Semua stok</SelectItem>
                        <SelectItem value="1"
                            >Menipis (≤ {{ lowStockThreshold }})</SelectItem
                        >
                        <SelectItem value="kosong">Kosong (0)</SelectItem>
                    </SelectContent>
                </Select>
            </div>
            <button
                v-if="hasActiveFilters"
                type="button"
                class="flex h-11 w-full items-center justify-center gap-2 text-sm text-muted-foreground transition-colors hover:bg-accent hover:text-destructive md:h-9 md:w-9"
                title="Hapus filter"
                aria-label="Hapus filter"
                @click="resetFilters"
            >
                <X class="size-4" />
                <span class="md:hidden">Hapus filter</span>
            </button>
        </div>

        <DataTable
            :data="books.data"
            :columns="columns"
            :paginator="books"
            empty-title="Tidak ada buku"
            empty-description="Coba ubah pencarian, atau buat buku pertama Anda."
        >
            <template #cell-judul="{ row }">
                <div class="flex items-center gap-3">
                    <img
                        v-if="row.cover_url"
                        :src="row.cover_url"
                        :alt="row.judul"
                        class="h-12 w-8 rounded border object-cover"
                    />
                    <div
                        v-else
                        class="flex h-12 w-9 items-center justify-center rounded border bg-muted text-xs text-muted-foreground"
                    >
                        📖
                    </div>
                    <div class="min-w-0">
                        <p class="max-w-60 truncate font-medium">
                            {{ row.judul }}
                        </p>
                        <p class="truncate text-xs text-muted-foreground">
                            {{ row.penulis }}
                        </p>
                    </div>
                </div>
            </template>
            <template #cell-category="{ row }">
                <span class="max-w-36 truncate">{{
                    row.category?.nama ?? '—'
                }}</span>
            </template>
            <template #cell-harga="{ row }">
                <Money :value="row.harga" />
            </template>
            <template #cell-stok="{ row }">
                <StatusBadge
                    :variant="row.stok <= 5 ? 'warning' : 'neutral'"
                    :label="String(row.stok)"
                />
            </template>
            <template #cell-aktif="{ row }">
                <StatusBadge
                    :variant="row.aktif ? 'success' : 'danger'"
                    :label="row.aktif ? 'Aktif' : 'Nonaktif'"
                />
                <Badge
                    v-if="row.is_preorder"
                    variant="outline"
                    class="ml-1 border-sky-200 bg-sky-100 text-sky-800"
                    title="Buku new coming — boleh dipesan sebelum stok tiba"
                >
                    Pre-Order
                </Badge>
            </template>
            <template #cell-aksi="{ row }">
                <DataTableActions
                    :actions="[
                        {
                            label: 'Edit',
                            href: edit(row.id).url,
                        },
                        {
                            label: row.aktif ? 'Nonaktifkan' : 'Aktifkan',
                            onClick: () =>
                                router.patch(
                                    BookController.toggleActive(row.id).url,
                                    { preserveScroll: true },
                                ),
                        },
                        {
                            label: 'Hapus',
                            variant: 'destructive',
                            onClick: () => confirmDelete(row),
                        },
                    ]"
                />
            </template>
            <template #empty>
                <Button size="sm" as-child>
                    <Link :href="create()">Buat Buku Pertama</Link>
                </Button>
            </template>
        </DataTable>

        <ConfirmDeleteDialog
            :open="!!deletingBook"
            @update:open="
                (open) => {
                    if (!open) deletingBook = null;
                }
            "
            title="Hapus Buku?"
            :description="
                deletingBook
                    ? `'${deletingBook.judul}' akan dihapus. Buku ber-riwayat pesanan tidak dapat dihapus.`
                    : ''
            "
            @confirm="executeDelete"
        />
    </div>

    <ImportCsvDialog
        v-model:open="importOpen"
        :action="BookController.importCsv.form()"
        template-type="books"
        title="Import Buku dari CSV"
        description="Format kolom: kategori, kode, judul, penulis, harga_jual, harga_beli, qty"
        hint="Jika judul sama maka data buku akan direplace. Harga format Indonesia (30.000). Qty opsional — masuk stok cetakan ke-1 gudang Malang. Kode SKU otomatis dibuat dari abreviasi kategori bila kosong/tidak valid."
    />
</template>

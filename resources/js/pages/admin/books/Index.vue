<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { Pencil, Plus, Search, Trash2 } from '@lucide/vue';
import { ref, watch } from 'vue';
import BookController from '@/actions/App/Http/Controllers/Admin/BookController';
import ConfirmDeleteDialog from '@/components/ConfirmDeleteDialog.vue';
import DataTableActions from '@/components/DataTableActions.vue';
import EmptyState from '@/components/EmptyState.vue';
import Money from '@/components/Money.vue';
import Pagination from '@/components/Pagination.vue';
import StatusBadge from '@/components/StatusBadge.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { create, index as indexRoute, edit } from '@/routes/admin/books';

type Book = {
    id: number;
    kode_sku: string | null;
    judul: string;
    penulis: string | null;
    isbn: string | null;
    harga: number;
    stok: number;
    cover_url: string | null;
    aktif: boolean;
    is_preorder: boolean;
    category: { id: number; nama: string } | null;
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
    categories: Array<{ id: number; nama: string }>;
    filters: { search?: string; category_id?: string; status?: string };
};

const props = defineProps<Props>();

const allCategories = '__all_categories__';
const allStatuses = '__all_statuses__';
const search = ref(props.filters.search ?? '');
const categoryId = ref(props.filters.category_id ?? allCategories);
const status = ref(props.filters.status ?? allStatuses);

let searchTimer: ReturnType<typeof setTimeout> | undefined;

watch([search, categoryId, status], () => {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(() => {
        router.get(
            indexRoute().url,
            {
                search: search.value || undefined,
                category_id:
                    categoryId.value === allCategories
                        ? undefined
                        : categoryId.value,
                status: status.value === allStatuses ? undefined : status.value,
            },
            {
                preserveState: true,
                replace: true,
            },
        );
    }, 350);
});

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

    <div class="flex flex-col gap-4 p-4 md:p-6">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div>
                <h1 class="text-xl font-semibold tracking-tight">
                    Katalog Buku
                </h1>
                <p class="text-sm text-muted-foreground">
                    Mengelola katalog buku toko
                </p>
            </div>
            <Button as-child>
                <Link :href="create()">
                    <Plus class="size-4" />
                    Buat Buku
                </Link>
            </Button>
        </div>

        <div class="grid gap-4 md:grid-cols-3">
            <div class="relative">
                <Search
                    class="absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
                />
                <Input
                    v-model="search"
                    class="pl-9"
                    placeholder="Cari judul, penulis, ISBN, SKU..."
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
            <Select v-model="status">
                <SelectTrigger>
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

        <Card>
            <CardContent class="p-0">
                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead>Buku</TableHead>
                            <TableHead>SKU</TableHead>
                            <TableHead class="w-36">Kategori</TableHead>
                            <TableHead>Harga</TableHead>
                            <TableHead>Stok</TableHead>
                            <TableHead>Status</TableHead>
                            <TableHead class="text-right"><span class="sr-only">Aksi</span></TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <TableRow v-for="book in books.data" :key="book.id">
                            <TableCell>
                                <div class="flex items-center gap-3">
                                    <img
                                        v-if="book.cover_url"
                                        :src="book.cover_url"
                                        :alt="book.judul"
                                        class="h-12 w-9 rounded border object-cover"
                                    />
                                    <div
                                        v-else
                                        class="flex h-12 w-9 items-center justify-center rounded border bg-muted text-xs text-muted-foreground"
                                    >
                                        📖
                                    </div>
                                    <div class="min-w-0">
                                        <p
                                            class="max-w-60 truncate font-medium"
                                        >
                                            {{ book.judul }}
                                            <span
                                                v-if="book.is_preorder"
                                                class="text-xs text-blue-600"
                                                >(PO)</span
                                            >
                                        </p>
                                        <p
                                            class="truncate text-xs text-muted-foreground"
                                        >
                                            {{ book.penulis }}
                                        </p>
                                    </div>
                                </div>
                            </TableCell>
                            <TableCell class="font-mono text-xs">{{
                                book.kode_sku
                            }}</TableCell>
                            <TableCell class="max-w-36 truncate">{{
                                book.category?.nama ?? '—'
                            }}</TableCell>
                            <TableCell><Money :value="book.harga" /></TableCell>
                            <TableCell>
                                <StatusBadge
                                    :variant="
                                        book.stok <= 5 ? 'warning' : 'neutral'
                                    "
                                    :label="String(book.stok)"
                                />
                            </TableCell>
                            <TableCell>
                                <StatusBadge
                                    :variant="book.aktif ? 'success' : 'danger'"
                                    :label="book.aktif ? 'Aktif' : 'Nonaktif'"
                                />
                            </TableCell>
                            <TableCell class="text-right">
                                <DataTableActions
                                    :actions="[
                                        {
                                            label: 'Edit',
                                            icon: Pencil,
                                            href: edit(book.id),
                                        },
                                        {
                                            label: 'Hapus',
                                            icon: Trash2,
                                            variant: 'destructive',
                                            onClick: () => confirmDelete(book),
                                        },
                                    ]"
                                />
                            </TableCell>
                        </TableRow>
                    </TableBody>
                </Table>
                <EmptyState
                    v-if="!books.data.length"
                    title="Tidak ada buku"
                    description="Coba ubah pencarian, atau buat buku pertama Anda."
                >
                    <Button size="sm" as-child>
                        <Link :href="create()">Buat Buku Pertama</Link>
                    </Button>
                </EmptyState>
                <Pagination v-else :paginator="books" />
            </CardContent>
        </Card>

        <ConfirmDeleteDialog
            :open="!!deletingBook"
            @update:open="(open) => { if (!open) deletingBook = null }"
            title="Hapus Buku?"
            :description="
                deletingBook
                    ? `'${deletingBook.judul}' akan dihapus. Buku ber-riwayat pesanan tidak dapat dihapus.`
                    : ''
            "
            @confirm="executeDelete"
        />
    </div>
</template>

<script setup lang="ts">
defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Beranda', href: '/admin/dashboard' },
            { title: 'Inventori', href: '/admin/inventory' },
        ],
    },
});

import { Form, Head, router } from '@inertiajs/vue3';
import { Search, X } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import { toast } from 'vue-sonner';
import InventoryController from '@/actions/App/Http/Controllers/Admin/InventoryController';
import DataTable from '@/components/DataTable.vue';
import type { DataTableColumn } from '@/components/DataTable.vue';
import DataTableActions from '@/components/DataTableActions.vue';
import StatusBadge from '@/components/StatusBadge.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { index as indexRoute } from '@/routes/admin/inventory';

type Book = {
    id: string;
    judul: string;
    kode_sku: string | null;
    stok: number;
    stock_map: Record<string, number>;
    edition_stocks: Array<{
        id: string;
        cetakan_ke: number;
        nama: string | null;
        is_active: boolean;
        harga_beli: number;
        harga_jual: number;
        stocks: Record<string, number>;
    }>;
};

type Warehouse = {
    id: string;
    kode: string;
    nama: string;
    is_defect: boolean;
    is_active: boolean;
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
    warehouses: Warehouse[];
    filters: { search?: string; low_stock?: string };
    movementOptions: Record<string, string>;
    lowStockThreshold: number;
};

const props = defineProps<Props>();

const search = ref(props.filters.search ?? '');
const lowStock = ref(props.filters.low_stock === '1');
const movementOpen = ref(false);
const selectedBook = ref<Book | null>(null);
const selectedEditionId = ref<string>('');
const movementType = ref('in');

// Snapshot awal (nilai server saat load) untuk tombol Reset.
const initialSearch = props.filters.search ?? '';
const initialLowStock = props.filters.low_stock === '1';

const hasActiveFilters = computed(
    () => search.value !== initialSearch || lowStock.value !== initialLowStock,
);

let filterTimer: ReturnType<typeof setTimeout> | undefined;

function applyFilters() {
    clearTimeout(filterTimer);
    filterTimer = setTimeout(() => {
        router.get(
            indexRoute().url,
            {
                search: search.value || undefined,
                low_stock: lowStock.value ? '1' : undefined,
            },
            {
                preserveState: true,
                replace: true,
            },
        );
    }, 350);
}

function resetFilters() {
    search.value = initialSearch;
    lowStock.value = initialLowStock;
    applyFilters();
}

watch([search, lowStock], applyFilters);

const stock = (book: Book, kode: string) => book.stock_map?.[kode] ?? 0;

// Kolom gudang dinamis + data disiapkan agar nilai stok per gudang
// bisa dirender lewat key wh_{id} (default slot).
const columns = computed<DataTableColumn[]>(() => [
    {
        key: 'expand',
        header: '',
        srOnly: true,
        expandable: true,
        cellClass: 'w-10',
    },
    { key: 'judul', header: 'Buku' },
    ...props.warehouses.map((w) => ({
        key: `wh_${w.id}`,
        header: w.nama,
        cellClass: `text-right tabular-nums${w.is_defect ? ' text-muted-foreground' : ''}`,
    })),
    { key: 'stok', header: 'Total Normal', cellClass: 'text-right' },
    { key: 'aksi', header: 'Aksi', srOnly: true, cellClass: 'text-right' },
]);

// Baris yang di-expand (detail stok per cetakan).
const expandedIds = ref<Set<string>>(new Set());

const editionTotal = (edition: Book['edition_stocks'][number]): number =>
    Object.values(edition.stocks ?? {}).reduce((sum, qty) => sum + qty, 0);

const allStockTotal = (book: Book): number =>
    Object.values(book.stock_map ?? {}).reduce((sum, qty) => sum + qty, 0);

const displayRows = computed(() =>
    props.books.data.map((book) => {
        const row: Book & Record<string, any> = { ...book };

        props.warehouses.forEach((w) => {
            row[`wh_${w.id}`] = stock(book, w.kode);
        });

        return row;
    }),
);

function openMovement(book: Book) {
    selectedBook.value = book;
    // Default ke cetakan aktif (atau pertama).
    const defaultEdition =
        book.edition_stocks?.find((edition) => edition.is_active) ??
        book.edition_stocks?.[0];
    selectedEditionId.value = defaultEdition ? String(defaultEdition.id) : '';
    movementType.value = 'in';
    movementOpen.value = true;
}

const movementSummary = computed(() => {
    switch (movementType.value) {
        case 'in':
            return 'Stok masuk ke gudang tujuan.';
        case 'out':
            return 'Stok keluar dari gudang asal.';
        case 'transfer':
            return 'Pindah antar gudang (asal → tujuan).';
        case 'defect':
            return 'Pindah ke gudang defect — tidak pernah dijual.';
        case 'return':
            return 'Kembalikan stok defect ke supplier — keluar dari gudang defect.';
        default:
            return '';
    }
});

function onFormError() {
    toast.error(
        'Gagal menyimpan mutasi — periksa kembali isian yang wajib diisi.',
    );
}
</script>

<template>
    <Head title="Inventori" />

    <div class="flex flex-col gap-4 p-4 md:p-6">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div>
                <h1 class="text-xl font-semibold tracking-tight">
                    Inventori Multi-Gudang
                </h1>
                <p class="text-sm text-muted-foreground">
                    Mengelola stok multi-gudang dengan catatan mutasi lengkap
                </p>
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
                    placeholder="Cari judul / SKU..."
                />
            </div>
            <div class="md:flex md:items-center">
                <Label class="flex h-11 items-center gap-2 px-3 text-sm md:h-9">
                    <Checkbox v-model="lowStock" />
                    Stok menipis (≤ {{ lowStockThreshold }})
                </Label>
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
            :data="displayRows"
            :columns="columns"
            :paginator="books"
            expandable
            v-model:expanded-ids="expandedIds"
            empty-title="Tidak ada buku"
            empty-description="Buku dengan stok akan tampil di sini."
        >
            <template #cell-judul="{ row }">
                <p class="font-medium">{{ row.judul }}</p>
                <p class="text-xs text-muted-foreground">{{ row.kode_sku }}</p>
            </template>
            <template #cell-stok="{ row }">
                <StatusBadge
                    :variant="
                        row.stok <= lowStockThreshold ? 'warning' : 'success'
                    "
                    :label="String(row.stok)"
                />
            </template>
            <template #cell-aksi="{ row }">
                <DataTableActions
                    :actions="[
                        {
                            label: 'Mutasi',
                            onClick: () => openMovement(row),
                        },
                    ]"
                />
            </template>
            <template #expanded-row="{ row }">
                <div class="px-4 py-3">
                    <template v-if="(row.edition_stocks ?? []).length">
                        <div class="overflow-x-auto rounded-md border bg-card">
                            <table class="w-full text-sm">
                                <thead>
                                    <tr
                                        class="border-b text-left text-muted-foreground"
                                    >
                                        <th class="px-4 py-2 font-medium">
                                            Cetakan
                                        </th>
                                        <th
                                            v-for="w in warehouses"
                                            :key="w.id"
                                            class="px-4 py-2 text-right font-medium"
                                        >
                                            {{ w.nama }}
                                        </th>
                                        <th
                                            class="px-4 py-2 text-right font-medium"
                                        >
                                            Total
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr
                                        v-for="edition in row.edition_stocks"
                                        :key="edition.id"
                                        class="border-b last:border-0"
                                    >
                                        <td class="px-4 py-2 font-medium">
                                            {{
                                                edition.nama ??
                                                `Cetakan ke-${edition.cetakan_ke}`
                                            }}
                                        </td>
                                        <td
                                            v-for="w in warehouses"
                                            :key="w.id"
                                            class="px-4 py-2 text-right tabular-nums"
                                        >
                                            {{ edition.stocks?.[w.kode] ?? 0 }}
                                        </td>
                                        <td
                                            class="px-4 py-2 text-right font-semibold tabular-nums"
                                        >
                                            {{ editionTotal(edition) }}
                                        </td>
                                    </tr>
                                    <tr
                                        class="border-t bg-muted/50 font-semibold"
                                    >
                                        <td class="px-4 py-2">TOTAL</td>
                                        <td
                                            v-for="w in warehouses"
                                            :key="w.id"
                                            class="px-4 py-2 text-right tabular-nums"
                                        >
                                            {{ row.stock_map?.[w.kode] ?? 0 }}
                                        </td>
                                        <td
                                            class="px-4 py-2 text-right tabular-nums"
                                        >
                                            {{ allStockTotal(row) }}
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </template>
                    <p v-else class="text-sm text-muted-foreground">
                        Buku tanpa cetakan — stok langsung di level buku.
                    </p>
                </div>
            </template>
        </DataTable>

        <Dialog v-model:open="movementOpen">
            <DialogContent class="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle
                        >Mutasi Stok: {{ selectedBook?.judul }}</DialogTitle
                    >
                    <DialogDescription>{{ movementSummary }}</DialogDescription>
                </DialogHeader>

                <Form
                    v-if="selectedBook"
                    v-bind="InventoryController.store.form()"
                    class="grid gap-4"
                    v-slot="{ processing }"
                    @error="onFormError"
                    @success="movementOpen = false"
                >
                    <input
                        type="hidden"
                        name="book_id"
                        :value="String(selectedBook.id)"
                    />

                    <div class="grid gap-2">
                        <Label for="book_edition_id">Cetakan</Label>
                        <Select
                            v-model="selectedEditionId"
                            name="book_edition_id"
                        >
                            <SelectTrigger id="book_edition_id">
                                <SelectValue placeholder="Pilih cetakan" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem
                                    v-for="edition in selectedBook.edition_stocks ??
                                    []"
                                    :key="edition.id"
                                    :value="String(edition.id)"
                                >
                                    Cetakan ke-{{ edition.cetakan_ke }}
                                    <template v-if="edition.is_active">
                                        (default)
                                    </template>
                                </SelectItem>
                            </SelectContent>
                        </Select>
                    </div>

                    <div class="grid gap-2">
                        <Label for="type">Tipe Mutasi</Label>
                        <Select v-model="movementType" name="type">
                            <SelectTrigger id="type">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem
                                    v-for="(label, value) in movementOptions"
                                    :key="value"
                                    :value="value"
                                >
                                    {{ label }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                    </div>

                    <div class="grid gap-2">
                        <Label for="from_warehouse"
                            >Gudang Asal (transfer / keluar / defect /
                            retur)</Label
                        >
                        <Select name="from_warehouse">
                            <SelectTrigger id="from_warehouse">
                                <SelectValue placeholder="Pilih gudang" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem
                                    v-for="warehouse in warehouses"
                                    :key="warehouse.id"
                                    :value="String(warehouse.id)"
                                >
                                    {{ warehouse.nama }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                    </div>

                    <div class="grid gap-2">
                        <Label for="to_warehouse"
                            >Gudang Tujuan (masuk / transfer / defect)</Label
                        >
                        <Select name="to_warehouse">
                            <SelectTrigger id="to_warehouse">
                                <SelectValue placeholder="Pilih gudang" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem
                                    v-for="warehouse in warehouses"
                                    :key="warehouse.id"
                                    :value="String(warehouse.id)"
                                >
                                    {{ warehouse.nama }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                    </div>

                    <div class="grid gap-2">
                        <Label for="qty">Jumlah</Label>
                        <Input
                            id="qty"
                            name="qty"
                            type="number"
                            min="1"
                            required
                            placeholder="1"
                        />
                    </div>

                    <div class="grid gap-2">
                        <Label for="notes">Keterangan (opsional)</Label>
                        <Input
                            id="notes"
                            name="notes"
                            placeholder="Contoh: stok masuk dari penerbit"
                        />
                    </div>

                    <DialogFooter>
                        <Button type="submit" :disabled="processing">
                            {{ processing ? 'Menyimpan...' : 'Catat Mutasi' }}
                        </Button>
                    </DialogFooter>
                </Form>
            </DialogContent>
        </Dialog>
    </div>
</template>

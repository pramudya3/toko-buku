<script setup lang="ts">
import { Form, Head, router } from '@inertiajs/vue3';
import { Search } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import InventoryController from '@/actions/App/Http/Controllers/Admin/InventoryController';
import DataTableActions from '@/components/DataTableActions.vue';
import EmptyState from '@/components/EmptyState.vue';
import Pagination from '@/components/Pagination.vue';
import StatusBadge from '@/components/StatusBadge.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
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
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { index as indexRoute } from '@/routes/admin/inventory';

type Book = {
    id: number;
    judul: string;
    kode_sku: string | null;
    stok: number;
    inventory_stock: {
        stock_malang: number;
        stock_sidoarjo: number;
        stock_defect: number;
    } | null;
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
    filters: { search?: string; low_stock?: string };
    movementOptions: Record<string, string>;
    lowStockThreshold: number;
};

const props = defineProps<Props>();

const search = ref(props.filters.search ?? '');
const lowStock = ref(props.filters.low_stock === '1');
const movementOpen = ref(false);
const selectedBook = ref<Book | null>(null);
const movementType = ref('in');

let searchTimer: ReturnType<typeof setTimeout> | undefined;

watch([search, lowStock], () => {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(() => {
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
});

const stock = (book: Book) => ({
    malang: book.inventory_stock?.stock_malang ?? 0,
    sidoarjo: book.inventory_stock?.stock_sidoarjo ?? 0,
    defect: book.inventory_stock?.stock_defect ?? 0,
});

function openMovement(book: Book) {
    selectedBook.value = book;
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
        default:
            return '';
    }
});
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

        <div class="flex flex-wrap items-center gap-4">
            <div class="relative w-full max-w-sm">
                <Search
                    class="absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
                />
                <Input
                    v-model="search"
                    class="pl-9"
                    placeholder="Cari judul / SKU..."
                />
            </div>
            <Label class="flex items-center gap-2">
                <Checkbox v-model="lowStock" />
                Stok menipis (≤ {{ lowStockThreshold }})
            </Label>
        </div>

        <Card>
            <CardContent class="p-0">
                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead>Buku</TableHead>
                            <TableHead class="text-right">Malang</TableHead>
                            <TableHead class="text-right">Sidoarjo</TableHead>
                            <TableHead class="text-right">Defect</TableHead>
                            <TableHead class="text-right"
                                >Total Normal</TableHead
                            >
                            <TableHead class="text-right"><span class="sr-only">Aksi</span></TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <TableRow v-for="book in books.data" :key="book.id">
                            <TableCell>
                                <p class="font-medium">{{ book.judul }}</p>
                                <p class="text-xs text-muted-foreground">
                                    {{ book.kode_sku }}
                                </p>
                            </TableCell>
                            <TableCell class="text-right tabular-nums">{{
                                stock(book).malang
                            }}</TableCell>
                            <TableCell class="text-right tabular-nums">{{
                                stock(book).sidoarjo
                            }}</TableCell>
                            <TableCell
                                class="text-right text-muted-foreground tabular-nums"
                                >{{ stock(book).defect }}</TableCell
                            >
                            <TableCell class="text-right">
                                <StatusBadge
                                    :variant="
                                        book.stok <= lowStockThreshold
                                            ? 'warning'
                                            : 'success'
                                    "
                                    :label="String(book.stok)"
                                />
                            </TableCell>
                            <TableCell class="text-right">
                                <DataTableActions
                                    :actions="[
                                        {
                                            label: 'Mutasi',
                                            onClick: () => openMovement(book),
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
                    description="Buku dengan stok akan tampil di sini."
                />
                <Pagination v-else :paginator="books" />
            </CardContent>
        </Card>

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
                    v-slot="{ errors, processing }"
                >
                    <input
                        type="hidden"
                        name="book_id"
                        :value="String(selectedBook.id)"
                    />

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
                            >Gudang Asal (transfer / keluar / defect)</Label
                        >
                        <Select name="from_warehouse">
                            <SelectTrigger id="from_warehouse">
                                <SelectValue placeholder="Pilih gudang" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="malang">Malang</SelectItem>
                                <SelectItem value="sidoarjo"
                                    >Sidoarjo</SelectItem
                                >
                            </SelectContent>
                        </Select>
                        <span
                            v-if="errors.from_warehouse"
                            class="text-sm text-destructive"
                            >{{ errors.from_warehouse }}</span
                        >
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
                                <SelectItem value="malang">Malang</SelectItem>
                                <SelectItem value="sidoarjo"
                                    >Sidoarjo</SelectItem
                                >
                                <SelectItem value="defect">Defect</SelectItem>
                            </SelectContent>
                        </Select>
                        <span
                            v-if="errors.to_warehouse"
                            class="text-sm text-destructive"
                            >{{ errors.to_warehouse }}</span
                        >
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
                        <span
                            v-if="errors.qty"
                            class="text-sm text-destructive"
                            >{{ errors.qty }}</span
                        >
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

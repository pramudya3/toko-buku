<script setup lang="ts">
defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Beranda', href: '/admin/dashboard' },
            { title: 'Stok Adjustment', href: '/admin/inventory-adjustments' },
        ],
    },
});

import { Form, Head } from '@inertiajs/vue3';
import { Plus } from '@lucide/vue';
import { computed, ref } from 'vue';
import { toast } from 'vue-sonner';
import InventoryAdjustmentController from '@/actions/App/Http/Controllers/Admin/InventoryAdjustmentController';
import BookPicker from '@/components/BookPicker.vue';
import type { BookOption } from '@/components/BookPicker.vue';
import DataTable from '@/components/DataTable.vue';
import type { DataTableColumn } from '@/components/DataTable.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
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
import { Textarea } from '@/components/ui/textarea';
import { books as bookOptions } from '@/routes/admin/inventory-adjustments/options';

// BookPicker hanya butuh id/judul/kode_sku; tipe lokal menambah field
// yang dikirim endpoint adjustment (stok + cetakan untuk form).
type PickedBook = BookOption & {
    stok: number;
    editions?: Array<{
        id: string;
        cetakan_ke: number;
        is_active: boolean;
        harga_jual: number;
    }>;
};

type Warehouse = {
    id: string;
    kode: string;
    nama: string;
    is_defect: boolean;
    is_active: boolean;
};

type Adjustment = {
    id: string;
    qty: number;
    notes: string | null;
    created_at: string;
    book: { id: string; judul: string; kode_sku: string | null } | null;
    edition: { id: string; cetakan_ke: number } | null;
    from_warehouse: { id: string; nama: string } | null;
    to_warehouse: { id: string; nama: string } | null;
    user: { id: string; name: string } | null;
};

defineProps<{
    adjustments: Adjustment[];
    warehouses: Warehouse[];
}>();

// --- Pemilihan buku (BookPicker) & preview stok ---
const dialogOpen = ref(false);
const selectedBook = ref<PickedBook | null>(null);
const selectedEditionId = ref('');
const warehouseId = ref('');
const qty = ref('');
const notes = ref('');

const qtyNumber = computed(() => Number(qty.value) || 0);
const currentStock = computed(() => selectedBook.value?.stok ?? 0);
const afterStock = computed(() => currentStock.value + qtyNumber.value);

function onBookSelect(book: BookOption) {
    const picked = book as PickedBook;
    selectedBook.value = picked;

    const defaultEdition =
        picked.editions?.find((edition) => edition.is_active) ??
        picked.editions?.[0];
    selectedEditionId.value = defaultEdition ? String(defaultEdition.id) : '';
}

function clearBook() {
    selectedBook.value = null;
    selectedEditionId.value = '';
}

function openDialog() {
    // Reset semua isian agar tidak ada sisa dari input sebelumnya.
    clearBook();
    warehouseId.value = '';
    qty.value = '';
    notes.value = '';
    dialogOpen.value = true;
}

// --- Riwayat ---
const historyColumns: DataTableColumn[] = [
    {
        key: 'created_at',
        header: 'Tanggal',
        cellClass: 'whitespace-nowrap',
    },
    { key: 'buku', header: 'Buku', cellClass: 'max-w-52' },
    { key: 'cetakan', header: 'Cetakan' },
    { key: 'gudang', header: 'Gudang' },
    {
        key: 'qty',
        header: 'Selisih',
        cellClass: 'text-right tabular-nums',
    },
    { key: 'notes', header: 'Alasan', cellClass: 'max-w-48 truncate' },
    { key: 'user', header: 'Petugas' },
];

function formatDate(date: string): string {
    return new Date(date).toLocaleString('id-ID', {
        dateStyle: 'medium',
        timeStyle: 'short',
    });
}

function onFormError() {
    toast.error('Gagal menyimpan — periksa kembali isian yang wajib diisi.');
}
</script>

<template>

    <Head title="Stok Adjustment" />

    <div class="flex flex-col gap-4 p-4 md:p-6">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div>
                <h1 class="text-xl font-semibold tracking-tight">
                    Stok Adjustment
                </h1>
                <p class="text-sm text-muted-foreground">
                    Koreksi stok hasil opname fisik — selisih stok sistem vs
                    aktual, dicatat sebagai mutasi adjustment
                </p>
            </div>
            <Button @click="openDialog">
                <Plus class="size-4" />
                Catat Adjustment
            </Button>
        </div>


        <DataTable :data="adjustments" :columns="historyColumns" empty-title="Belum ada adjustment"
            empty-description="Koreksi stok yang dicatat akan tampil di sini.">
            <template #cell-created_at="{ row }">
                {{ formatDate(row.created_at) }}
            </template>
            <template #cell-buku="{ row }">
                <p class="truncate font-medium">
                    {{ row.book?.judul ?? '—' }}
                </p>
                <p class="text-xs text-muted-foreground">
                    {{ row.book?.kode_sku }}
                </p>
            </template>
            <template #cell-cetakan="{ row }">
                <template v-if="row.edition">
                    Cetakan ke-{{ row.edition.cetakan_ke }}
                </template>
                <span v-else class="text-muted-foreground">—</span>
            </template>
            <template #cell-gudang="{ row }">
                {{
                    row.from_warehouse?.nama ??
                    row.to_warehouse?.nama ??
                    '—'
                }}
            </template>
            <template #cell-qty="{ row }">
                <span :class="row.qty > 0
                    ? 'font-medium text-green-600'
                    : 'font-medium text-destructive'
                    ">
                    {{ row.qty > 0 ? `+${row.qty}` : row.qty }}
                </span>
            </template>
            <template #cell-notes="{ row }">
                {{ row.notes ?? '—' }}
            </template>
            <template #cell-user="{ row }">
                {{ row.user?.name ?? '—' }}
            </template>
        </DataTable>

    </div>

    <Dialog v-model:open="dialogOpen">
        <DialogContent class="sm:max-w-md">
            <DialogHeader>
                <DialogTitle>Catat Stok Adjustment</DialogTitle>
                <DialogDescription>
                    Koreksi stok hasil opname fisik — selisih stok sistem vs
                    aktual.
                </DialogDescription>
            </DialogHeader>

            <Form v-bind="InventoryAdjustmentController.store.form()" class="grid gap-4" v-slot="{ errors, processing }"
                @error="onFormError" @success="dialogOpen = false">
                <p v-if="
                    errors.book_id ||
                    errors.warehouse_id ||
                    errors.qty ||
                    errors.notes
                " class="rounded-lg border border-destructive/40 bg-destructive/5 px-3 py-2 text-sm text-destructive">
                    Periksa kembali isian formulir.
                </p>

                <!-- Buku -->
                <div class="grid gap-2">
                    <Label>Buku *</Label>
                    <div v-if="selectedBook" class="flex items-center gap-2">
                        <div class="flex-1 rounded-md border px-3 py-2">
                            <p class="font-medium">
                                {{ selectedBook.judul }}
                            </p>
                            <p class="text-xs text-muted-foreground">
                                {{ selectedBook.kode_sku }}
                            </p>
                        </div>
                        <Button type="button" variant="ghost" size="sm" @click="clearBook">
                            Ganti
                        </Button>
                        <input type="hidden" name="book_id" :value="String(selectedBook.id)" />
                    </div>
                    <div v-else>
                        <BookPicker :base-url="bookOptions().url" placeholder="Cari judul / SKU buku..."
                            @select="onBookSelect" />
                    </div>
                </div>

                <!-- Cetakan -->
                <div v-if="selectedBook?.editions?.length" class="grid gap-2">
                    <Label for="book_edition_id">Cetakan</Label>
                    <Select v-model="selectedEditionId" name="book_edition_id">
                        <SelectTrigger id="book_edition_id">
                            <SelectValue placeholder="Pilih cetakan" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem v-for="edition in selectedBook.editions" :key="edition.id"
                                :value="String(edition.id)">
                                Cetakan ke-{{ edition.cetakan_ke }}
                                <template v-if="edition.is_active">
                                    (default)
                                </template>
                            </SelectItem>
                        </SelectContent>
                    </Select>
                </div>
                <p v-else-if="selectedBook" class="text-sm text-muted-foreground">
                    Buku tanpa cetakan — penyesuaian di level buku.
                </p>

                <!-- Gudang -->
                <div class="grid gap-2">
                    <Label for="warehouse_id">Gudang *</Label>
                    <Select v-model="warehouseId" name="warehouse_id">
                        <SelectTrigger id="warehouse_id">
                            <SelectValue placeholder="Pilih gudang" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem v-for="warehouse in warehouses" :key="warehouse.id"
                                :value="String(warehouse.id)">
                                {{ warehouse.nama
                                }}{{ warehouse.is_defect ? ' (defect)' : '' }}
                            </SelectItem>
                        </SelectContent>
                    </Select>
                </div>

                <!-- Selisih -->
                <div class="grid gap-2">
                    <Label for="qty">Selisih Stok *</Label>
                    <Input id="qty" name="qty" v-model="qty" type="number"
                        placeholder="Contoh: 5 (lebih) atau -3 (kurang)" />
                    <p v-if="selectedBook && qtyNumber !== 0" class="text-sm" :class="qtyNumber > 0
                        ? 'text-green-600'
                        : 'text-destructive'
                        ">
                        Stok saat ini (total normal): {{ currentStock }} →
                        setelah: {{ afterStock }}
                    </p>
                    <p v-else-if="selectedBook" class="text-sm text-muted-foreground">
                        Stok saat ini (total normal): {{ currentStock }}
                    </p>
                    <p class="text-xs text-muted-foreground">
                        Nilai positif menambah stok, negatif menguranginya.
                    </p>
                </div>

                <!-- Alasan -->
                <div class="grid gap-2">
                    <Label for="notes">Alasan *</Label>
                    <Textarea id="notes" name="notes" v-model="notes" rows="2"
                        placeholder="Contoh: selisih hasil opname 31 Januari" />
                </div>

                <DialogFooter>
                    <Button type="submit" :disabled="processing">
                        {{ processing ? 'Menyimpan...' : 'Catat Adjustment' }}
                    </Button>
                </DialogFooter>
            </Form>
        </DialogContent>
    </Dialog>
</template>

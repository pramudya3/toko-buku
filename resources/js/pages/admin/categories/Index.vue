<script setup lang="ts">
import { Form, Head, router } from '@inertiajs/vue3';
import { Plus, Search, X } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import CategoryController from '@/actions/App/Http/Controllers/Admin/CategoryController';
import ConfirmDeleteDialog from '@/components/ConfirmDeleteDialog.vue';
import DataTable from '@/components/DataTable.vue';
import type { DataTableColumn } from '@/components/DataTable.vue';
import DataTableActions from '@/components/DataTableActions.vue';
import { Button } from '@/components/ui/button';
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
import { index as indexRoute } from '@/routes/admin/categories';

type Category = {
    id: string;
    nama: string;
    books_count: number;
};

type Props = {
    categories: {
        data: Category[];
        current_page: number;
        last_page: number;
        total: number;
        per_page: number;
        links: Array<{ url: string | null; label: string; active: boolean }>;
    };
    filters: { search?: string };
};

const props = defineProps<Props>();

const columns: DataTableColumn[] = [
    { key: 'nama', header: 'Nama', cellClass: 'font-medium' },
    { key: 'books_count', header: 'Jumlah Buku' },
    { key: 'aksi', header: 'Aksi', srOnly: true, cellClass: 'text-right' },
];

const search = ref(props.filters.search ?? '');

// Snapshot awal (nilai server saat load) untuk tombol Reset.
const initialSearch = props.filters.search ?? '';

const hasActiveFilters = computed(() => search.value !== initialSearch);

let filterTimer: ReturnType<typeof setTimeout> | undefined;

function applyFilters() {
    clearTimeout(filterTimer);
    filterTimer = setTimeout(() => {
        router.get(
            indexRoute().url,
            { search: search.value || undefined },
            {
                preserveState: true,
                replace: true,
            },
        );
    }, 350);
}

function resetFilters() {
    search.value = initialSearch;
    applyFilters();
}

watch([search], applyFilters);

const editing = ref<Category | null>(null);
const dialogOpen = ref(false);
const deletingCategory = ref<Category | null>(null);

function openEdit(category: Category) {
    editing.value = category;
    dialogOpen.value = true;
}

function confirmDelete(category: Category) {
    deletingCategory.value = category;
}

function openCreate() {
    editing.value = null;
    dialogOpen.value = true;
}

function executeDelete() {
    if (!deletingCategory.value) {
        return;
    }

    const category = deletingCategory.value;

    deletingCategory.value = null;
    router.delete(CategoryController.destroy(category.id).url, {
        preserveScroll: true,
    });
}
</script>

<template>
    <Head title="Kategori" />

    <div class="flex flex-col gap-4 p-4 md:p-6">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div>
                <h1 class="text-xl font-semibold tracking-tight">Kategori</h1>
                <p class="text-sm text-muted-foreground">
                    Kelompokkan buku agar mudah dicari
                </p>
            </div>
            <Button @click="openCreate">
                <Plus class="size-4" />
                Buat Kategori
            </Button>
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
                    class="h-11 w-full rounded-none border-0 bg-transparent pl-9 shadow-none focus-visible:ring-0 md:h-9 md:w-56"
                    placeholder="Cari kategori..."
                />
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
            :data="categories.data"
            :columns="columns"
            :paginator="categories"
            empty-title="Tidak ada kategori"
            empty-description="Buat kategori pertama untuk mengelompokkan buku."
        >
            <template #cell-books_count="{ row }">
                {{ row.books_count }} buku
            </template>
            <template #cell-aksi="{ row }">
                <DataTableActions
                    :actions="[
                        {
                            label: 'Edit',
                            onClick: () => openEdit(row),
                        },
                        {
                            label: 'Hapus',
                            variant: 'destructive',
                            onClick: () => confirmDelete(row),
                        },
                    ]"
                />
            </template>
        </DataTable>

        <Dialog v-model:open="dialogOpen">
            <DialogContent class="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>{{
                        editing ? 'Edit Kategori' : 'Buat Kategori'
                    }}</DialogTitle>
                    <DialogDescription>
                        Slug dipakai untuk URL & harus unik.
                    </DialogDescription>
                </DialogHeader>

                <Form
                    v-if="editing"
                    v-bind="CategoryController.update.form(editing.id)"
                    class="grid gap-4"
                    v-slot="{ errors, processing }"
                >
                    <div class="grid gap-2">
                        <Label for="nama">Nama</Label>
                        <Input
                            id="nama"
                            name="nama"
                            :default-value="editing.nama"
                            required
                        />
                        <span
                            v-if="errors.nama"
                            class="text-sm text-destructive"
                            >{{ errors.nama }}</span
                        >
                    </div>
                    <DialogFooter>
                        <Button type="submit" :disabled="processing"
                            >Simpan</Button
                        >
                    </DialogFooter>
                </Form>

                <Form
                    v-else
                    v-bind="CategoryController.store.form()"
                    class="grid gap-4"
                    v-slot="{ errors, processing }"
                >
                    <div class="grid gap-2">
                        <Label for="nama">Nama</Label>
                        <Input
                            id="nama"
                            name="nama"
                            required
                            placeholder="Contoh: Fiksi"
                        />
                        <span
                            v-if="errors.nama"
                            class="text-sm text-destructive"
                            >{{ errors.nama }}</span
                        >
                    </div>
                    <DialogFooter>
                        <Button type="submit" :disabled="processing"
                            >Buat</Button
                        >
                    </DialogFooter>
                </Form>
            </DialogContent>
        </Dialog>

        <ConfirmDeleteDialog
            :open="!!deletingCategory"
            @update:open="
                (open) => {
                    if (!open) deletingCategory = null;
                }
            "
            title="Hapus Kategori?"
            :description="
                deletingCategory
                    ? `Kategori '${deletingCategory.nama}' akan dihapus.`
                    : ''
            "
            @confirm="executeDelete"
        />
    </div>
</template>

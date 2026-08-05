<script setup lang="ts">
import { Form, Head, router } from '@inertiajs/vue3';
import { Pencil, Plus, Search, Trash2 } from '@lucide/vue';
import { ref, watch } from 'vue';
import CategoryController from '@/actions/App/Http/Controllers/Admin/CategoryController';
import ConfirmDeleteDialog from '@/components/ConfirmDeleteDialog.vue';
import DataTableActions from '@/components/DataTableActions.vue';
import EmptyState from '@/components/EmptyState.vue';
import Pagination from '@/components/Pagination.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
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
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { index as indexRoute } from '@/routes/admin/categories';

type Category = {
    id: number;
    nama: string;
    slug: string;
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

const search = ref(props.filters.search ?? '');
const editing = ref<Category | null>(null);
const dialogOpen = ref(false);

let searchTimer: ReturnType<typeof setTimeout> | undefined;

watch(search, () => {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(() => {
        router.get(
            indexRoute().url,
            { search: search.value || undefined },
            {
                preserveState: true,
                replace: true,
            },
        );
    }, 350);
});

function openCreate() {
    editing.value = null;
    dialogOpen.value = true;
}

function openEdit(category: Category) {
    editing.value = category;
    dialogOpen.value = true;
}

function confirmDelete(category: Category) {
    deletingCategory.value = category;
}

const deletingCategory = ref<Category | null>(null);

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

        <div class="relative max-w-sm">
            <Search
                class="absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
            />
            <Input
                v-model="search"
                class="pl-9"
                placeholder="Cari kategori..."
            />
        </div>

        <Card>
            <CardContent class="p-0">
                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead>Nama</TableHead>
                            <TableHead>Slug</TableHead>
                            <TableHead>Jumlah Buku</TableHead>
                            <TableHead class="text-right"><span class="sr-only">Aksi</span></TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <TableRow
                            v-for="category in categories.data"
                            :key="category.id"
                        >
                            <TableCell class="font-medium">{{
                                category.nama
                            }}</TableCell>
                            <TableCell
                                class="font-mono text-xs text-muted-foreground"
                                >{{ category.slug }}</TableCell
                            >
                            <TableCell
                                >{{ category.books_count }} buku</TableCell
                            >
                            <TableCell class="text-right">
                                <DataTableActions
                                    :actions="[
                                        {
                                            label: 'Edit',
                                            icon: Pencil,
                                            onClick: () => openEdit(category),
                                        },
                                        {
                                            label: 'Hapus',
                                            icon: Trash2,
                                            variant: 'destructive',
                                            onClick: () =>
                                                confirmDelete(category),
                                        },
                                    ]"
                                />
                            </TableCell>
                        </TableRow>
                    </TableBody>
                </Table>
                <EmptyState
                    v-if="!categories.data.length"
                    title="Tidak ada kategori"
                    description="Buat kategori pertama untuk mengelompokkan buku."
                />
                <Pagination v-else :paginator="categories" />
            </CardContent>
        </Card>

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
                    <div class="grid gap-2">
                        <Label for="slug">Slug</Label>
                        <Input
                            id="slug"
                            name="slug"
                            :default-value="editing.slug"
                            required
                        />
                        <span
                            v-if="errors.slug"
                            class="text-sm text-destructive"
                            >{{ errors.slug }}</span
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
                    <div class="grid gap-2">
                        <Label for="slug">Slug</Label>
                        <Input
                            id="slug"
                            name="slug"
                            required
                            placeholder="fiksi"
                        />
                        <span
                            v-if="errors.slug"
                            class="text-sm text-destructive"
                            >{{ errors.slug }}</span
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
            @update:open="(open) => { if (!open) deletingCategory = null }"
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

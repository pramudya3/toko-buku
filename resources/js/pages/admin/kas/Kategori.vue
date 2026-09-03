<script setup lang="ts">
defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Beranda', href: '/admin/dashboard' },
            { title: 'Kas', href: '/admin/kas' },
            { title: 'Kategori Kas' },
        ],
    },
});

import { Form, Head, router } from '@inertiajs/vue3';
import { Pencil, Plus, Trash2 } from '@lucide/vue';
import { ref } from 'vue';
import KasCategoryController from '@/actions/App/Http/Controllers/Admin/KasCategoryController';
import ConfirmDeleteDialog from '@/components/ConfirmDeleteDialog.vue';
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

type KasSub = {
    id: string;
    cash_flow_category_id: string;
    nama: string;
    description?: string | null;
    sort_order: number;
    is_active: boolean;
};

type KasCategory = {
    id: string;
    nama: string;
    sort_order: number;
    is_active: boolean;
    sub_categories?: KasSub[];
    subCategories?: KasSub[];
};

defineProps<{
    categories: KasCategory[];
    activeCategories: Array<{ id: string; nama: string }>;
}>();

const categoryDialogOpen = ref(false);
const editingCategory = ref<KasCategory | null>(null);
const categoryName = ref('');

function openCreateCategory() {
    editingCategory.value = null;
    categoryName.value = '';
    categoryDialogOpen.value = true;
}

function openEditCategory(cat: KasCategory) {
    editingCategory.value = cat;
    categoryName.value = cat.nama;
    categoryDialogOpen.value = true;
}

// Sub kategori
const subDialogOpen = ref(false);
const editingSub = ref<KasSub | null>(null);
const subCategoryId = ref('');
const subName = ref('');

function openCreateSub(catId?: string) {
    editingSub.value = null;
    subCategoryId.value = catId ?? '';
    subName.value = '';
    subDialogOpen.value = true;
}

function openEditSub(sub: KasSub) {
    editingSub.value = sub;
    subCategoryId.value = sub.cash_flow_category_id;
    subName.value = sub.nama;
    subDialogOpen.value = true;
}

const deletingCategory = ref<KasCategory | null>(null);
const deletingSub = ref<KasSub | null>(null);

function confirmDeleteCategory(cat: KasCategory) {
    deletingCategory.value = cat;
}

function confirmDeleteSub(sub: KasSub) {
    deletingSub.value = sub;
}
</script>

<template>
    <Head title="Kategori Kas" />

    <div class="mx-auto flex w-full max-w-7xl flex-col gap-3 p-3 md:p-4">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div>
                <h1 class="text-xl font-semibold tracking-tight">
                    Kategori Kas
                </h1>
                <p class="text-sm text-muted-foreground">
                    Kelola klasifikasi kategori dan subkategori untuk pencatatan
                    kas.
                </p>
            </div>
            <div class="flex gap-2">
                <Button variant="outline" @click="openCreateSub()">
                    <Plus class="size-4" />
                    Tambah Subkategori
                </Button>
                <Button @click="openCreateCategory">
                    <Plus class="size-4" />
                    Tambah Kategori
                </Button>
            </div>
        </div>

        <div class="grid gap-4">
            <Card v-for="cat in categories" :key="cat.id">
                <CardHeader
                    class="flex flex-row items-center justify-between space-y-0 pb-2"
                >
                    <CardTitle class="flex items-center gap-2 text-base">
                        {{ cat.nama }}
                        <span class="text-xs font-normal text-muted-foreground"
                            >({{
                                (cat.sub_categories ?? cat.subCategories ?? [])
                                    .length
                            }}
                            sub)</span
                        >
                    </CardTitle>
                    <div class="flex gap-1">
                        <Button
                            variant="ghost"
                            size="icon"
                            class="size-7"
                            @click="openEditCategory(cat)"
                        >
                            <Pencil class="size-3.5" />
                        </Button>
                        <Button
                            variant="ghost"
                            size="icon"
                            class="size-7 text-destructive"
                            @click="confirmDeleteCategory(cat)"
                        >
                            <Trash2 class="size-3.5" />
                        </Button>
                        <Button
                            variant="ghost"
                            size="sm"
                            @click="openCreateSub(cat.id)"
                            >+ Sub</Button
                        >
                    </div>
                </CardHeader>
                <CardContent>
                    <div
                        v-if="
                            (cat.sub_categories ?? cat.subCategories ?? [])
                                .length === 0
                        "
                        class="text-sm text-muted-foreground"
                    >
                        Belum ada subkategori pada kategori ini.
                    </div>
                    <ul v-else class="divide-y divide-border rounded-md border">
                        <li
                            v-for="sub in cat.sub_categories ??
                            cat.subCategories ??
                            []"
                            :key="sub.id"
                            class="flex items-center justify-between px-3 py-2 text-sm"
                        >
                            <span>{{ sub.nama }}</span>
                            <span class="flex gap-1">
                                <Button
                                    variant="ghost"
                                    size="icon"
                                    class="size-6"
                                    @click="openEditSub(sub)"
                                >
                                    <Pencil class="size-3" />
                                </Button>
                                <Button
                                    variant="ghost"
                                    size="icon"
                                    class="size-6 text-destructive"
                                    @click="confirmDeleteSub(sub)"
                                >
                                    <Trash2 class="size-3" />
                                </Button>
                            </span>
                        </li>
                    </ul>
                </CardContent>
            </Card>

            <div
                v-if="categories.length === 0"
                class="rounded-md border border-dashed p-8 text-center text-sm text-muted-foreground"
            >
                Belum ada kategori. Silakan buat kategori baru untuk memulai
                pencatatan.
            </div>
        </div>
    </div>

    <!-- Dialog Kategori -->
    <Dialog v-model:open="categoryDialogOpen">
        <DialogContent class="sm:max-w-md">
            <DialogHeader>
                <DialogTitle>{{
                    editingCategory ? 'Edit Kategori' : 'Buat Kategori'
                }}</DialogTitle>
                <DialogDescription
                    >Masukkan nama kategori sesuai klasifikasi pencatatan
                    kas.</DialogDescription
                >
            </DialogHeader>

            <Form
                v-if="editingCategory"
                v-bind="
                    KasCategoryController.updateCategory.form(
                        editingCategory.id,
                    )
                "
                class="grid gap-4"
                v-slot="{ errors, processing }"
                @success="categoryDialogOpen = false"
            >
                <div class="grid gap-2">
                    <Label>Nama Kategori *</Label>
                    <Input name="nama" :default-value="categoryName" required />
                    <span v-if="errors.nama" class="text-sm text-destructive">{{
                        errors.nama
                    }}</span>
                </div>
                <DialogFooter>
                    <Button type="submit" :disabled="processing">Catat</Button>
                </DialogFooter>
            </Form>

            <Form
                v-else
                v-bind="KasCategoryController.storeCategory.form()"
                class="grid gap-4"
                v-slot="{ errors, processing }"
                @success="categoryDialogOpen = false"
            >
                <div class="grid gap-2">
                    <Label>Nama Kategori *</Label>
                    <Input
                        name="nama"
                        placeholder="Contoh: Pemasaran & Promosi"
                        required
                    />
                    <span v-if="errors.nama" class="text-sm text-destructive">{{
                        errors.nama
                    }}</span>
                </div>
                <DialogFooter>
                    <Button type="submit" :disabled="processing">Catat</Button>
                </DialogFooter>
            </Form>
        </DialogContent>
    </Dialog>

    <!-- Dialog Sub -->
    <Dialog v-model:open="subDialogOpen">
        <DialogContent class="sm:max-w-md">
            <DialogHeader>
                <DialogTitle>{{
                    editingSub ? 'Ubah Subkategori' : 'Tambah Subkategori'
                }}</DialogTitle>
                <DialogDescription
                    >Pilih kategori induk dan tentukan nama
                    subkategori.</DialogDescription
                >
            </DialogHeader>

            <Form
                v-if="editingSub"
                v-bind="KasCategoryController.updateSub.form(editingSub.id)"
                class="grid gap-4"
                v-slot="{ errors, processing }"
                @success="subDialogOpen = false"
            >
                <div class="grid gap-2">
                    <Label>Nama Subkategori *</Label>
                    <Input
                        name="nama"
                        :default-value="editingSub.nama"
                        required
                    />
                    <span v-if="errors.nama" class="text-sm text-destructive">{{
                        errors.nama
                    }}</span>
                </div>
                <DialogFooter>
                    <Button type="submit" :disabled="processing">Catat</Button>
                </DialogFooter>
            </Form>

            <Form
                v-else
                v-bind="KasCategoryController.storeSub.form()"
                class="grid gap-4"
                v-slot="{ errors, processing }"
                @success="subDialogOpen = false"
            >
                <div class="grid gap-2">
                    <Label>Kategori Induk *</Label>
                    <Select v-model="subCategoryId">
                        <SelectTrigger>
                            <SelectValue placeholder="Pilih kategori induk" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem
                                v-for="cat in categories"
                                :key="cat.id"
                                :value="cat.id"
                                >{{ cat.nama }}</SelectItem
                            >
                        </SelectContent>
                    </Select>
                    <input
                        type="hidden"
                        name="kas_category_id"
                        :value="subCategoryId"
                    />
                    <span
                        v-if="errors.kas_category_id"
                        class="text-sm text-destructive"
                        >{{ errors.kas_category_id }}</span
                    >
                </div>
                <div class="grid gap-2">
                    <Label>Nama Subkategori *</Label>
                    <Input
                        name="nama"
                        placeholder="Contoh: Iklan Digital"
                        required
                    />
                    <span v-if="errors.nama" class="text-sm text-destructive">{{
                        errors.nama
                    }}</span>
                </div>
                <DialogFooter>
                    <Button type="submit" :disabled="processing">Catat</Button>
                </DialogFooter>
            </Form>
        </DialogContent>
    </Dialog>

    <ConfirmDeleteDialog
        :open="!!deletingCategory"
        title="Hapus Kategori?"
        :description="
            deletingCategory
                ? `Kategori '${deletingCategory.nama}' akan dihapus secara permanen. Tindakan ini tidak dapat dibatalkan.`
                : ''
        "
        @update:open="
            (o) => {
                if (!o) deletingCategory = null;
            }
        "
        @confirm="
            () => {
                if (deletingCategory) {
                    router.delete(
                        KasCategoryController.destroyCategory(
                            deletingCategory.id,
                        ).url,
                    );
                    deletingCategory = null;
                }
            }
        "
    />
    <ConfirmDeleteDialog
        :open="!!deletingSub"
        title="Hapus Subkategori?"
        :description="
            deletingSub
                ? `Subkategori '${deletingSub.nama}' akan dihapus secara permanen.`
                : ''
        "
        @update:open="
            (o) => {
                if (!o) deletingSub = null;
            }
        "
        @confirm="
            () => {
                if (deletingSub) {
                    router.delete(
                        KasCategoryController.destroySub(deletingSub.id).url,
                    );
                    deletingSub = null;
                }
            }
        "
    />
</template>

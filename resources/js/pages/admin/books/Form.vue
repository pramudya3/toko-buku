<script setup lang="ts">
defineOptions({
    layout: (pageProps: any) => ({
        breadcrumbs: [
            { title: 'Beranda', href: '/admin/dashboard' },
            { title: 'Buku', href: '/admin/books' },
            { title: pageProps.book ? 'Edit' : 'Tambah' },
        ],
    }),
});

import { Form, Head, Link } from '@inertiajs/vue3';
import { Plus, Trash2, X } from '@lucide/vue';
import { computed, onBeforeUnmount, ref } from 'vue';
import { toast } from 'vue-sonner';
import BookController from '@/actions/App/Http/Controllers/Admin/BookController';
import CurrencyInput from '@/components/CurrencyInput.vue';
import FieldHint from '@/components/FieldHint.vue';
import FormErrorAlert from '@/components/FormErrorAlert.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
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
import { index as indexRoute } from '@/routes/admin/books';

type Category = { id: string; nama: string };

type Book = {
    id: string;
    kode_sku: string | null;
    judul: string;
    penulis: string | null;
    penterjemah: string | null;
    penerbit: string | null;
    tahun: number | null;
    isbn: string | null;
    sinopsis: string | null;
    harga: number;
    category_id: string | null;
    cover_url: string | null;
    aktif: boolean;
    is_preorder: boolean;
    preorder_eta: string | null;
    rating_umur: string | null;
    dimensi: string | null;
    kemasan: string | null;
    berat_gr: number | null;
    jumlah_halaman: number | null;
    jenis_kertas: string | null;
    cetakan: string | null;
    bahasa: string | null;
    jenis_cover: string | null;
};

type BookEdition = {
    id?: string;
    nama: string;
    cetakan_ke: number;
    harga_beli: number;
    harga_jual: number;
    is_active: boolean;
};

type BookImage = {
    id: string;
    image_url: string;
    urutan: number;
};

const props = defineProps<{
    book: Book | null;
    editions: BookEdition[];
    images: BookImage[];
    categories: Category[];
}>();

const MAX_GALLERY_IMAGES = 5;
const MAX_IMAGE_SIZE_MB = 2;
const MAX_IMAGE_SIZE = MAX_IMAGE_SIZE_MB * 1024 * 1024;
const MAX_UPLOAD_RAW_SIZE = 15 * 1024 * 1024; // batas aman sebelum kompres
const MAX_IMAGE_DIMENSION = 1600;
const JPEG_QUALITY = 0.82;
const existingImages = ref<BookImage[]>(props.images ?? []);
const removedImages = ref<string[]>([]);
const newPreviews = ref<{ url: string; file: File }[]>([]);
const coverPreview = ref<string | null>(null);
const removeCover = ref(false);
const aktifValue = ref<string>(
    props.book ? (props.book.aktif ? '1' : '0') : '1',
);
const isPreorderValue = ref<string>(
    props.book ? (props.book.is_preorder ? '1' : '0') : '0',
);
const coverPreviewSrc = computed<string | undefined>(() => {
    if (coverPreview.value) {
        return coverPreview.value;
    }

    return removeCover.value ? undefined : (props.book?.cover_url ?? undefined);
});

function loadImage(file: File): Promise<HTMLImageElement> {
    return new Promise((resolve, reject) => {
        const url = URL.createObjectURL(file);
        const img = new Image();

        img.onload = () => {
            URL.revokeObjectURL(url);
            resolve(img);
        };
        img.onerror = () => {
            URL.revokeObjectURL(url);
            reject(new Error('Gagal membaca gambar.'));
        };
        img.src = url;
    });
}

/**
 * Kompres gambar: resize maks 1600px + JPEG 82% + latar putih (PNG transparan).
 * Mengembalikan file asli bila hasilnya tidak lebih kecil.
 */
// Deteksi file GIF — dipakai untuk skip kompresi & batas ukuran khusus.
function isGif(file: File): boolean {
    return (
        file.type === 'image/gif' || file.name.toLowerCase().endsWith('.gif')
    );
}

async function compressImage(file: File): Promise<File> {
    // GIF (animasi): lewati kompresi — canvas/toBlob hanya mengambil frame
    // pertama dan mengubahnya jadi JPEG statis (animasi hilang).
    if (isGif(file)) {
        return file;
    }

    try {
        const img = await loadImage(file);
        const scale = Math.min(
            1,
            MAX_IMAGE_DIMENSION / Math.max(img.naturalWidth, img.naturalHeight),
        );
        const canvas = document.createElement('canvas');
        canvas.width = Math.max(1, Math.round(img.naturalWidth * scale));
        canvas.height = Math.max(1, Math.round(img.naturalHeight * scale));
        const ctx = canvas.getContext('2d');

        if (!ctx) {
            return file;
        }

        ctx.fillStyle = '#ffffff';
        ctx.fillRect(0, 0, canvas.width, canvas.height);
        ctx.drawImage(img, 0, 0, canvas.width, canvas.height);

        const blob = await new Promise<Blob | null>((resolve) =>
            canvas.toBlob(resolve, 'image/jpeg', JPEG_QUALITY),
        );

        if (!blob || blob.size >= file.size) {
            return file;
        }

        return new File([blob], file.name.replace(/\.[^.]+$/, '.jpg'), {
            type: 'image/jpeg',
        });
    } catch {
        return file;
    }
}

async function onGalleryFiles(event: Event) {
    const input = event.target as HTMLInputElement;
    const files = Array.from(input.files ?? []);
    const remaining =
        MAX_GALLERY_IMAGES -
        existingImages.value.length -
        newPreviews.value.length;
    const quota = Math.max(remaining, 0);

    const oversized = files.filter((file) => file.size > MAX_UPLOAD_RAW_SIZE);

    if (oversized.length > 0) {
        toast.error('Ukuran gambar galeri maksimal 15 MB sebelum kompres.');
    }

    if (files.length > quota) {
        toast.error('Maksimal 5 gambar galeri.');
    }

    const accepted = files
        .filter((file) => file.size <= MAX_UPLOAD_RAW_SIZE)
        .slice(0, quota);

    for (const file of accepted) {
        const compressed = await compressImage(file);

        // GIF tidak dikompres — batasnya pakai ukuran raw (15MB).
        const limit = isGif(file) ? MAX_UPLOAD_RAW_SIZE : MAX_IMAGE_SIZE;

        if (compressed.size > limit) {
            toast.error(
                isGif(file)
                    ? 'Ukuran GIF galeri maksimal 15 MB.'
                    : 'Ukuran gambar galeri maksimal 2 MB per gambar.',
            );
            continue;
        }

        newPreviews.value.push({
            url: URL.createObjectURL(compressed),
            file: compressed,
        });
    }

    syncGalleryInput();
}

function syncCoverInput(file: File | null) {
    const input = document.getElementById('cover') as HTMLInputElement | null;

    if (!input) {
        return;
    }

    if (file === null) {
        input.value = '';

        return;
    }

    const transfer = new DataTransfer();
    transfer.items.add(file);
    input.files = transfer.files;
}

async function onCoverFile(event: Event) {
    const input = event.target as HTMLInputElement;
    const file = input.files?.[0] ?? null;

    if (coverPreview.value) {
        URL.revokeObjectURL(coverPreview.value);
    }

    if (file && file.size > MAX_UPLOAD_RAW_SIZE) {
        input.value = '';
        toast.error('Ukuran cover maksimal 15 MB sebelum kompres.');
        coverPreview.value = null;

        return;
    }

    if (!file) {
        coverPreview.value = null;

        return;
    }

    const compressed = await compressImage(file);

    // GIF tidak dikompres — batasnya pakai ukuran raw (15MB).
    const limit = isGif(file) ? MAX_UPLOAD_RAW_SIZE : MAX_IMAGE_SIZE;

    if (compressed.size > limit) {
        input.value = '';
        toast.error(
            isGif(file)
                ? 'Ukuran GIF cover maksimal 15 MB.'
                : 'Ukuran cover maksimal 2 MB.',
        );
        coverPreview.value = null;

        return;
    }

    syncCoverInput(compressed);
    removeCover.value = false;
    coverPreview.value = URL.createObjectURL(compressed);
}

function removeCoverImage() {
    if (coverPreview.value) {
        URL.revokeObjectURL(coverPreview.value);
        coverPreview.value = null;
    }

    const input = document.getElementById('cover') as HTMLInputElement | null;

    if (input) {
        input.value = '';
    }

    removeCover.value = true;
}

function removeNewImage(index: number) {
    URL.revokeObjectURL(newPreviews.value[index].url);
    newPreviews.value.splice(index, 1);
    syncGalleryInput();
}

function removeExistingImage(id: string) {
    const image = existingImages.value.find((img) => img.id === id);

    if (image) {
        existingImages.value = existingImages.value.filter(
            (img) => img.id !== id,
        );
        removedImages.value.push(id);
    }
}

function syncGalleryInput() {
    const input = document.getElementById('images') as HTMLInputElement | null;

    if (!input) {
        return;
    }

    const transfer = new DataTransfer();

    for (const preview of newPreviews.value) {
        transfer.items.add(preview.file);
    }

    input.files = transfer.files;
}

onBeforeUnmount(() => {
    for (const preview of newPreviews.value) {
        URL.revokeObjectURL(preview.url);
    }

    if (coverPreview.value) {
        URL.revokeObjectURL(coverPreview.value);
    }
});

const isEdit = Boolean(props.book);
const action = isEdit ? BookController.update : BookController.store;
const submitArgs = isEdit ? props.book?.id : undefined;

const editions = ref<BookEdition[]>(
    (props.editions ?? []).length > 0
        ? props.editions.map((e) => ({
              ...e,
              nama: e.nama ?? `Cetakan ke-${e.cetakan_ke}`,
          }))
        : [
              {
                  nama: 'Cetakan ke-1',
                  cetakan_ke: 1,
                  harga_beli: 0,
                  harga_jual: 0,
                  is_active: true,
              },
          ],
);

function addEdition() {
    const maxCetakan = Math.max(...editions.value.map((e) => e.cetakan_ke), 0);
    editions.value.push({
        nama: `Cetakan ke-${maxCetakan + 1}`,
        cetakan_ke: maxCetakan + 1,
        harga_beli: 0,
        harga_jual: 0,
        is_active: false,
    });
}

function removeEdition(index: number) {
    if (editions.value.length <= 1) {
        return;
    }

    const removed = editions.value[index];
    editions.value.splice(index, 1);

    if (removed.is_active) {
        editions.value[0].is_active = true;
    }
}

function setActive(index: number) {
    editions.value.forEach((e, i) => (e.is_active = i === index));
}

function onFormError() {
    toast.error('Gagal menyimpan — periksa kembali isian yang wajib diisi.');
}
</script>

<template>
    <Head :title="isEdit ? 'Edit Buku' : 'Buat Buku'" />

    <div class="flex flex-col gap-4 p-4 md:p-6">
        <div>
            <h1 class="text-xl font-semibold tracking-tight">
                {{ isEdit ? `Edit Buku: ${book?.judul}` : 'Buat Buku Baru' }}
            </h1>
            <p class="text-sm text-muted-foreground">
                SKU otomatis dibuat bila kolom dikosongkan
            </p>
        </div>

        <Form
            v-bind="action.form(submitArgs as string)"
            :method="isEdit ? 'put' : 'post'"
            class="flex flex-col gap-4"
            v-slot="{ errors, processing }"
            @error="onFormError"
        >
            <FormErrorAlert :errors="errors" />
            <!-- Hidden inputs editions -->
            <template
                v-for="(edition, i) in editions"
                :key="edition.cetakan_ke"
            >
                <input
                    type="hidden"
                    :name="`editions[${i}][cetakan_ke]`"
                    :value="edition.cetakan_ke"
                />
                <input
                    type="hidden"
                    :name="`editions[${i}][nama]`"
                    :value="edition.nama ?? ''"
                />
                <input
                    type="hidden"
                    :name="`editions[${i}][harga_beli]`"
                    :value="edition.harga_beli"
                />
                <input
                    type="hidden"
                    :name="`editions[${i}][harga_jual]`"
                    :value="edition.harga_jual"
                />
                <input
                    type="hidden"
                    :name="`editions[${i}][is_active]`"
                    :value="edition.is_active ? '1' : '0'"
                />
            </template>

            <Card>
                <CardHeader>
                    <CardTitle class="text-base font-medium"
                        >Informasi Utama</CardTitle
                    >
                </CardHeader>
                <CardContent class="grid gap-4 md:grid-cols-2">
                    <div class="grid gap-2">
                        <Label for="judul">Judul *</Label>
                        <Input
                            id="judul"
                            name="judul"
                            :default-value="book?.judul ?? undefined"
                            :aria-invalid="errors.judul ? true : undefined"
                            placeholder="Judul buku"
                            required
                        />
                    </div>
                    <div class="grid gap-2">
                        <Label for="category_id">Kategori *</Label>
                        <Select
                            name="category_id"
                            class="w-48"
                            :default-value="
                                book?.category_id
                                    ? String(book.category_id)
                                    : undefined
                            "
                        >
                            <SelectTrigger id="category_id" required>
                                <SelectValue placeholder="Pilih kategori" />
                            </SelectTrigger>
                            <SelectContent>
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
                    <div class="grid gap-2">
                        <Label for="penulis">Penulis</Label>
                        <Input
                            id="penulis"
                            name="penulis"
                            :default-value="book?.penulis ?? undefined"
                            :aria-invalid="errors.penulis ? true : undefined"
                            placeholder="Nama penulis (opsional)"
                        />
                    </div>
                    <div class="grid gap-2">
                        <Label
                            for="penterjemah"
                            class="inline-flex w-fit items-center gap-1"
                        >
                            Penterjemah
                            <FieldHint text="Diisi jika buku terjemahan." />
                        </Label>
                        <Input
                            id="penterjemah"
                            name="penterjemah"
                            :default-value="book?.penterjemah ?? undefined"
                            placeholder="Nama penterjemah"
                        />
                    </div>
                    <div class="grid gap-2">
                        <Label for="penerbit">Penerbit</Label>
                        <Input
                            id="penerbit"
                            name="penerbit"
                            :default-value="book?.penerbit ?? undefined"
                            placeholder="Nama penerbit"
                        />
                    </div>
                    <div class="grid gap-2">
                        <Label for="isbn">ISBN</Label>
                        <Input
                            id="isbn"
                            name="isbn"
                            :default-value="book?.isbn ?? undefined"
                            placeholder="978-xxx"
                        />
                    </div>
                    <div class="grid gap-2">
                        <Label for="tahun">Tahun Terbit</Label>
                        <Input
                            id="tahun"
                            name="tahun"
                            type="number"
                            :default-value="book?.tahun ?? undefined"
                            placeholder="2024"
                        />
                    </div>
                    <div class="grid gap-2">
                        <Label for="aktif">Status</Label>
                        <Select v-model="aktifValue">
                            <SelectTrigger id="aktif">
                                <SelectValue placeholder="Pilih status" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="1">Aktif</SelectItem>
                                <SelectItem value="0">Nonaktif</SelectItem>
                            </SelectContent>
                        </Select>
                        <input type="hidden" name="aktif" :value="aktifValue" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="is_preorder">Buku Pre-Order</Label>
                        <Select v-model="isPreorderValue">
                            <SelectTrigger id="is_preorder">
                                <SelectValue placeholder="Pilih" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="1">
                                    Ya — buku akan datang (new coming)
                                </SelectItem>
                                <SelectItem value="0">Tidak</SelectItem>
                            </SelectContent>
                        </Select>
                        <input
                            type="hidden"
                            name="is_preorder"
                            :value="isPreorderValue"
                        />
                    </div>

                    <div v-if="isPreorderValue === '1'" class="grid gap-2">
                        <Label
                            for="penterjemah"
                            class="inline-flex w-fit items-center gap-1"
                        >
                            Estimasi Tersedia
                            <FieldHint
                                text="Customer akan melihat perkiraan tanggal buku tersedia."
                            />
                        </Label>
                        <Input
                            id="preorder_eta"
                            name="preorder_eta"
                            type="date"
                            :default-value="book?.preorder_eta ?? undefined"
                        />
                    </div>

                    <div class="grid gap-2 md:col-span-2">
                        <Label for="sinopsis">Sinopsis</Label>
                        <Textarea
                            id="sinopsis"
                            name="sinopsis"
                            :default-value="book?.sinopsis ?? undefined"
                            rows="4"
                            placeholder="Deskripsi buku..."
                        />
                    </div>
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle class="text-base font-medium"
                        >Spesifikasi Buku</CardTitle
                    >
                </CardHeader>
                <CardContent class="grid gap-4 md:grid-cols-4">
                    <div class="grid gap-2">
                        <Label for="rating_umur">Rating Umur</Label>
                        <Input
                            id="rating_umur"
                            name="rating_umur"
                            :default-value="book?.rating_umur ?? undefined"
                            placeholder="13+"
                        />
                    </div>
                    <div class="grid gap-2">
                        <Label for="dimensi">Dimensi</Label>
                        <Input
                            id="dimensi"
                            name="dimensi"
                            :default-value="book?.dimensi ?? undefined"
                            placeholder="20 x 13 cm"
                        />
                    </div>
                    <div class="grid gap-2">
                        <Label for="kemasan">Kemasan</Label>
                        <Input
                            id="kemasan"
                            name="kemasan"
                            :default-value="book?.kemasan ?? undefined"
                            placeholder="Soft cover"
                        />
                    </div>
                    <div class="grid gap-2">
                        <Label for="berat_gr">Berat (gram)</Label>
                        <Input
                            id="berat_gr"
                            name="berat_gr"
                            type="number"
                            min="0"
                            :default-value="book?.berat_gr ?? undefined"
                        />
                    </div>
                    <div class="grid gap-2">
                        <Label for="jumlah_halaman">Jumlah Halaman</Label>
                        <Input
                            id="jumlah_halaman"
                            name="jumlah_halaman"
                            type="number"
                            min="0"
                            :default-value="book?.jumlah_halaman ?? undefined"
                        />
                    </div>
                    <div class="grid gap-2">
                        <Label for="jenis_kertas">Jenis Kertas</Label>
                        <Input
                            id="jenis_kertas"
                            name="jenis_kertas"
                            :default-value="book?.jenis_kertas ?? undefined"
                            placeholder="Bookpaper"
                        />
                    </div>
                    <div class="grid gap-2">
                        <Label for="cetakan">Cetakan</Label>
                        <Input
                            id="cetakan"
                            name="cetakan"
                            :default-value="book?.cetakan ?? undefined"
                            placeholder="Ke-1, 2024"
                        />
                    </div>
                    <div class="grid gap-2">
                        <Label for="bahasa">Bahasa</Label>
                        <Input
                            id="bahasa"
                            name="bahasa"
                            :default-value="book?.bahasa ?? undefined"
                            placeholder="Indonesia"
                        />
                    </div>
                    <div class="grid gap-2">
                        <Label for="jenis_cover">Jenis Cover</Label>
                        <Input
                            id="jenis_cover"
                            name="jenis_cover"
                            :default-value="book?.jenis_cover ?? undefined"
                            placeholder="Soft cover"
                        />
                    </div>
                </CardContent>
            </Card>

            <!-- EDITIONS CARD -->
            <Card>
                <CardHeader class="flex flex-row items-center justify-between">
                    <CardTitle class="text-base font-medium"
                        >Daftar Cetakan</CardTitle
                    >
                    <Button type="button" size="sm" @click="addEdition">
                        <Plus class="size-4" />
                        Tambah Cetakan
                    </Button>
                </CardHeader>
                <CardContent>
                    <p class="mb-3 text-xs text-muted-foreground">
                        Atur nama, harga beli & jual tiap cetakan. Cetakan
                        <strong class="text-foreground">aktif</strong> akan
                        tampil sebagai harga default di katalog.
                    </p>

                    <div class="overflow-x-auto">
                        <table
                            class="w-full border-separate border-spacing-0 text-sm"
                        >
                            <thead>
                                <tr>
                                    <th
                                        class="border-b px-2 pb-2 text-left font-medium whitespace-nowrap text-muted-foreground"
                                    >
                                        Nama Cetakan
                                    </th>
                                    <th
                                        class="border-b px-2 pb-2 text-left font-medium whitespace-nowrap text-muted-foreground"
                                    >
                                        Harga Beli
                                    </th>
                                    <th
                                        class="border-b px-2 pb-2 text-left font-medium whitespace-nowrap text-muted-foreground"
                                    >
                                        Harga Jual
                                    </th>
                                    <th
                                        class="border-b px-2 pb-2 text-center font-medium whitespace-nowrap text-muted-foreground"
                                    >
                                        Default
                                    </th>
                                    <th class="w-10 border-b px-2 pb-2"></th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr
                                    v-for="(edition, i) in editions"
                                    :key="edition.cetakan_ke"
                                    class="border-b border-border/60 last:border-0"
                                >
                                    <td class="px-2 py-2">
                                        <Input
                                            v-model="edition.nama"
                                            :placeholder="`Cetakan ke-${edition.cetakan_ke}`"
                                            aria-label="Nama cetakan"
                                            class="h-8 w-44"
                                        />
                                    </td>
                                    <td class="px-2 py-2">
                                        <CurrencyInput
                                            v-model="edition.harga_beli"
                                            input-class="h-8 w-28"
                                        />
                                    </td>
                                    <td class="px-2 py-2">
                                        <CurrencyInput
                                            v-model="edition.harga_jual"
                                            input-class="h-8 w-28"
                                        />
                                    </td>
                                    <td class="px-2 py-2 text-center">
                                        <Checkbox
                                            :model-value="edition.is_active"
                                            @update:model-value="
                                                (
                                                    val:
                                                        | boolean
                                                        | 'indeterminate',
                                                ) => {
                                                    if (val === true)
                                                        setActive(i);
                                                }
                                            "
                                        />
                                    </td>
                                    <td
                                        v-if="
                                            editions.length > 1 &&
                                            i === editions.length - 1
                                        "
                                        class="px-2 py-2"
                                    >
                                        <Button
                                            type="button"
                                            variant="ghost"
                                            size="icon"
                                            class="size-8 text-muted-foreground hover:text-destructive"
                                            @click="removeEdition(i)"
                                        >
                                            <Trash2 class="size-4" />
                                        </Button>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle class="text-base font-medium">Gambar</CardTitle>
                </CardHeader>
                <CardContent class="grid gap-4 md:grid-cols-2">
                    <div class="grid gap-2 md:col-span-2">
                        <Label for="cover"
                            >Upload Gambar Utama
                            <p class="text-xs text-muted-foreground">
                                Otomatis dikompres, maks 2 MB
                            </p>
                        </Label>
                        <Input
                            id="cover"
                            name="cover"
                            type="file"
                            accept="image/*"
                            @change="onCoverFile"
                        />
                    </div>
                    <div
                        v-if="coverPreview || (book?.cover_url && !removeCover)"
                        class="md:col-span-2"
                    >
                        <Label class="mb-2 block">Preview Cover</Label>
                        <div class="group relative inline-block">
                            <img
                                :src="coverPreviewSrc"
                                :alt="book?.judul ?? 'Cover buku'"
                                class="h-40 w-auto rounded-md border object-cover"
                            />
                            <Button
                                type="button"
                                variant="destructive"
                                size="icon"
                                class="absolute top-1.5 right-1.5 size-6 opacity-100 transition-opacity group-hover:opacity-100 md:opacity-0 md:group-hover:opacity-100"
                                aria-label="Hapus cover"
                                @click="removeCoverImage"
                            >
                                <X class="size-3.5" />
                            </Button>
                        </div>
                    </div>

                    <input
                        type="hidden"
                        name="remove_cover"
                        :value="removeCover ? '1' : '0'"
                    />

                    <div class="grid gap-2 md:col-span-2">
                        <Label for="images"
                            >Gambar Galeri
                            <p class="text-xs text-muted-foreground">
                                Maks 5 gambar, otomatis dikompres
                            </p>
                        </Label>
                        <Input
                            id="images"
                            name="images[]"
                            type="file"
                            accept="image/*"
                            multiple
                            @change="onGalleryFiles"
                        />
                    </div>

                    <div
                        v-if="
                            existingImages.length > 0 || newPreviews.length > 0
                        "
                        class="md:col-span-2"
                    >
                        <Label class="mb-2 block">Preview Galeri</Label>
                        <div
                            class="grid grid-cols-2 gap-3 sm:grid-cols-3 md:grid-cols-5"
                        >
                            <div
                                v-for="image in existingImages"
                                :key="image.id"
                                class="group relative"
                            >
                                <img
                                    :src="image.image_url"
                                    :alt="'Gambar galeri'"
                                    class="h-28 w-full rounded-md border object-cover"
                                />
                                <Button
                                    type="button"
                                    variant="destructive"
                                    size="icon"
                                    class="absolute top-1.5 right-1.5 size-6 opacity-100 transition-opacity group-hover:opacity-100 md:opacity-0 md:group-hover:opacity-100"
                                    @click="removeExistingImage(image.id)"
                                >
                                    <X class="size-3.5" />
                                </Button>
                            </div>
                            <div
                                v-for="(preview, i) in newPreviews"
                                :key="preview.url"
                                class="group relative"
                            >
                                <img
                                    :src="preview.url"
                                    alt="Preview gambar baru"
                                    class="h-28 w-full rounded-md border object-cover"
                                />
                                <Button
                                    type="button"
                                    variant="destructive"
                                    size="icon"
                                    class="absolute top-1.5 right-1.5 size-6 opacity-100 transition-opacity group-hover:opacity-100 md:opacity-0 md:group-hover:opacity-100"
                                    @click="removeNewImage(i)"
                                >
                                    <X class="size-3.5" />
                                </Button>
                            </div>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <template v-for="id in removedImages" :key="id">
                <input type="hidden" name="removed_images[]" :value="id" />
            </template>

            <!-- Action bar — sticky di bawah agar selalu terlihat -->
            <div
                class="sticky bottom-0 z-10 -mx-4 flex flex-wrap items-center gap-2 border-t bg-background/95 px-4 py-3 backdrop-blur supports-[backdrop-filter]:bg-background/80 md:-mx-6 md:px-6"
            >
                <Button type="submit" :disabled="processing">
                    {{
                        processing
                            ? 'Menyimpan...'
                            : isEdit
                              ? 'Simpan Perubahan'
                              : 'Buat Buku'
                    }}
                </Button>
                <Button variant="outline" type="button" as-child>
                    <Link :href="indexRoute().url">Batal</Link>
                </Button>
            </div>
        </Form>
    </div>
</template>

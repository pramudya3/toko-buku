<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import { ref } from 'vue';
import { toast } from 'vue-sonner';
import BookController from '@/actions/App/Http/Controllers/Admin/BookController';
import CurrencyInput from '@/components/CurrencyInput.vue';
import InputError from '@/components/InputError.vue';
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

type Category = { id: number; nama: string };

type Book = {
    id: number;
    kode_sku: string | null;
    judul: string;
    penulis: string | null;
    penerbit: string | null;
    tahun: number | null;
    isbn: string | null;
    sinopsis: string | null;
    harga: number;
    stok: number;
    category_id: number | null;
    cover_url: string | null;
    aktif: boolean;
    is_preorder: boolean;
    po_label: string | null;
    rating_umur: string | null;
    dimensi: string | null;
    kemasan: string | null;
    berat_gr: number | null;
    jumlah_halaman: number | null;
    jenis_kertas: string | null;
    cetakan: string | null;
};

const props = defineProps<{
    book: Book | null;
    categories: Category[];
}>();

const isEdit = Boolean(props.book);
const action = isEdit ? BookController.update : BookController.store;
const submitArgs = isEdit ? props.book?.id : undefined;

const isPreorder = ref(props.book?.is_preorder ?? false);

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
            v-bind="action.form(submitArgs as number)"
            :method="isEdit ? 'put' : 'post'"
            class="flex flex-col gap-4"
            v-slot="{ errors, processing }"
            @error="onFormError"
        >
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
                        <InputError :message="errors.judul" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="category_id">Kategori</Label>
                        <Select
                            name="category_id"
                            class="w-48"
                            :default-value="
                                book?.category_id
                                    ? String(book.category_id)
                                    : undefined
                            "
                        >
                            <SelectTrigger id="category_id">
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
                        <Label for="penulis">Penulis *</Label>
                        <Input
                            id="penulis"
                            name="penulis"
                            :default-value="book?.penulis ?? undefined"
                            :aria-invalid="errors.penulis ? true : undefined"
                            placeholder="Nama penulis"
                            required
                        />
                        <InputError :message="errors.penulis" />
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
                        <Label for="harga">Harga *</Label>
                        <CurrencyInput
                            id="harga"
                            name="harga"
                            min="0"
                            :default-value="book?.harga ?? undefined"
                            :invalid="Boolean(errors.harga)"
                            placeholder="85.000"
                            required
                        />
                        <InputError :message="errors.harga" />
                    </div>
                    <div class="grid gap-2">
                        <template v-if="!isEdit">
                            <Label for="stok">Stok Awal (gudang Malang)</Label>
                            <Input
                                id="stok"
                                name="stok"
                                type="number"
                                min="0"
                                default-value="0"
                            />
                        </template>
                        <template v-else>
                            <Label>Stok Normal Saat Ini</Label>
                            <p
                                class="flex h-9 items-center rounded-md border bg-muted/50 px-3 text-sm tabular-nums"
                            >
                                {{ book?.stok ?? 0 }} unit
                            </p>
                        </template>
                    </div>
                    <div class="grid gap-2">
                        <Label for="aktif">Status</Label>
                        <Select
                            name="aktif"
                            :default-value="book ? (book.aktif ? '1' : '0') : '1'"
                        >
                            <SelectTrigger id="aktif">
                                <SelectValue placeholder="Pilih status" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="1">Aktif</SelectItem>
                                <SelectItem value="0">Nonaktif</SelectItem>
                            </SelectContent>
                        </Select>
                    </div>
                    <Label class="flex items-end gap-2 pb-2">
                        <input
                            type="hidden"
                            name="is_preorder"
                            :value="isPreorder ? '1' : '0'"
                        />
                        <Checkbox v-model="isPreorder" />
                        Preorder
                    </Label>
                    <div v-if="isPreorder" class="grid gap-2 md:col-span-2">
                        <Label for="po_label">Label Preorder</Label>
                        <Input
                            id="po_label"
                            name="po_label"
                            :default-value="book?.po_label ?? undefined"
                            placeholder="PO - Okt 2026"
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
                    <CardTitle class="text-base font-medium">Cover</CardTitle>
                </CardHeader>
                <CardContent class="grid gap-4 md:grid-cols-2">
                    <div class="grid gap-2">
                        <Label for="cover">Upload Gambar<p class="text-xs text-muted-foreground">
                            Maks 2 MB
                        </p></Label>
                        <Input
                            id="cover"
                            name="cover"
                            type="file"
                            accept="image/*"
                        />
                        <InputError :message="errors.cover" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="cover_url">Atau URL Eksternal</Label>
                        <Input
                            id="cover_url"
                            name="cover_url"
                            :default-value="book?.cover_url ?? undefined"
                            placeholder="https://..."
                        />
                        <InputError :message="errors.cover_url" />
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
                </CardContent>
            </Card>

            <div class="flex items-center gap-3">
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

<script setup lang="ts">
defineOptions({
    layout: (pageProps: any) => ({
        breadcrumbs: [
            { title: 'Beranda', href: '/admin/dashboard' },
            { title: 'Artikel', href: '/admin/articles' },
            { title: pageProps.article ? 'Edit' : 'Tambah' },
        ],
    }),
});

import { Form, Head, Link } from '@inertiajs/vue3';
import { ArrowLeft } from '@lucide/vue';
import { ref } from 'vue';
import ArticleController from '@/actions/App/Http/Controllers/Admin/ArticleController';
import ArticleEditor from '@/components/ArticleEditor.vue';
import FieldHint from '@/components/FieldHint.vue';
import FormErrorAlert from '@/components/FormErrorAlert.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Switch } from '@/components/ui/switch';
import { Textarea } from '@/components/ui/textarea';
import { index as indexRoute } from '@/routes/admin/articles';

type Article = {
    id: string;
    judul: string;
    article_category_id: string | null;
    penulis: string | null;
    ringkasan: string;
    isi: string;
    motif: string | null;
    cover_url: string | null;
    is_active: boolean;
    is_featured: boolean;
    published_at: string | null;
};

type KategoriOption = { id: string; nama: string };

const props = defineProps<{
    article: Article | null;
    kategoriOptions: KategoriOption[];
}>();

const isEdit = Boolean(props.article);
const submitArgs = isEdit ? props.article!.id : undefined;
const action = isEdit ? ArticleController.update : ArticleController.store;

const kategoriValue = ref(props.article?.article_category_id ?? '');
const isActive = ref(props.article?.is_active ?? true);
const isFeatured = ref(props.article?.is_featured ?? false);
const articleIsi = ref(props.article?.isi ?? '');
const removeCover = ref(false);

// Tanggal terbit — default hari ini untuk artikel baru (langsung terbit).
// Dikosongkan hanya saat menyimpan sebagai draft.
const today = new Date().toISOString().slice(0, 10);
const isDraftArticle = props.article
    ? !props.article.is_active
    : false;
const publishedAt = ref(
    isDraftArticle ? '' : (props.article?.published_at ?? today),
);
const isDraft = ref(isDraftArticle);

// "Simpan sebagai Draft" — nonaktifkan agar tidak tampil di beranda,
// tanggal terbit dikosongkan; hanya judul & kategori yang wajib diisi.
// Artikel tetap bisa diedit kapan saja.
function saveAsDraft(): void {
    isActive.value = false;
    // publishedAt.value = '';
    isDraft.value = true;
}

// "Terbitkan Artikel" — aktifkan kembali + tanggal hari ini (dari status draft).
// Tanggal terbit yang sudah ada tidak ditimpa.
function publishArticle(): void {
    isActive.value = true;
    if (!publishedAt.value) {
        publishedAt.value = today;
    }
    isDraft.value = false;
}
</script>

<template>

    <Head :title="isEdit ? 'Edit Artikel' : 'Buat Artikel'" />

    <div class="flex flex-col gap-4 p-4 md:p-6">
        <div class="flex items-start justify-between gap-4">
            <div>
                <h1 class="text-xl font-semibold tracking-tight">
                    {{ isEdit ? 'Edit Artikel' : 'Buat Artikel Baru' }}
                </h1>
                <p class="text-sm text-muted-foreground">
                    Artikel aktif &amp; sudah terbit otomatis tampil di menu
                    Artikel storefront.
                </p>
            </div>
        </div>

        <Form v-bind="action.form(submitArgs as string)" :method="isEdit ? 'put' : 'post'" class="flex flex-col gap-4"
            v-slot="{ errors, processing }">
            <FormErrorAlert :errors="errors" />

            <Card>
                <CardHeader>
                    <CardTitle class="text-base font-medium">Informasi</CardTitle>
                </CardHeader>
                <CardContent class="grid gap-4 md:grid-cols-2">
                    <div class="grid gap-2 md:col-span-2">
                        <Label for="judul">Judul</Label>
                        <Input id="judul" name="judul" :default-value="article?.judul ?? undefined"
                            :aria-invalid="errors.judul ? true : undefined" placeholder="Judul artikel" required />
                        <span v-if="errors.judul" class="text-sm text-destructive">{{ errors.judul }}</span>
                    </div>
                    <div class="grid gap-2">
                        <Label for="kategori">Kategori</Label>
                        <Select v-model="kategoriValue">
                            <SelectTrigger id="kategori">
                                <SelectValue placeholder="Pilih kategori" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem v-for="kategori in props.kategoriOptions" :key="kategori.id"
                                    :value="kategori.id">
                                    {{ kategori.nama }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                        <input type="hidden" name="article_category_id" :value="kategoriValue" />
                        <span v-if="errors.article_category_id" class="text-sm text-destructive">{{
                            errors.article_category_id }}</span>
                    </div>
                    <div class="grid gap-2">
                        <Label for="penulis">Penulis</Label>
                        <Input id="penulis" name="penulis" :default-value="article?.penulis ?? undefined"
                            placeholder="Tim Penerbit" />
                        <span v-if="errors.penulis" class="text-sm text-destructive">{{ errors.penulis }}</span>
                    </div>
                    <div class="grid gap-2">
                        <Label for="published_at">Tanggal Terbit</Label>
                        <Input id="published_at" name="published_at" type="date" v-model="publishedAt" />
                        <p class="text-xs text-muted-foreground">
                            <template v-if="isDraft">
                                Disimpan sebagai draft — tidak tampil di
                                beranda.
                            </template>
                            <template v-else>
                                Artikel dengan tanggal ini langsung tampil di
                                storefront.
                            </template>
                        </p>
                        <span v-if="errors.published_at" class="text-sm text-destructive">{{ errors.published_at
                        }}</span>
                    </div>
                    <div class="grid items-center gap-4 md:col-span-2 md:grid-cols-2">
                        <Label class="flex h-9 items-center gap-2 text-sm">
                            <input type="hidden" name="is_active" :value="isActive ? '1' : '0'" />
                            <Switch v-model="isActive" />
                            Aktif di storefront
                        </Label>
                        <Label class="flex h-9 items-center gap-2 text-sm">
                            <input type="hidden" name="is_featured" :value="isFeatured ? '1' : '0'" />
                            <Switch v-model="isFeatured" />
                            Jadikan artikel unggulan
                        </Label>
                    </div>

                    <!-- Ringkasan -->
                    <div class="grid gap-2 md:col-span-2">
                        <Label for="ringkasan">
                            Ringkasan
                            <FieldHint
                                text="Teks pendek yang tampil di kartu artikel & meta description."
                            />
                        </Label>
                        <Textarea id="ringkasan" name="ringkasan"
                            :default-value="article?.ringkasan ?? undefined" rows="3" maxlength="500"
                            placeholder="Ringkasan singkat artikel…" :required="!isDraft" />
                        <span v-if="errors.ringkasan" class="text-sm text-destructive">{{ errors.ringkasan }}</span>
                    </div>

                    <!-- Cover -->
                    <div class="grid gap-2 md:col-span-2">
                        <Label for="cover">
                            Cover
                            <FieldHint
                                text="Opsional — jika tidak diunggah, ilustrasi kutipan (SVG) dipakai otomatis."
                            />
                        </Label>
                        <div class="flex items-center gap-3">
                            <img v-if="article?.cover_url" :src="article.cover_url"
                                :alt="`Cover artikel ${article.judul}`"
                                class="h-16 w-28 shrink-0 rounded-md object-cover ring-1 ring-border" />
                            <div class="min-w-0 flex-1">
                                <Input id="cover" name="cover" type="file" accept="image/*" class="h-9" />
                                <label v-if="article?.cover_url"
                                    class="mt-1.5 flex w-fit cursor-pointer items-center gap-1.5 text-xs text-muted-foreground">
                                    <input type="checkbox" name="remove_cover" :value="removeCover ? '1' : ''"
                                        v-model="removeCover" class="size-3.5 rounded border-border" />
                                    Hapus cover
                                </label>
                            </div>
                        </div>
                        <span v-if="errors.cover" class="text-sm text-destructive">{{ errors.cover }}</span>
                    </div>
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle class="text-base font-medium">Isi Artikel</CardTitle>
                </CardHeader>
                <CardContent class="grid gap-2">
                    <ArticleEditor v-model="articleIsi" />
                    <input type="hidden" name="isi" :value="articleIsi" />
                    <span v-if="errors.isi" class="text-sm text-destructive">{{
                        errors.isi
                    }}</span>
                </CardContent>
            </Card>

            <!-- Action bar — sticky di bawah agar selalu terlihat -->
            <input
                type="hidden"
                name="save_as_draft"
                :value="isDraft ? '1' : '0'"
            />
            <div
                class="sticky bottom-0 z-10 -mx-4 flex flex-wrap items-center gap-2 border-t bg-background/95 px-4 py-3 backdrop-blur supports-[backdrop-filter]:bg-background/80 md:-mx-6 md:px-6"
            >
                <Button type="submit" :disabled="processing" @click="publishArticle">
                    {{
                        isDraft
                            ? 'Terbitkan Artikel'
                            : isEdit
                              ? 'Simpan Perubahan'
                              : 'Buat Artikel'
                    }}
                </Button>
                <Button
                    variant="secondary"
                    type="submit"
                    :disabled="processing"
                    @click="saveAsDraft"
                >
                    Simpan sebagai Draft
                </Button>
                <Button variant="outline" type="button" as-child>
                    <Link :href="indexRoute().url">Batal</Link>
                </Button>
            </div>
        </Form>
    </div>
</template>

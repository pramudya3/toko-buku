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
    published_at: string | null;
};

type KategoriOption = { id: string; nama: string };

const props = defineProps<{
    article: Article | null;
    kategoriOptions: KategoriOption[];
    motifOptions: Record<string, string>;
}>();

const isEdit = Boolean(props.article);
const submitArgs = isEdit ? props.article!.id : undefined;
const action = isEdit ? ArticleController.update : ArticleController.store;

const kategoriValue = ref(props.article?.article_category_id ?? '');
const motifValue = ref(props.article?.motif ?? '');
const noMotifSentinel = 'none';
const isActive = ref(props.article?.is_active ?? true);
const articleIsi = ref(props.article?.isi ?? '');
const removeCover = ref(false);
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
            <Button variant="outline" as-child>
                <Link :href="indexRoute().url">
                    <ArrowLeft class="size-4" />
                    Kembali
                </Link>
            </Button>
        </div>

        <Form
            v-bind="action.form(submitArgs as string)"
            :method="isEdit ? 'put' : 'post'"
            class="flex flex-col gap-4"
            v-slot="{ errors, processing }"
        >
            <FormErrorAlert :errors="errors" />

            <Card>
                <CardHeader>
                    <CardTitle class="text-base font-medium"
                        >Informasi</CardTitle
                    >
                </CardHeader>
                <CardContent class="grid gap-4 md:grid-cols-2">
                    <div class="grid gap-2 md:col-span-2">
                        <Label for="judul">Judul</Label>
                        <Input
                            id="judul"
                            name="judul"
                            :default-value="article?.judul ?? undefined"
                            :aria-invalid="errors.judul ? true : undefined"
                            placeholder="Judul artikel"
                            required
                        />
                        <span
                            v-if="errors.judul"
                            class="text-sm text-destructive"
                            >{{ errors.judul }}</span
                        >
                    </div>
                    <div class="grid gap-2">
                        <Label for="kategori">Kategori</Label>
                        <Select v-model="kategoriValue">
                            <SelectTrigger id="kategori">
                                <SelectValue placeholder="Pilih kategori" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem
                                    v-for="kategori in props.kategoriOptions"
                                    :key="kategori.id"
                                    :value="kategori.id"
                                >
                                    {{ kategori.nama }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                        <input
                            type="hidden"
                            name="article_category_id"
                            :value="kategoriValue"
                        />
                        <span
                            v-if="errors.article_category_id"
                            class="text-sm text-destructive"
                            >{{ errors.article_category_id }}</span
                        >
                    </div>
                    <div class="grid gap-2">
                        <Label for="penulis">Penulis</Label>
                        <Input
                            id="penulis"
                            name="penulis"
                            :default-value="article?.penulis ?? undefined"
                            placeholder="Tim Penerbit"
                        />
                        <span
                            v-if="errors.penulis"
                            class="text-sm text-destructive"
                            >{{ errors.penulis }}</span
                        >
                    </div>
                    <div class="grid gap-2">
                        <Label for="published_at">Tanggal Terbit</Label>
                        <Input
                            id="published_at"
                            name="published_at"
                            type="date"
                            :default-value="article?.published_at ?? undefined"
                        />
                        <p class="text-xs text-muted-foreground">
                            Kosongkan untuk draft (tidak tampil di storefront).
                        </p>
                        <span
                            v-if="errors.published_at"
                            class="text-sm text-destructive"
                            >{{ errors.published_at }}</span
                        >
                    </div>
                    <div class="grid items-end gap-2 pb-6 md:pb-0">
                        <Label class="flex h-9 items-center gap-2 text-sm">
                            <input
                                type="hidden"
                                name="is_active"
                                :value="isActive ? '1' : '0'"
                            />
                            <Switch v-model="isActive" />
                            Aktif di storefront
                        </Label>
                    </div>
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle class="text-base font-medium"
                        >Ringkasan</CardTitle
                    >
                </CardHeader>
                <CardContent class="grid gap-2">
                    <Label for="ringkasan">
                        Ringkasan
                        <FieldHint
                            text="Teks pendek yang tampil di kartu artikel & meta description."
                        />
                    </Label>
                    <Textarea
                        id="ringkasan"
                        name="ringkasan"
                        :default-value="article?.ringkasan ?? undefined"
                        rows="3"
                        maxlength="500"
                        placeholder="Ringkasan singkat artikel…"
                        required
                    />
                    <span
                        v-if="errors.ringkasan"
                        class="text-sm text-destructive"
                        >{{ errors.ringkasan }}</span
                    >
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle class="text-base font-medium"
                        >Isi Artikel</CardTitle
                    >
                </CardHeader>
                <CardContent class="grid gap-2">
                    <Label>
                        Isi Artikel
                        <FieldHint
                            text="Gunakan toolbar untuk format: tebal, miring, judul, kutipan, daftar, dan tautan."
                        />
                    </Label>
                    <ArticleEditor v-model="articleIsi" />
                    <input type="hidden" name="isi" :value="articleIsi" />
                    <span v-if="errors.isi" class="text-sm text-destructive">{{
                        errors.isi
                    }}</span>
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle class="text-base font-medium"
                        >Ilustrasi</CardTitle
                    >
                </CardHeader>
                <CardContent class="grid gap-4 md:grid-cols-2">
                    <div class="grid gap-2">
                        <Label for="motif">Ilustrasi (SVG)</Label>
                        <Select v-model="motifValue">
                            <SelectTrigger id="motif">
                                <SelectValue placeholder="Tanpa ilustrasi" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem :value="noMotifSentinel"
                                    >Tanpa ilustrasi</SelectItem
                                >
                                <SelectItem
                                    v-for="(label, value) in props.motifOptions"
                                    :key="value"
                                    :value="value"
                                >
                                    {{ label }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                        <input
                            type="hidden"
                            name="motif"
                            :value="
                                motifValue === noMotifSentinel ? '' : motifValue
                            "
                        />
                        <p class="text-xs text-muted-foreground">
                            Dipakai saat belum ada cover — SVG ringan tanpa
                            permintaan gambar.
                        </p>
                        <span
                            v-if="errors.motif"
                            class="text-sm text-destructive"
                            >{{ errors.motif }}</span
                        >
                    </div>
                    <div class="grid gap-2">
                        <Label for="cover">Cover (opsional)</Label>
                        <div class="flex items-center gap-3">
                            <img
                                v-if="article?.cover_url"
                                :src="article.cover_url"
                                :alt="`Cover artikel ${article.judul}`"
                                class="h-16 w-28 shrink-0 rounded-md object-cover ring-1 ring-border"
                            />
                            <div class="min-w-0 flex-1">
                                <Input
                                    id="cover"
                                    name="cover"
                                    type="file"
                                    accept="image/*"
                                    class="h-9"
                                />
                                <label
                                    v-if="article?.cover_url"
                                    class="mt-1.5 flex w-fit cursor-pointer items-center gap-1.5 text-xs text-muted-foreground"
                                >
                                    <input
                                        type="checkbox"
                                        name="remove_cover"
                                        :value="removeCover ? '1' : ''"
                                        v-model="removeCover"
                                        class="size-3.5 rounded border-border"
                                    />
                                    Hapus cover
                                </label>
                            </div>
                        </div>
                        <span
                            v-if="errors.cover"
                            class="text-sm text-destructive"
                            >{{ errors.cover }}</span
                        >
                    </div>
                </CardContent>
            </Card>

            <div class="flex items-center justify-end gap-2">
                <Button variant="outline" type="button" as-child>
                    <Link :href="indexRoute().url">Batal</Link>
                </Button>
                <Button type="submit" :disabled="processing">
                    {{ isEdit ? 'Simpan Perubahan' : 'Buat Artikel' }}
                </Button>
            </div>
        </Form>
    </div>
</template>

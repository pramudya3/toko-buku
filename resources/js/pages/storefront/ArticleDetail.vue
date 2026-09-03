<script setup lang="ts">
/**
 * Detail Artikel — halaman baca editorial.
 */
import { Head } from '@inertiajs/vue3';
import { Clock, User } from '@lucide/vue';
import ArticleBody from '@/components/ArticleBody.vue';
import ArticleThumb from '@/components/storefront/ArticleThumb.vue';
import EditorialLayout from '@/layouts/customer/EditorialLayout.vue';
import { formatDateID } from '@/lib/date';

defineOptions({ layout: EditorialLayout });

type Article = {
    judul: string;
    kategori_label: string;
    penulis: string | null;
    ringkasan: string;
    isi: string;
    published_at: string | null;
    cover_url: string | null;
    motif:
        | 'stack'
        | 'manuscript'
        | 'readers'
        | 'quote'
        | 'shelf'
        | 'pencil'
        | 'lamp'
        | null;
    menit: number;
};

const props = defineProps<{ article: Article }>();

const penulis = props.article.penulis ?? 'Tim Penerbit';
const tanggal = props.article.published_at
    ? formatDateID(props.article.published_at)
    : '';
</script>

<template>
    <Head :title="article.judul">
        <meta name="description" :content="article.ringkasan" />
    </Head>

    <article class="mx-auto max-w-3xl px-4 py-10 md:px-6 md:py-14">
        <header class="mt-4 text-center">
            <p
                class="text-xs font-semibold tracking-[0.2em] text-article-primary uppercase"
            >
                {{ article.kategori_label }}
            </p>
            <h1
                class="mx-auto mt-4 max-w-2xl font-serif text-3xl leading-tight font-bold tracking-tight text-article-ink md:text-4xl"
            >
                {{ article.judul }}
            </h1>
            <p
                class="mx-auto mt-5 flex flex-wrap items-center justify-center gap-x-2 gap-y-1 text-sm text-article-muted"
            >
                <span
                    class="inline-flex items-center gap-1 font-medium text-article-ink"
                >
                    <User class="size-4" aria-hidden="true" />
                    {{ penulis }}
                </span>
                <span aria-hidden="true">·</span>
                <span>{{ tanggal }}</span>
                <span aria-hidden="true">·</span>
                <span class="inline-flex items-center gap-1">
                    <Clock class="size-4" aria-hidden="true" />
                    {{ article.menit }} menit baca
                </span>
            </p>
        </header>

        <figure class="mt-8">
            <img
                v-if="article.cover_url"
                :src="article.cover_url"
                :alt="`Ilustrasi artikel ${article.judul}`"
                class="aspect-video w-full rounded-2xl object-cover ring-1 ring-article-border"
            />
            <div
                v-else
                class="overflow-hidden rounded-2xl ring-1 ring-article-border"
            >
                <ArticleThumb
                    :motif="article.motif ?? 'quote'"
                    :label="`Ilustrasi artikel ${article.judul}`"
                    class="!rounded-none"
                />
            </div>
        </figure>

        <ArticleBody
            :isi="article.isi"
            container-class="mt-10 text-[17px] leading-[1.9]"
        />
    </article>
</template>

<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ArrowLeft } from '@lucide/vue';
import ArticleBody from '@/components/ArticleBody.vue';
import ArticleThumb from '@/components/storefront-pcd/ArticleThumb.vue';
import StorefrontPcdLayout from '@/layouts/customer/StorefrontPcdLayout.vue';
import { formatDateID } from '@/lib/date';
import { home as homeRoute } from '@/routes/pcd';

defineOptions({ layout: StorefrontPcdLayout });

type Motif =
    'stack' | 'manuscript' | 'readers' | 'quote' | 'shelf' | 'pencil' | 'lamp';

type Article = {
    judul: string;
    kategori_label: string;
    penulis: string | null;
    ringkasan: string;
    isi: string;
    published_at: string | null;
    motif: Motif | null;
    cover_url: string | null;
    menit: number;
};

const props = defineProps<{ article: Article }>();

const motif = props.article.motif ?? 'lamp';
const penulis = props.article.penulis ?? 'Tim Penerbit';
const tanggal = props.article.published_at
    ? formatDateID(props.article.published_at)
    : '';
</script>

<template>
    <Head :title="`${article.judul} — Pustaka Cahaya Peradaban`">
        <meta name="description" :content="article.ringkasan" />
    </Head>

    <main class="mx-auto max-w-2xl px-4 pt-10 pb-24 md:pt-14 md:pb-32">
        <article>
            <Link
                :href="homeRoute().url"
                class="inline-flex min-h-12 items-center gap-1.5 text-sm text-pcd-muted transition-colors hover:text-pcd-ink focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-pcd-accent-strong"
            >
                <ArrowLeft class="size-4" aria-hidden="true" />
                Semua Artikel
            </Link>

            <p
                class="mt-12 text-xs font-semibold tracking-[0.16em] text-pcd-accent uppercase"
            >
                {{ article.kategori_label }}
            </p>
            <h1
                class="mt-4 font-serif text-[30px] leading-[1.25] font-semibold tracking-tight md:text-[40px] md:leading-[1.2]"
            >
                {{ article.judul }}
            </h1>
            <p class="mt-5 text-sm text-pcd-muted">
                {{ penulis }} · {{ tanggal }} · {{ article.menit }} menit baca
            </p>

            <!-- Ilustrasi: cover upload (R2) atau motif SVG ringan -->
            <figure class="mt-10">
                <img
                    v-if="article.cover_url"
                    :src="article.cover_url"
                    :alt="`Ilustrasi artikel ${article.judul}`"
                    class="aspect-video w-full rounded-lg object-cover ring-1 ring-pcd-hairline"
                />
                <ArticleThumb
                    v-else
                    :motif="motif"
                    :label="`Ilustrasi artikel ${article.judul}`"
                />
            </figure>

            <ArticleBody
                :isi="article.isi"
                container-class="pcd-prose font-serif text-[17.5px] md:text-lg"
            />
        </article>
    </main>
</template>

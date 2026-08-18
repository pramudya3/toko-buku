<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ArrowLeft } from '@lucide/vue';
import ArticleBody from '@/components/ArticleBody.vue';
import AdSlot from '@/components/storefront-pcd/AdSlot.vue';
import ArticleThumb from '@/components/storefront-pcd/ArticleThumb.vue';
import StorefrontPcdLayout from '@/layouts/customer/StorefrontPcdLayout.vue';
import { formatDateID } from '@/lib/date';
import { home as homeRoute } from '@/routes/pcd';
import type { BookPromo } from '@/types';

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

const props = defineProps<{ article: Article; books: BookPromo[] }>();

const motif = props.article.motif ?? 'quote';
const penulis = props.article.penulis ?? 'Tim Penerbit';
const tanggal = props.article.published_at
    ? formatDateID(props.article.published_at)
    : '';
</script>

<template>
    <Head :title="`${article.judul} — Pustaka Cahaya Peradaban`">
        <meta name="description" :content="article.ringkasan" />
    </Head>

    <main class="mx-auto max-w-6xl px-4 pt-10 pb-24 md:px-6 md:pt-14 md:pb-32">
        <article class="mx-auto max-w-2xl">
            <Link
                :href="homeRoute().url"
                class="inline-flex min-h-12 items-center gap-1.5 text-sm text-gray-500 transition-colors hover:text-flat-primary focus-visible:ring-2 focus-visible:ring-flat-primary focus-visible:ring-offset-2 focus-visible:outline-none"
            >
                <ArrowLeft class="size-4" aria-hidden="true" />
                Semua Artikel
            </Link>

            <p
                class="mt-12 text-xs font-semibold tracking-[0.16em] text-flat-primary uppercase"
            >
                {{ article.kategori_label }}
            </p>
            <h1
                class="mt-4 text-[30px] leading-[1.25] font-extrabold tracking-tight md:text-[40px] md:leading-[1.2]"
            >
                {{ article.judul }}
            </h1>
            <p class="mt-5 text-sm text-gray-500">
                {{ penulis }} · {{ tanggal }} · {{ article.menit }} menit baca
            </p>

            <!-- Ilustrasi: cover upload (R2) atau motif SVG ringan -->
            <figure class="mt-10">
                <img
                    v-if="article.cover_url"
                    :src="article.cover_url"
                    :alt="`Ilustrasi artikel ${article.judul}`"
                    class="aspect-video w-full rounded-lg object-cover border-2 border-flat-border"
                />
                <ArticleThumb
                    v-else
                    :motif="motif"
                    :label="`Ilustrasi artikel ${article.judul}`"
                />
            </figure>
        </article>

        <!-- Badan artikel + rail iklan samping (hilang di mobile) -->
        <div
            class="mx-auto mt-10 grid max-w-5xl items-start gap-10 lg:grid-cols-[minmax(0,1fr)_300px]"
        >
            <div class="max-w-2xl min-w-0">
                <ArticleBody
                    :isi="article.isi"
                    container-class="pcd-prose text-[17.5px] md:text-lg"
                />

                <!-- Iklan bawah: setelah konten, selebar kolom baca -->
                <AdSlot placement="bottom" :books="props.books" />
            </div>

            <AdSlot placement="side" :books="props.books" />
        </div>
    </main>
</template>

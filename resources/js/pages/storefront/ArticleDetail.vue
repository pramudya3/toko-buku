<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ArrowLeft, BookOpen } from '@lucide/vue';
import ArticleBody from '@/components/ArticleBody.vue';
import CustomerLayout from '@/layouts/customer/CustomerLayout.vue';
import { formatDateID } from '@/lib/date';
import { index as indexRoute } from '@/routes/articles';

defineOptions({ layout: CustomerLayout });

type Article = {
    judul: string;
    kategori_label: string;
    penulis: string | null;
    ringkasan: string;
    isi: string;
    published_at: string | null;
    cover_url: string | null;
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

    <article class="mx-auto flex max-w-2xl flex-col gap-6">
        <div>
            <Link
                :href="indexRoute().url"
                class="inline-flex min-h-10 items-center gap-1.5 text-sm text-muted-foreground transition-colors hover:text-foreground"
            >
                <ArrowLeft class="size-4" aria-hidden="true" />
                Semua Artikel
            </Link>

            <p
                class="mt-8 text-xs font-semibold tracking-widest text-primary uppercase"
            >
                {{ article.kategori_label }}
            </p>
            <h1 class="mt-3 text-3xl leading-tight font-bold tracking-tight">
                {{ article.judul }}
            </h1>
            <p class="mt-3 text-sm text-muted-foreground">
                {{ penulis }} · {{ tanggal }} · {{ article.menit }} menit baca
            </p>
        </div>

        <figure>
            <img
                v-if="article.cover_url"
                :src="article.cover_url"
                :alt="`Ilustrasi artikel ${article.judul}`"
                class="aspect-video w-full rounded-xl object-cover ring-1 ring-border"
            />
            <div
                v-else
                class="flex aspect-video w-full items-center justify-center rounded-xl bg-muted/50 ring-1 ring-border"
            >
                <BookOpen
                    class="size-12 text-muted-foreground/40"
                    aria-hidden="true"
                />
            </div>
        </figure>

        <ArticleBody
            :isi="article.isi"
            container-class="text-[17px] leading-[1.8]"
        />
    </article>
</template>

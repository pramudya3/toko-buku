<script setup lang="ts">
/**
 * EditorialArticleCard — kartu artikel untuk grid "Artikel Terbaru" & daftar
 * artikel. Sampul 16:9 (foto atau motif SVG), kategori, judul serif, ringkasan,
 * penulis, tanggal, dan estimasi lama baca.
 */
import { Link } from '@inertiajs/vue3';
import { Clock } from '@lucide/vue';
import ArticleThumb from '@/components/storefront/ArticleThumb.vue';
import { formatDateID } from '@/lib/date';
import { show as articleShowRoute } from '@/routes/articles';

type Motif =
    'stack' | 'manuscript' | 'readers' | 'quote' | 'shelf' | 'pencil' | 'lamp';

export type EditorialArticle = {
    id: string;
    judul: string;
    slug: string;
    kategori_label: string;
    penulis: string | null;
    ringkasan: string;
    published_at: string | null;
    motif: Motif | null;
    cover_url: string | null;
    menit: number;
};

defineProps<{
    article: EditorialArticle;
}>();

const dateLabel = (value: string | null): string =>
    value ? formatDateID(value) : '';
</script>

<template>
    <Link
        :href="articleShowRoute({ article: article.slug }).url"
        class="group flex h-full flex-col overflow-hidden rounded-xl border border-article-border bg-article-surface transition-shadow duration-200 hover:shadow-lg focus-visible:ring-2 focus-visible:ring-article-primary focus-visible:ring-offset-2 focus-visible:outline-none"
    >
        <!-- Sampul 16:9 -->
        <div
            class="relative aspect-video overflow-hidden border-b border-article-border bg-article-border/40"
        >
            <img
                v-if="article.cover_url"
                :src="article.cover_url"
                :alt="article.judul"
                class="size-full object-cover transition-transform duration-300 group-hover:scale-[1.03]"
            />
            <ArticleThumb
                v-else
                :motif="article.motif ?? 'quote'"
                :label="`Ilustrasi artikel ${article.judul}`"
            />
        </div>

        <!-- Konten -->
        <div class="flex flex-1 flex-col p-5">
            <div class="flex items-center gap-2">
                <span
                    class="text-xs font-semibold tracking-[0.14em] text-article-primary uppercase"
                >
                    {{ article.kategori_label }}
                </span>
                <span class="text-xs text-article-muted">
                    {{ dateLabel(article.published_at) }}
                </span>
            </div>

            <h3
                class="mt-3 font-serif text-xl leading-snug font-semibold tracking-tight text-article-ink"
            >
                <span
                    class="transition-colors group-hover:text-article-primary"
                >
                    {{ article.judul }}
                </span>
            </h3>

            <p
                class="mt-2 line-clamp-3 text-sm leading-relaxed text-article-muted"
            >
                {{ article.ringkasan }}
            </p>

            <div
                class="mt-4 flex items-center gap-1.5 border-t border-article-border pt-3 text-xs text-article-muted"
            >
                <span class="font-medium text-article-ink">
                    {{ article.penulis ?? 'Tim Penerbit' }}
                </span>
                <span aria-hidden="true">·</span>
                <span class="inline-flex items-center gap-1">
                    <Clock class="size-3.5" aria-hidden="true" />
                    {{ article.menit }} menit baca
                </span>
            </div>
        </div>
    </Link>
</template>

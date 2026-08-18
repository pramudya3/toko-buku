<script setup lang="ts">
/**
 * EditorialFeatured — artikel unggulan di puncak beranda.
 * Kolom ganda di desktop (gambar kiri, teks kanan), menumpuk di mobile.
 */
import { Link } from '@inertiajs/vue3';
import { ArrowRight, Clock } from '@lucide/vue';
import ArticleThumb from '@/components/storefront/ArticleThumb.vue';
import type { EditorialArticle } from '@/components/storefront/EditorialArticleCard.vue';
import { formatDateID } from '@/lib/date';
import { show as articleShowRoute } from '@/routes/articles';

defineProps<{
    article: EditorialArticle;
}>();

const dateLabel = (value: string | null): string =>
    value ? formatDateID(value) : '';
</script>

<template>
    <section
        class="overflow-hidden rounded-2xl border border-article-border bg-article-surface"
        :aria-label="`Artikel unggulan: ${article.judul}`"
    >
        <div class="grid md:grid-cols-2">
            <!-- Gambar -->
            <Link
                :href="articleShowRoute({ article: article.slug }).url"
                class="relative block aspect-video overflow-hidden md:aspect-auto md:min-h-full"
                :aria-label="`Baca artikel ${article.judul}`"
            >
                <img
                    v-if="article.cover_url"
                    :src="article.cover_url"
                    :alt="article.judul"
                    class="absolute inset-0 size-full object-cover transition-transform duration-300 hover:scale-[1.02]"
                />
                <ArticleThumb
                    v-else
                    :motif="article.motif ?? 'quote'"
                    :label="`Ilustrasi artikel ${article.judul}`"
                    class="absolute inset-0 size-full !rounded-none"
                />
            </Link>

            <!-- Teks -->
            <div class="flex flex-col justify-center p-6 md:p-10">
                <p
                    class="inline-flex w-fit items-center rounded-full bg-article-primary/10 px-3 py-1 text-xs font-semibold tracking-[0.14em] text-article-primary uppercase"
                >
                    Artikel Unggulan
                </p>
                <h2
                    class="mt-4 font-serif text-2xl leading-tight font-bold tracking-tight text-article-ink md:text-3xl"
                >
                    <Link
                        :href="articleShowRoute({ article: article.slug }).url"
                        class="transition-colors hover:text-article-primary"
                    >
                        {{ article.judul }}
                    </Link>
                </h2>
                <p
                    class="mt-3 line-clamp-3 text-sm leading-relaxed text-article-muted"
                >
                    {{ article.ringkasan }}
                </p>

                <div
                    class="mt-5 flex flex-wrap items-center gap-x-2 gap-y-1 text-xs text-article-muted"
                >
                    <span class="font-medium text-article-ink">
                        {{ article.penulis ?? 'Tim Penerbit' }}
                    </span>
                    <span aria-hidden="true">·</span>
                    <span>{{ dateLabel(article.published_at) }}</span>
                    <span aria-hidden="true">·</span>
                    <span class="inline-flex items-center gap-1">
                        <Clock class="size-3.5" aria-hidden="true" />
                        {{ article.menit }} menit baca
                    </span>
                </div>

                <Link
                    :href="articleShowRoute({ article: article.slug }).url"
                    class="mt-6 inline-flex w-fit items-center gap-1.5 rounded-lg bg-article-primary px-5 py-2.5 text-sm font-semibold text-white transition-colors hover:bg-article-primary-dark focus-visible:ring-2 focus-visible:ring-article-primary focus-visible:ring-offset-2 focus-visible:outline-none"
                >
                    Baca artikel
                    <ArrowRight class="size-4" aria-hidden="true" />
                </Link>
            </div>
        </div>
    </section>
</template>

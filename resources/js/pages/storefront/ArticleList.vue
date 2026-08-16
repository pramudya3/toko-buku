<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { BookOpen, ChevronLeft, ChevronRight, Newspaper } from '@lucide/vue';
import EmptyState from '@/components/EmptyState.vue';
import { Button } from '@/components/ui/button';
import CustomerLayout from '@/layouts/customer/CustomerLayout.vue';
import { formatDateID } from '@/lib/date';
import { index as indexRoute, show as showRoute } from '@/routes/articles';

defineOptions({ layout: CustomerLayout });

type Motif =
    'stack' | 'manuscript' | 'readers' | 'quote' | 'shelf' | 'pencil' | 'lamp';

type ArticleCard = {
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

type Props = {
    articles: {
        data: ArticleCard[];
        current_page: number;
        last_page: number;
        total: number;
        per_page: number;
        links: Array<{ url: string | null; label: string; active: boolean }>;
    };
};

const props = defineProps<Props>();

const dateLabel = (value: string | null): string =>
    value ? formatDateID(value) : '';

const paginator = props.articles;
</script>

<template>
    <Head title="Artikel" />

    <div class="flex flex-col gap-6">
        <div>
            <h1 class="text-2xl font-bold tracking-tight">Artikel</h1>
            <p class="text-sm text-muted-foreground">
                Esai, resensi, dan catatan dari meja redaksi
            </p>
        </div>

        <EmptyState
            v-if="paginator.data.length === 0"
            :lucide-icon="Newspaper"
            title="Belum ada artikel"
            description="Artikel akan tampil di sini setelah diterbitkan."
        >
            <Button size="sm" as-child>
                <Link :href="indexRoute().url">Lihat Toko</Link>
            </Button>
        </EmptyState>

        <template v-else>
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                <article
                    v-for="article in paginator.data"
                    :key="article.id"
                    class="flex flex-col gap-3 rounded-xl border p-4 transition-shadow hover:shadow-md"
                >
                    <Link
                        :href="showRoute({ article: article.slug }).url"
                        class="group block"
                        :aria-label="`Baca artikel ${article.judul}`"
                    >
                        <img
                            v-if="article.cover_url"
                            :src="article.cover_url"
                            :alt="`Ilustrasi artikel ${article.judul}`"
                            class="aspect-video w-full rounded-lg object-cover ring-1 ring-border transition-transform duration-300 group-hover:scale-[1.02]"
                        />
                        <div
                            v-else
                            class="flex aspect-video w-full items-center justify-center rounded-lg bg-muted/50 ring-1 ring-border"
                        >
                            <BookOpen
                                class="size-8 text-muted-foreground/50"
                                aria-hidden="true"
                            />
                        </div>
                    </Link>
                    <div class="flex flex-col gap-1.5">
                        <p class="flex items-center gap-2 text-xs">
                            <span
                                class="font-semibold tracking-wide text-primary uppercase"
                                >{{ article.kategori_label }}</span
                            >
                            <span class="text-muted-foreground">
                                {{ dateLabel(article.published_at) }}
                            </span>
                        </p>
                        <h2
                            class="text-base leading-snug font-semibold tracking-tight"
                        >
                            <Link
                                :href="showRoute({ article: article.slug }).url"
                                class="transition-colors hover:text-primary"
                            >
                                {{ article.judul }}
                            </Link>
                        </h2>
                        <p class="line-clamp-3 text-sm text-muted-foreground">
                            {{ article.ringkasan }}
                        </p>
                        <p class="text-xs text-muted-foreground">
                            {{ article.penulis ?? 'Tim Penerbit' }} ·
                            {{ article.menit }} menit baca
                        </p>
                    </div>
                </article>
            </div>

            <!-- Pagination -->
            <div
                v-if="paginator.last_page > 1"
                class="flex items-center justify-between gap-4 border-t pt-4"
            >
                <p class="text-sm text-muted-foreground">
                    Menampilkan
                    <span class="font-medium">{{
                        (paginator.current_page - 1) * paginator.per_page + 1
                    }}</span>
                    –
                    <span class="font-medium">
                        {{
                            Math.min(
                                paginator.current_page * paginator.per_page,
                                paginator.total,
                            )
                        }}
                    </span>
                    dari
                    <span class="font-medium">{{ paginator.total }}</span>
                </p>
                <div class="flex items-center gap-2">
                    <Button
                        v-if="paginator.current_page > 1"
                        variant="outline"
                        size="sm"
                        as-child
                    >
                        <Link
                            :href="paginator.links[0].url ?? '#'"
                            :preserve-scroll="true"
                        >
                            <ChevronLeft class="size-4" />
                            Sebelumnya
                        </Link>
                    </Button>
                    <Button
                        v-if="paginator.current_page < paginator.last_page"
                        variant="outline"
                        size="sm"
                        as-child
                    >
                        <Link
                            :href="
                                paginator.links[paginator.links.length - 1]
                                    .url ?? '#'
                            "
                            :preserve-scroll="true"
                        >
                            Berikutnya
                            <ChevronRight class="size-4" />
                        </Link>
                    </Button>
                </div>
            </div>
        </template>
    </div>
</template>

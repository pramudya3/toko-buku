<script setup lang="ts">
/**
 * ArticleCard — Flat design article card with SVG motif.
 * Used on home page and article listing.
 */
import { Link } from '@inertiajs/vue3';
import { Clock } from '@lucide/vue';
import { formatDateID } from '@/lib/date';

type Motif =
    | 'stack'
    | 'manuscript'
    | 'readers'
    | 'quote'
    | 'shelf'
    | 'pencil'
    | 'lamp';

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

defineProps<{
    article: ArticleCard;
    /** Show full content (for detail page) */
    featured?: boolean;
}>();

function articleUrl(slug: string): string {
    return `/artikel/${slug}`;
}
</script>

<template>
    <Link
        :href="articleUrl(article.slug)"
        class="group block cursor-pointer rounded-lg bg-white p-4 transition-all duration-200 hover:scale-[1.02]"
    >
        <!-- Cover / Motif -->
        <div class="relative aspect-video overflow-hidden rounded-md bg-flat-muted">
            <img
                v-if="article.cover_url"
                :src="article.cover_url"
                :alt="article.judul"
                class="size-full object-cover transition-transform duration-200 group-hover:scale-105"
            />
            <svg
                v-else-if="article.motif"
                viewBox="0 0 320 180"
                role="img"
                :aria-label="article.judul"
                class="size-full"
            >
                <rect width="320" height="180" fill="#F3F4F6" />

                <!-- Stack motif -->
                <template v-if="article.motif === 'stack'">
                    <rect x="128" y="26" width="64" height="12" rx="2" fill="#3B82F6" />
                    <rect x="116" y="42" width="88" height="16" rx="2" fill="#10B981" />
                    <rect x="110" y="62" width="100" height="16" rx="2" fill="#F59E0B" />
                    <rect x="104" y="82" width="112" height="16" rx="2" fill="#3B82F6" />
                    <rect x="98" y="102" width="124" height="16" rx="2" fill="#10B981" />
                    <rect x="92" y="122" width="136" height="16" rx="2" fill="#F59E0B" />
                </template>

                <!-- Manuscript motif -->
                <template v-else-if="article.motif === 'manuscript'">
                    <rect x="88" y="38" width="144" height="104" rx="4" fill="#ffffff" stroke="#E5E7EB" stroke-width="2" />
                    <g stroke="#111827" opacity=".22" stroke-width="5" stroke-linecap="round">
                        <path d="M110 62h70" />
                        <path d="M110 80h98" />
                        <path d="M110 98h84" />
                        <path d="M110 116h52" />
                    </g>
                    <rect x="248" y="42" width="12" height="70" rx="3" fill="#3B82F6" />
                    <path d="M248 112l6 16 6-16Z" fill="#111827" opacity=".7" />
                </template>

                <!-- Readers motif -->
                <template v-else-if="article.motif === 'readers'">
                    <circle cx="112" cy="62" r="16" fill="#3B82F6" opacity=".14" />
                    <circle cx="208" cy="56" r="13" fill="#10B981" opacity=".11" />
                    <path d="M150 95c-24-19-64-24-96-13v80c32-11 72-6 96 13 24-19 64-24 96-13V82c-32-11-72-6-96 13Z" fill="#E5E7EB" />
                    <path d="M150 95v80" stroke="#3B82F6" stroke-width="3" opacity=".5" />
                </template>

                <!-- Quote motif -->
                <template v-else-if="article.motif === 'quote'">
                    <text x="160" y="64" text-anchor="middle" font-family="Georgia, serif" font-size="56" font-weight="600" fill="#3B82F6">"</text>
                    <path d="M160 92c-22-17-58-21-88-11v70c30-10 66-6 88 11 22-17 58-21 88-11V81c-30-10-66-6-88 11Z" fill="#E5E7EB" />
                    <path d="M160 92v70" stroke="#3B82F6" stroke-width="3" opacity=".5" />
                </template>

                <!-- Shelf motif -->
                <template v-else-if="article.motif === 'shelf'">
                    <path d="M72 62h176" stroke="#111827" stroke-width="6" opacity=".15" />
                    <rect x="88" y="30" width="18" height="30" rx="2" fill="#3B82F6" opacity=".45" />
                    <rect x="112" y="24" width="14" height="36" rx="2" fill="#10B981" />
                    <rect x="132" y="34" width="16" height="26" rx="2" fill="#F59E0B" opacity=".3" />
                    <rect x="154" y="26" width="13" height="34" rx="2" fill="#3B82F6" opacity=".5" />
                    <path d="M72 112h176" stroke="#111827" stroke-width="6" opacity=".15" />
                    <rect x="90" y="82" width="20" height="28" rx="2" fill="#10B981" opacity=".35" />
                    <rect x="116" y="78" width="15" height="32" rx="2" fill="#F59E0B" opacity=".5" />
                    <rect x="137" y="88" width="17" height="22" rx="2" fill="#3B82F6" opacity=".9" />
                    <path d="M72 162h176" stroke="#111827" stroke-width="6" opacity=".15" />
                </template>

                <!-- Pencil motif -->
                <template v-else-if="article.motif === 'pencil'">
                    <g stroke="#111827" opacity=".2" stroke-width="5" stroke-linecap="round">
                        <path d="M86 58h96" />
                        <path d="M86 78h126" />
                        <path d="M86 98h106" />
                        <path d="M86 118h72" />
                    </g>
                    <rect x="228" y="38" width="13" height="76" rx="3" fill="#3B82F6" />
                    <path d="M228 114l6.5 18 6.5-18Z" fill="#111827" opacity=".7" />
                </template>

                <!-- Lamp motif (default) -->
                <template v-else>
                    <path d="M128 52h64l-12 26h-40Z" fill="#111827" opacity=".5" />
                    <path d="M160 78v34" stroke="#111827" stroke-width="5" opacity=".25" />
                    <rect x="134" y="112" width="52" height="8" rx="4" fill="#111827" opacity=".4" />
                    <path d="M128 78L84 140h44Z" fill="#3B82F6" opacity=".16" />
                    <rect x="78" y="138" width="96" height="12" rx="2" fill="#111827" opacity=".35" />
                </template>
            </svg>
            <div v-else class="flex size-full items-center justify-center">
                <span class="text-4xl text-gray-300">📝</span>
            </div>
        </div>

        <!-- Info -->
        <div class="mt-4">
            <!-- Category badge -->
            <span
                class="inline-block rounded-md bg-flat-primary/10 px-2 py-0.5 text-xs font-semibold text-flat-primary"
            >
                {{ article.kategori_label }}
            </span>

            <h3
                class="mt-2 line-clamp-2 text-sm font-bold text-flat-ink"
                style="font-family: var(--font-flat)"
            >
                {{ article.judul }}
            </h3>

            <p
                v-if="article.penulis"
                class="mt-1 text-xs text-gray-500"
            >
                oleh {{ article.penulis }}
            </p>

            <p
                v-if="article.ringkasan"
                class="mt-2 line-clamp-2 text-xs text-gray-600"
            >
                {{ article.ringkasan }}
            </p>

            <!-- Meta -->
            <div class="mt-3 flex items-center gap-3 text-xs text-gray-400">
                <span v-if="article.published_at">
                    {{ formatDateID(article.published_at) }}
                </span>
                <span class="flex items-center gap-1">
                    <Clock class="size-3" />
                    {{ article.menit }} menit
                </span>
            </div>
        </div>
    </Link>
</template>

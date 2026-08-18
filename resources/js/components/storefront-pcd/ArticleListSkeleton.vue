<script setup lang="ts">
/**
 * ArticleListSkeleton — placeholder pulse saat navigasi filter/pencarian.
 * Meniru baris daftar artikel proto-d (thumb 16:9 + meta + judul + ringkasan).
 */
defineProps<{
    rows?: number;
}>();
</script>

<template>
    <div
        class="divide-y divide-flat-border"
        aria-hidden="true"
        data-testid="article-list-skeleton"
    >
        <div
            v-for="n in rows ?? 4"
            :key="n"
            class="flex animate-pulse gap-5 py-8"
        >
            <div
                class="aspect-video w-28 shrink-0 rounded-md bg-flat-border sm:w-40"
            />
            <div class="min-w-0 flex-1 space-y-3 pt-1">
                <div class="h-3 w-28 rounded bg-flat-border" />
                <div class="h-5 w-2/3 rounded bg-flat-border" />
                <div class="h-3 w-full rounded bg-flat-border/70" />
                <div class="h-3 w-3/4 rounded bg-flat-border/70" />
            </div>
        </div>
    </div>
</template>

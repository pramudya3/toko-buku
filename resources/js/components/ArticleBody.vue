<script setup lang="ts">
/**
 * Perender isi artikel — HTML dari editor WYSIWYG (TipTap), disajikan dengan
 * tipografi membaca (prose). Keamanan dijamin server-side (sanitasi allowlist
 * saat disimpan lewat ArticleSanitizer).
 */
defineProps<{
    isi: string;
    containerClass?: string;
}>();
</script>

<template>
    <div
        class="article-body prose max-w-none prose-stone dark:prose-invert"
        :class="containerClass"
        v-html="isi"
    />
</template>

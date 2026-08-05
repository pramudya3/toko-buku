<script setup lang="ts">
import { BookOpen } from '@lucide/vue';

defineProps<{
    title: string;
}>();
</script>

<template>
    <div
        class="flex items-center justify-center bg-gradient-to-br from-muted to-muted/50"
    >
        <div class="flex flex-col items-center gap-2 p-4 text-center">
            <BookOpen class="size-8 text-muted-foreground/60" />
            <span
                class="line-clamp-2 max-w-full text-xs font-medium text-muted-foreground"
            >
                {{ title }}
            </span>
        </div>
    </div>
</template>

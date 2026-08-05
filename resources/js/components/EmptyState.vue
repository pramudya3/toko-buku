<script setup lang="ts">
import { PackageOpen } from '@lucide/vue';

withDefaults(
    defineProps<{
        title?: string;
        description?: string;
    }>(),
    {
        title: 'Belum ada data',
        description: 'Data akan tampil di sini setelah tersedia.',
    },
);
</script>

<template>
    <div
        class="flex flex-col items-center justify-center gap-2 py-12 text-center"
    >
        <div
            class="flex size-12 items-center justify-center rounded-full bg-muted"
        >
            <PackageOpen class="size-6 text-muted-foreground" />
        </div>
        <p class="text-sm font-medium">{{ title }}</p>
        <p class="max-w-sm text-sm text-muted-foreground">{{ description }}</p>
        <div class="mt-2">
            <slot />
        </div>
    </div>
</template>

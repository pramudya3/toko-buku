<script setup lang="ts">
import type { HTMLAttributes } from 'vue';
import { cn } from '@/lib/utils';

const props = defineProps<{
    class?: HTMLAttributes['class'];
}>();
</script>

<template>
    <div
        data-slot="table-container"
        :class="cn('w-full overflow-x-auto', props.class)"
    >
        <table
            data-slot="table"
            class="data-slot w-full caption-bottom text-sm"
        >
            <slot />
        </table>
    </div>
</template>

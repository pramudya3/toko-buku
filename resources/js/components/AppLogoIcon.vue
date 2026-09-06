<script setup lang="ts">
import { BookOpen } from '@lucide/vue';
import type { HTMLAttributes } from 'vue';

defineOptions({
    inheritAttrs: false,
});

type Props = {
    className?: HTMLAttributes['class'];
};

defineProps<Props>();
</script>

<template>
    <BookOpen :class="className" v-bind="$attrs" aria-hidden="true" />
</template>

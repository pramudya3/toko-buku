<script setup lang="ts">
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
    <svg
        xmlns="http://www.w3.org/2000/svg"
        viewBox="0 0 40 42"
        :class="className"
        v-bind="$attrs"
        role="img"
        aria-label="TokoBuku"
    >
        <text
            x="50%"
            y="54%"
            text-anchor="middle"
            dominant-baseline="middle"
            font-family="Inter, ui-sans-serif, system-ui, sans-serif"
            font-weight="700"
            letter-spacing="-0.5"
            fill="currentColor"
        >
            TB
        </text>
    </svg>
</template>

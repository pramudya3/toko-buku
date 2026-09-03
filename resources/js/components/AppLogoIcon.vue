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
    <img
        src="/logo-pcp.png"
        alt="Pustaka Cahaya Peradaban"
        :class="className"
        v-bind="$attrs"
    />
</template>

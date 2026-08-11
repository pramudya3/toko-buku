<script setup lang="ts">
import { computed } from 'vue';

const props = withDefaults(
    defineProps<{
        value: number;
        prefix?: string;
    }>(),
    {
        value: 0,
        prefix: 'Rp',
    },
);

const formatted = computed(() =>
    (props.value ?? 0).toLocaleString('id-ID', {
        maximumFractionDigits: 0,
    }),
);
</script>

<template>
    <span class="whitespace-nowrap tabular-nums"
        >{{ prefix }} {{ formatted }}</span
    >
</template>

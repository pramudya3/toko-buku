<script setup lang="ts">
// Ringkasan nilai nota: baris-baris (label/nilai) + baris TOTAL yang ditebalkan.

defineProps<{
    lines: Array<{ label: string; value: string }>;
    totalLabel?: string;
    total: string;
}>();
</script>

<template>
    <div class="ml-auto w-72 space-y-1 text-xs">
        <div
            v-for="line in lines"
            :key="line.label"
            class="flex items-center justify-between gap-4"
        >
            <span class="text-slate-500">{{ line.label }}</span>
            <span class="tabular-nums">{{ line.value }}</span>
        </div>
        <div
            class="flex items-center justify-between gap-4 border-t-2 border-slate-900 pt-1.5 text-sm font-bold"
        >
            <span>{{ totalLabel ?? 'TOTAL' }}</span>
            <span class="tabular-nums">{{ total }}</span>
        </div>
    </div>
</template>

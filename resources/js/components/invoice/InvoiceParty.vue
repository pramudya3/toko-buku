<script setup lang="ts">
// Blok pihak (Kepada/Dari): judul + daftar baris label/nilai.

defineProps<{
    title: string;
    lines: Array<{ label: string; value: string }>;
}>();
</script>

<template>
    <div>
        <p
            class="mb-1 text-xs font-semibold tracking-wide text-slate-500 uppercase"
        >
            {{ title }}
        </p>
        <div class="space-y-0.5">
            <p
                v-for="line in lines.filter((l) => l.value)"
                :key="line.label"
                class="text-xs"
            >
                <span class="text-slate-500">{{ line.label }}: </span>
                <span class="font-medium">{{ line.value }}</span>
            </p>
        </div>
    </div>
</template>

<script setup lang="ts">
// Footer nota: catatan + blok tanda tangan + ucapan terima kasih.

defineProps<{
    notes?: string | null;
    signatures: Array<{ label: string; name?: string | null }>;
    thanks?: string;
}>();
</script>

<template>
    <div class="mt-8">
        <p v-if="notes" class="text-xs text-slate-600">
            <span class="font-semibold">Catatan:</span> {{ notes }}
        </p>

        <div class="mt-10 flex justify-end gap-16 pr-4 text-center text-xs">
            <div v-for="sig in signatures" :key="sig.label" class="w-36">
                <p class="mb-16">{{ sig.label }}</p>
                <p class="border-t border-slate-900 pt-1 font-medium">
                    {{ sig.name || '(................)' }}
                </p>
            </div>
        </div>

        <p v-if="thanks" class="mt-8 text-center text-xs text-slate-500">
            {{ thanks }}
        </p>
    </div>
</template>

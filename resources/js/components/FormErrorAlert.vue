<script setup lang="ts">
import { CircleAlert, X } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import { Button } from '@/components/ui/button';

const props = defineProps<{
    errors: Record<string, string>;
    title?: string;
}>();

const dismissed = ref(false);

// Munculkan kembali banner saat ada error baru.
watch(
    () => props.errors,
    () => {
        dismissed.value = false;
    },
);

const messages = computed(() => {
    const list = Object.values(props.errors).filter(Boolean);

    return [...new Set(list)];
});

const visible = computed(() => !dismissed.value && messages.value.length > 0);
</script>

<template>
    <div
        v-if="visible"
        role="alert"
        class="rounded-lg border border-destructive/40 bg-destructive/5 p-4 text-sm text-destructive"
    >
        <div class="flex items-start justify-between gap-3">
            <div class="flex items-start gap-2">
                <CircleAlert class="mt-0.5 size-4 shrink-0" />
                <div class="grid gap-1.5">
                    <p class="font-medium">
                        {{ title ?? 'Periksa kembali isian form.' }}
                    </p>
                    <ul
                        v-if="messages.length"
                        class="list-disc space-y-0.5 pl-5"
                    >
                        <li
                            v-for="message in messages.slice(0, 5)"
                            :key="message"
                        >
                            {{ message }}
                        </li>
                        <li
                            v-if="messages.length > 5"
                            class="text-muted-foreground"
                        >
                            dan {{ messages.length - 5 }} lainnya
                        </li>
                    </ul>
                </div>
            </div>
            <Button
                variant="ghost"
                size="icon"
                class="size-6 shrink-0 text-destructive hover:bg-destructive/10 hover:text-destructive"
                aria-label="Tutup"
                @click="dismissed = true"
            >
                <X class="size-3.5" />
            </Button>
        </div>
    </div>
</template>

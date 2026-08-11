<script setup lang="ts">
import { ChevronsUpDown, Search } from '@lucide/vue';
import { PopoverContent, PopoverRoot, PopoverTrigger } from 'reka-ui';
import { computed, ref } from 'vue';
import { Input } from '@/components/ui/input';

export type SelectOption = {
    value: string;
    label: string;
    hint?: string | null;
};

const props = withDefaults(
    defineProps<{
        modelValue: string;
        options: SelectOption[];
        name?: string;
        placeholder?: string;
        searchPlaceholder?: string;
        emptyText?: string;
        disabled?: boolean;
    }>(),
    {
        name: undefined,
        placeholder: 'Pilih...',
        searchPlaceholder: 'Cari...',
        emptyText: 'Tidak ada hasil',
        disabled: false,
    },
);

const emit = defineEmits<{
    'update:modelValue': [value: string];
}>();

const open = ref(false);
const query = ref('');

const selected = computed(
    () => props.options.find((o) => o.value === props.modelValue) ?? null,
);

// Filter client-side — cocok untuk daftar wilayah (ratusan item maksimal).
const filtered = computed(() => {
    const q = query.value.trim().toLowerCase();

    if (!q) {
        return props.options;
    }

    return props.options.filter(
        (o) =>
            o.label.toLowerCase().includes(q) ||
            (o.hint?.toLowerCase().includes(q) ?? false),
    );
});

function select(value: string) {
    emit('update:modelValue', value);
    open.value = false;
    query.value = '';
}

function onOpenChange(value: boolean) {
    open.value = value;

    if (!value) {
        query.value = '';
    }
}
</script>

<template>
    <!-- Nilai dikirim lewat hidden input agar form tetap menerima field ini. -->
    <input type="hidden" :name="name" :value="modelValue" />

    <PopoverRoot v-model:open="open" @update:open="onOpenChange">
        <PopoverTrigger as-child>
            <div
                role="button"
                :tabindex="disabled ? -1 : 0"
                :id="name"
                class="flex min-h-9 w-full cursor-pointer items-center justify-between gap-2 rounded-md border bg-transparent px-3 py-1.5 text-left text-sm transition-[color,box-shadow] outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                :class="disabled && 'pointer-events-none opacity-50'"
            >
                <span
                    class="truncate"
                    :class="selected ? '' : 'text-muted-foreground'"
                >
                    {{ selected?.label ?? placeholder }}
                </span>
                <ChevronsUpDown class="size-4 shrink-0 text-muted-foreground" />
            </div>
        </PopoverTrigger>

        <PopoverContent
            align="start"
            side="bottom"
            class="z-50 w-(--reka-popover-trigger-width) min-w-[240px] rounded-lg border bg-popover p-0 shadow-lg"
        >
            <div class="relative border-b p-2">
                <Search
                    class="absolute top-1/2 left-4 size-4 -translate-y-1/2 text-muted-foreground"
                />
                <Input
                    v-model="query"
                    class="pl-8"
                    :placeholder="searchPlaceholder"
                    autofocus
                />
            </div>
            <div class="max-h-64 overflow-y-auto p-1">
                <p
                    v-if="filtered.length === 0"
                    class="px-3 py-6 text-center text-xs text-muted-foreground"
                >
                    {{ emptyText }}
                </p>
                <button
                    v-for="option in filtered"
                    :key="option.value"
                    type="button"
                    class="flex w-full items-center justify-between gap-2 rounded-md px-2 py-2 text-left text-sm transition-colors hover:bg-muted/60"
                    :class="
                        option.value === modelValue &&
                        'bg-primary/5 font-medium'
                    "
                    @click="select(option.value)"
                >
                    <span class="truncate">{{ option.label }}</span>
                    <span
                        v-if="option.hint"
                        class="shrink-0 text-xs text-muted-foreground"
                    >
                        {{ option.hint }}
                    </span>
                </button>
            </div>
        </PopoverContent>
    </PopoverRoot>
</template>

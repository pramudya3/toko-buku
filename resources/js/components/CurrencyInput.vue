<script setup lang="ts">
import { computed, ref, watch } from 'vue';

const props = withDefaults(
    defineProps<{
        id?: string;
        name: string;
        defaultValue?: number | string | null;
        placeholder?: string;
        required?: boolean;
        disabled?: boolean;
        invalid?: boolean;
    }>(),
    {
        id: undefined,
        defaultValue: null,
        placeholder: '0',
        required: false,
        disabled: false,
        invalid: false,
    },
);

function parseRaw(value: number | string | null): number | '' {
    const parsed = parseInt(String(value ?? ''), 10);

    return Number.isNaN(parsed) ? '' : parsed;
}

const rawValue = ref<number | ''>(parseRaw(props.defaultValue));

watch(
    () => props.defaultValue,
    (value) => {
        rawValue.value = parseRaw(value);
    },
);

const displayValue = computed(() =>
    rawValue.value === '' ? '' : rawValue.value.toLocaleString('id-ID'),
);

function handleInput(event: Event) {
    const digits = (event.target as HTMLInputElement).value.replace(/\D/g, '');

    rawValue.value = digits === '' ? '' : parseInt(digits, 10);
}
</script>

<template>
    <div class="relative">
        <span
            class="absolute left-3 top-1/2 -translate-y-1/2 text-sm text-muted-foreground"
        >
            Rp
        </span>
        <input
            :id="id"
            type="text"
            inputmode="numeric"
            autocomplete="off"
            :placeholder="placeholder"
            :required="required"
            :disabled="disabled"
            :aria-invalid="invalid ? true : undefined"
            :value="displayValue"
            data-slot="input"
            class="border-input focus-visible:border-ring focus-visible:ring-ring/50 aria-invalid:border-destructive dark:bg-input/30 dark:aria-invalid:ring-destructive/40 file:text-foreground placeholder:text-muted-foreground selection:bg-primary selection:text-primary-foreground shadow-xs focus-visible:ring-[3px] h-9 w-full min-w-0 rounded-md border bg-transparent px-3 py-1 pr-8 text-base outline-none transition-[color,box-shadow] file:inline-flex file:h-7 file:border-0 file:bg-transparent file:text-sm file:font-medium disabled:pointer-events-none disabled:cursor-not-allowed disabled:opacity-50 aria-invalid:ring-destructive/20 md:text-sm pl-9"
            @input="handleInput"
        />
        <input
            type="hidden"
            :name="name"
            :value="rawValue === '' ? '' : rawValue"
        />
    </div>
</template>

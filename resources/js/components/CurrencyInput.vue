<script setup lang="ts">
import { computed, ref, watch } from 'vue';

/**
 * Input nominal rupiah dengan format ribuan (id-ID).
 *
 * Dua mode:
 * - Native form (Inertia <Form>): beri prop `name` → nilai dikirim via
 *   hidden input dengan nama tersebut.
 * - Programmatic (router.post + reactive state): gunakan `v-model` →
 *   meng-emit angka (number) via `update:modelValue`.
 *
 * Keduanya bisa dipakai bersamaan.
 */
const props = withDefaults(
    defineProps<{
        id?: string;
        name?: string;
        modelValue?: number | string | null;
        defaultValue?: number | string | null;
        placeholder?: string;
        required?: boolean;
        disabled?: boolean;
        invalid?: boolean;
        inputClass?: string;
    }>(),
    {
        id: undefined,
        name: undefined,
        modelValue: null,
        defaultValue: null,
        placeholder: '0',
        required: false,
        disabled: false,
        invalid: false,
        inputClass: '',
    },
);

const emit = defineEmits<{
    (e: 'update:modelValue', value: number): void;
}>();

function parseRaw(value: number | string | null): number | '' {
    const parsed = parseInt(String(value ?? ''), 10);

    return Number.isNaN(parsed) ? '' : parsed;
}

// Nilai sumber: modelValue (v-model) lebih diutamakan, fallback defaultValue.
const sourceValue = computed(() =>
    props.modelValue === null || props.modelValue === undefined
        ? props.defaultValue
        : props.modelValue,
);

const rawValue = ref<number | ''>(parseRaw(sourceValue.value));

// Sinkron balik ke v-model (mode v-model): kosong → 0.
let internalChange = false;

watch(rawValue, (value) => {
    if (props.modelValue !== null && props.modelValue !== undefined) {
        internalChange = true;
        emit('update:modelValue', value === '' ? 0 : value);
    }
});

watch(sourceValue, (value) => {
    // Jangan timpa saat perubahan berasal dari emit sendiri (user mengetik).
    if (!internalChange) {
        rawValue.value = parseRaw(value);
    }

    internalChange = false;
});

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
            class="absolute top-1/2 left-3 -translate-y-1/2 text-sm text-muted-foreground"
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
            :class="[
                'h-9 w-full min-w-0 rounded-md border border-input bg-transparent px-3 py-1 pr-8 pl-9 text-base shadow-xs transition-[color,box-shadow] outline-none selection:bg-primary selection:text-primary-foreground file:inline-flex file:h-7 file:border-0 file:bg-transparent file:text-sm file:font-medium file:text-foreground placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 disabled:pointer-events-none disabled:cursor-not-allowed disabled:opacity-50 aria-invalid:border-destructive aria-invalid:ring-destructive/20 md:text-sm dark:bg-input/30 dark:aria-invalid:ring-destructive/40',
                inputClass,
            ]"
            data-slot="input"
            @input="handleInput"
        />
        <input
            v-if="name"
            type="hidden"
            :name="name"
            :value="rawValue === '' ? '' : rawValue"
        />
    </div>
</template>

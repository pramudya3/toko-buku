<script setup lang="ts">
// Tabel item nota — generik: kolom + baris nilai string (sudah diformat
// oleh halaman pemanggil). Slot opsional `cell-{key}` untuk sel khusus.

export type InvoiceColumn = {
    key: string;
    label: string;
    align?: 'left' | 'center' | 'right';
};

defineProps<{
    columns: InvoiceColumn[];
    rows: Array<Record<string, string | number>>;
}>();

const alignClass = (align: InvoiceColumn['align']): string => {
    if (align === 'right') {
        return 'text-right';
    }

    if (align === 'center') {
        return 'text-center';
    }

    return 'text-left';
};
</script>

<template>
    <table class="w-full border-collapse">
        <thead>
            <tr class="border-y-2 border-slate-900 bg-slate-100">
                <th
                    v-for="col in columns"
                    :key="col.key"
                    :class="[
                        'px-2 py-1.5 text-xs font-semibold uppercase',
                        alignClass(col.align),
                    ]"
                >
                    {{ col.label }}
                </th>
            </tr>
        </thead>
        <tbody>
            <tr
                v-for="(row, i) in rows"
                :key="i"
                class="border-b border-slate-300"
            >
                <td
                    v-for="col in columns"
                    :key="col.key"
                    :class="['px-2 py-1.5 align-top', alignClass(col.align)]"
                >
                    <slot :name="`cell-${col.key}`" :row="row">
                        {{ row[col.key] }}
                    </slot>
                </td>
            </tr>
        </tbody>
    </table>
</template>

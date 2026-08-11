<script setup lang="ts" generic="T extends Record<string, any>">
import { ChevronUp } from '@lucide/vue';
import { computed } from 'vue';
import EmptyState from '@/components/EmptyState.vue';
import Pagination from '@/components/Pagination.vue';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';

export type DataTableColumn = {
    key: string;
    header?: string;
    /** Class untuk <th> (default: ikut cellClass). */
    headerClass?: string;
    /** Class untuk <td>. */
    cellClass?: string;
    /** Header hanya untuk screen reader (kolom aksi). */
    srOnly?: boolean;
    /** Kolom khusus tombol expand baris (chevron) — butuh slot #expanded-row. */
    expandable?: boolean;
};

type Paginator = {
    data: unknown[];
    current_page: number;
    last_page: number;
    total: number;
    per_page: number;
    links: Array<{ url: string | null; label: string; active: boolean }>;
};

const props = withDefaults(
    defineProps<{
        data: T[];
        columns: DataTableColumn[];
        keyField?: string;
        /** Tampilkan checkbox seleksi baris (default: false). */
        selectable?: boolean;
        selectedIds?: Set<string | number>;
        /** Class tambahan per baris (mis. highlight weekend). */
        rowClass?: (row: T) => string;
        paginator?: Paginator | null;
        emptyTitle?: string;
        emptyDescription?: string;
        /** Aktifkan baris expandable — butuh kolom expandable + slot #expanded-row. */
        expandable?: boolean;
        expandedIds?: Set<string | number>;
    }>(),
    {
        keyField: 'id',
        selectable: false,
        selectedIds: undefined,
        rowClass: undefined,
        paginator: null,
        emptyTitle: 'Tidak ada data',
        emptyDescription: 'Data akan tampil di sini setelah tersedia.',
        expandable: false,
        expandedIds: undefined,
    },
);

const emit = defineEmits<{
    'update:selectedIds': [value: Set<string | number>];
    'update:expandedIds': [value: Set<string | number>];
}>();

const expanded = computed({
    get: () => props.expandedIds ?? new Set<string | number>(),
    set: (value) => emit('update:expandedIds', value),
});

const isExpanded = (row: T): boolean => expanded.value.has(rowKey(row));

function toggleExpand(row: T) {
    const next = new Set(expanded.value);
    const key = rowKey(row);

    if (next.has(key)) {
        next.delete(key);
    } else {
        next.add(key);
    }

    expanded.value = next;
}

const selected = computed({
    get: () => props.selectedIds ?? new Set<string | number>(),
    set: (value) => emit('update:selectedIds', value),
});

const rowKey = (row: T): string => String(row[props.keyField] ?? '');

const allSelected = computed(
    () =>
        props.data.length > 0 &&
        props.data.every((row) => selected.value.has(rowKey(row))),
);

const someSelected = computed(() =>
    props.data.some((row) => selected.value.has(rowKey(row))),
);

function toggleAll() {
    const next = new Set(selected.value);

    if (allSelected.value) {
        props.data.forEach((row) => next.delete(rowKey(row)));
    } else {
        props.data.forEach((row) => next.add(rowKey(row)));
    }

    selected.value = next;
}

function toggleRow(row: T) {
    const next = new Set(selected.value);
    const key = rowKey(row);

    if (next.has(key)) {
        next.delete(key);
    } else {
        next.add(key);
    }

    selected.value = next;
}
</script>

<template>
    <div>
        <div class="overflow-hidden rounded-md border bg-card">
            <Table>
                <TableHeader>
                    <TableRow>
                        <TableHead v-if="selectable" class="w-10">
                            <Checkbox
                                :model-value="
                                    allSelected ||
                                    (someSelected && 'indeterminate')
                                "
                                aria-label="Pilih semua"
                                @update:model-value="toggleAll"
                            />
                        </TableHead>
                        <TableHead
                            v-for="col in columns"
                            :key="col.key"
                            :class="col.headerClass ?? col.cellClass"
                        >
                            <span v-if="col.srOnly" class="sr-only">{{
                                col.header
                            }}</span>
                            <slot
                                v-else
                                :name="`header-${col.key}`"
                                :column="col"
                            >
                                {{ col.header }}
                            </slot>
                        </TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    <template v-for="row in data" :key="rowKey(row)">
                        <TableRow :class="rowClass?.(row)">
                            <TableCell v-if="selectable">
                                <Checkbox
                                    :model-value="selected.has(rowKey(row))"
                                    aria-label="Pilih baris"
                                    @update:model-value="toggleRow(row)"
                                />
                            </TableCell>
                            <TableCell
                                v-for="col in columns"
                                :key="col.key"
                                :class="col.cellClass"
                            >
                                <button
                                    v-if="col.expandable"
                                    type="button"
                                    class="inline-flex size-8 items-center justify-center rounded-md text-muted-foreground transition-colors hover:bg-accent hover:text-foreground"
                                    :aria-expanded="isExpanded(row)"
                                    :aria-label="
                                        isExpanded(row)
                                            ? 'Tutup detail'
                                            : 'Lihat detail'
                                    "
                                    :title="
                                        isExpanded(row)
                                            ? 'Tutup detail'
                                            : 'Lihat detail'
                                    "
                                    @click="toggleExpand(row)"
                                >
                                    <ChevronUp
                                        class="size-4 transition-transform"
                                        :class="
                                            isExpanded(row) ? 'rotate-180' : ''
                                        "
                                    />
                                </button>
                                <slot
                                    v-else
                                    :name="`cell-${col.key}`"
                                    :row="row"
                                    :value="row[col.key]"
                                >
                                    {{ row[col.key] }}
                                </slot>
                            </TableCell>
                        </TableRow>
                        <TableRow
                            v-if="expandable && isExpanded(row)"
                            class="border-0 bg-muted/30"
                        >
                            <TableCell
                                :colspan="(selectable ? 1 : 0) + columns.length"
                                class="p-0"
                            >
                                <slot name="expanded-row" :row="row" />
                            </TableCell>
                        </TableRow>
                    </template>
                    <slot name="after-rows" />
                </TableBody>
            </Table>

            <slot name="footer" />

            <EmptyState
                v-if="!data.length"
                :title="emptyTitle"
                :description="emptyDescription"
            >
                <slot name="empty" />
            </EmptyState>

            <Pagination
                v-if="paginator && paginator.last_page > 1"
                :paginator="paginator"
            />
        </div>

        <p
            v-if="selectable && selected.size > 0"
            class="mt-2 text-sm text-muted-foreground"
        >
            {{ selected.size }} dari {{ paginator?.total ?? data.length }}
            baris dipilih.
        </p>
    </div>
</template>

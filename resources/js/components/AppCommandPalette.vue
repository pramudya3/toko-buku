<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { Search } from '@lucide/vue';
import { useDebounceFn } from '@vueuse/core';
import { computed, ref, watch, onMounted, onUnmounted, nextTick } from 'vue';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { toUrl } from '@/lib/utils';
import type { NavGroup, NavItem } from '@/types';

const props = defineProps<{
    open: boolean;
    groups: NavGroup[];
    items?: Array<NavItem>;
    footerItems?: Array<NavItem>;
}>();

const emit = defineEmits<{
    (e: 'update:open', value: boolean): void;
}>();

const query = ref('');
const debouncedQuery = ref('');
const selectedIndex = ref(0);

const debouncedSetQuery = useDebounceFn((val: string) => {
    debouncedQuery.value = val;
}, 100);

type FlatItem = NavItem & { groupLabel?: string };

const allItems = computed<FlatItem[]>(() => {
    const flat: FlatItem[] = [];

    if (props.items) {
        for (const item of props.items) {
            // NavItem | NavSection — only items with href
            if ('href' in item && item.href) {
                flat.push(item as FlatItem);
            }
        }
    }

    for (const group of props.groups) {
        for (const item of group.items) {
            if ('items' in item && Array.isArray((item as any).items)) {
                for (const child of (item as any).items as NavItem[]) {
                    flat.push({
                        ...child,
                        groupLabel: `${group.label} › ${item.title}`,
                    });
                }
            } else {
                flat.push({ ...item, groupLabel: group.label } as FlatItem);
            }
        }
    }

    if (props.footerItems) {
        for (const item of props.footerItems) {
            if ('href' in item && item.href) {
                flat.push(item as FlatItem);
            }
        }
    }

    return flat;
});

const filtered = computed<FlatItem[]>(() => {
    const q = debouncedQuery.value.trim().toLowerCase();

    if (!q) {
        return allItems.value.slice(0, 8);
    }

    const words = q.split(/\s+/).filter(Boolean);

    return allItems.value
        .filter((item) => {
            const title = item.title.toLowerCase();
            const group = (item.groupLabel ?? '').toLowerCase();
            const haystack = `${title} ${group}`;

            return words.every((w) => haystack.includes(w));
        })
        .slice(0, 10);
});

function navigate(item: FlatItem) {
    const href = toUrl(item.href);
    emit('update:open', false);
    query.value = '';
    selectedIndex.value = 0;
    router.visit(href);
}

function onKeyDown(e: KeyboardEvent) {
    if (!props.open) {
        return;
    }

    if (e.key === 'ArrowDown') {
        e.preventDefault();
        selectedIndex.value = Math.min(
            selectedIndex.value + 1,
            filtered.value.length - 1,
        );
    } else if (e.key === 'ArrowUp') {
        e.preventDefault();
        selectedIndex.value = Math.max(selectedIndex.value - 1, 0);
    } else if (e.key === 'Enter') {
        e.preventDefault();
        const item = filtered.value[selectedIndex.value];

        if (item) {
            navigate(item);
        }
    }
}

watch(
    () => props.open,
    async (open) => {
        if (open) {
            query.value = '';
            debouncedQuery.value = '';
            selectedIndex.value = 0;
            await nextTick();
            const el = document.querySelector<HTMLInputElement>(
                '[data-command-input]',
            );
            el?.focus();
        }
    },
);

watch(query, (val) => {
    debouncedSetQuery(val);
});

watch(debouncedQuery, () => {
    selectedIndex.value = 0;
});

// Global shortcut: Cmd+K / Ctrl+K and /
function onGlobalKeyDown(e: KeyboardEvent) {
    if ((e.metaKey || e.ctrlKey) && e.key.toLowerCase() === 'k') {
        e.preventDefault();
        emit('update:open', !props.open);
    } else if (e.key === '/' && !props.open) {
        // Only trigger / when not typing in input/textarea
        const target = e.target as HTMLElement;
        const isInput =
            target.tagName === 'INPUT' ||
            target.tagName === 'TEXTAREA' ||
            target.isContentEditable;

        if (!isInput) {
            e.preventDefault();
            emit('update:open', true);
        }
    }
}

onMounted(() => {
    window.addEventListener('keydown', onGlobalKeyDown);
});

onUnmounted(() => {
    window.removeEventListener('keydown', onGlobalKeyDown);
});
</script>

<template>
    <Dialog :open="open" @update:open="emit('update:open', $event)">
        <DialogContent
            :show-close-button="false"
            class="gap-0 overflow-hidden p-0 sm:max-w-[520px]"
            @keydown="onKeyDown"
        >
            <DialogHeader class="sr-only">
                <DialogTitle>Cari Menu</DialogTitle>
                <DialogDescription
                    >Cari menu navigasi — ketik kata kunci lalu
                    Enter</DialogDescription
                >
            </DialogHeader>
            <div class="flex items-center border-b px-3">
                <Search class="mr-2 size-4 shrink-0 opacity-50" />
                <Input
                    data-command-input
                    v-model="query"
                    placeholder="Cari menu… (kas, laporan, kategori, pesanan)"
                    class="flex h-11 w-full rounded-none border-0 bg-transparent py-3 shadow-none focus-visible:ring-0"
                />
                <kbd
                    class="ml-2 hidden shrink-0 rounded border bg-muted px-1.5 py-0.5 font-mono text-[10px] text-muted-foreground sm:inline-flex"
                    >ESC</kbd
                >
            </div>
            <div class="max-h-[320px] overflow-y-auto p-2">
                <div
                    v-if="filtered.length === 0"
                    class="py-8 text-center text-sm text-muted-foreground"
                >
                    Tidak ada menu untuk “{{ query }}”
                </div>
                <div v-else class="space-y-1">
                    <button
                        v-for="(item, idx) in filtered"
                        :key="`${item.groupLabel ?? ''}-${item.title}`"
                        type="button"
                        class="flex w-full items-center gap-3 rounded-md px-3 py-2.5 text-left text-sm transition-colors will-change-auto hover:bg-accent"
                        :class="idx === selectedIndex ? 'bg-accent' : ''"
                        @click="navigate(item)"
                        @mouseenter="selectedIndex = idx"
                    >
                        <component
                            :is="item.icon"
                            v-if="item.icon"
                            class="size-4 shrink-0 text-muted-foreground"
                        />
                        <div class="flex flex-1 flex-col">
                            <span class="leading-none font-medium">{{
                                item.title
                            }}</span>
                            <span
                                v-if="item.groupLabel"
                                class="text-xs text-muted-foreground"
                                >{{ item.groupLabel }}</span
                            >
                        </div>
                        <span
                            v-if="item.badge && item.badge > 0"
                            class="ml-auto inline-flex size-5 items-center justify-center rounded-full bg-primary text-xs font-semibold text-primary-foreground"
                            >{{ item.badge }}</span
                        >
                    </button>
                </div>
            </div>
        </DialogContent>
    </Dialog>
</template>

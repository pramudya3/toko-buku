<script setup lang="ts">
import { Link, router } from '@inertiajs/vue3';
import { ChevronLeft, ChevronRight } from '@lucide/vue';
import { Button } from '@/components/ui/button';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';

type Paginator = {
    current_page: number;
    last_page: number;
    total: number;
    per_page: number;
    data: unknown[];
    links: Array<{ url: string | null; label: string; active: boolean }>;
};

const props = defineProps<{
    paginator: Paginator;
}>();

const perPageOptions = [10, 20, 50, 100] as const;

function changePerPage(value: unknown) {
    const perPage = String(value);
    const url = new URL(window.location.href);

    url.searchParams.set('per_page', perPage);
    url.searchParams.delete('page');

    const params = Object.fromEntries(url.searchParams.entries());

    router.get(url.pathname, params as Record<string, string>, {
        preserveState: true,
        replace: true,
        preserveScroll: true,
    });
}
</script>

<template>
    <div
        class="flex flex-col gap-3 border-t px-4 py-3 sm:flex-row sm:items-center sm:justify-between"
    >
        <div class="flex flex-wrap items-center gap-4">
            <div class="flex items-center gap-2">
                <span class="text-sm whitespace-nowrap text-muted-foreground"
                    >Baris per halaman:</span
                >
                <Select
                    :model-value="String(props.paginator.per_page)"
                    @update:model-value="changePerPage"
                >
                    <SelectTrigger class="h-8 w-[72px]">
                        <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem
                            v-for="opt in perPageOptions"
                            :key="opt"
                            :value="String(opt)"
                        >
                            {{ opt }}
                        </SelectItem>
                    </SelectContent>
                </Select>
            </div>
            <p class="text-sm text-muted-foreground">
                Menampilkan
                <span class="font-medium">{{
                    props.paginator.total === 0
                        ? 0
                        : (props.paginator.current_page - 1) *
                              props.paginator.per_page +
                          1
                }}</span>
                –
                <span class="font-medium">
                    {{
                        Math.min(
                            props.paginator.current_page *
                                props.paginator.per_page,
                            props.paginator.total,
                        )
                    }}
                </span>
                dari
                <span class="font-medium">{{ props.paginator.total }}</span>
            </p>
        </div>
        <div
            v-if="props.paginator.last_page > 1"
            class="flex items-center gap-2"
        >
            <Button
                v-if="props.paginator.current_page > 1"
                variant="outline"
                size="sm"
                as-child
            >
                <Link
                    :href="props.paginator.links[0].url ?? '#'"
                    :preserve-scroll="true"
                >
                    <ChevronLeft class="size-4" />
                    Sebelumnya
                </Link>
            </Button>
            <Button
                v-if="props.paginator.current_page < props.paginator.last_page"
                variant="outline"
                size="sm"
                as-child
            >
                <Link
                    :href="
                        props.paginator.links[props.paginator.links.length - 1]
                            .url ?? '#'
                    "
                    :preserve-scroll="true"
                >
                    Berikutnya
                    <ChevronRight class="size-4" />
                </Link>
            </Button>
        </div>
    </div>
</template>

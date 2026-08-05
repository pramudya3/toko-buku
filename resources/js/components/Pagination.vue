<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ChevronLeft, ChevronRight } from '@lucide/vue';
import { Button } from '@/components/ui/button';

type Paginator = {
    current_page: number;
    last_page: number;
    total: number;
    per_page: number;
    data: unknown[];
    links: Array<{ url: string | null; label: string; active: boolean }>;
};

defineProps<{
    paginator: Paginator;
}>();
</script>

<template>
    <div
        v-if="paginator.last_page > 1"
        class="flex items-center justify-between gap-4 border-t px-4 py-3"
    >
        <p class="text-sm text-muted-foreground">
            Menampilkan
            <span class="font-medium">{{
                (paginator.current_page - 1) * paginator.per_page + 1
            }}</span
            > –
            <span class="font-medium">
                {{
                    Math.min(
                        paginator.current_page * paginator.per_page,
                        paginator.total,
                    )
                }}
            </span>
            dari <span class="font-medium">{{ paginator.total }}</span>
        </p>
        <div class="flex items-center gap-2">
            <Button
                v-if="paginator.current_page > 1"
                variant="outline"
                size="sm"
                as-child
            >
                <Link
                    :href="paginator.links[0].url ?? '#'"
                    :preserve-scroll="true"
                >
                    <ChevronLeft class="size-4" />
                    Sebelumnya
                </Link>
            </Button>
            <Button
                v-if="paginator.current_page < paginator.last_page"
                variant="outline"
                size="sm"
                as-child
            >
                <Link
                    :href="
                        paginator.links[paginator.links.length - 1].url ?? '#'
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

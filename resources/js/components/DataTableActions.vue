<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { EllipsisVertical } from '@lucide/vue';
import type { LucideIcon } from '@lucide/vue';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';

export type RowAction = {
    label: string;
    icon?: LucideIcon;
    href?: string;
    onClick?: () => void;
    variant?: 'default' | 'destructive';
};

defineProps<{
    actions: RowAction[];
}>();
</script>

<template>
    <DropdownMenu>
        <DropdownMenuTrigger as-child>
            <Button
                variant="ghost"
                size="icon"
                class="size-7 text-muted-foreground"
            >
                <EllipsisVertical class="size-3.5" />
            </Button>
        </DropdownMenuTrigger>
        <DropdownMenuContent align="end">
            <template v-for="action in actions" :key="action.label">
                <DropdownMenuItem v-if="action.href" as-child>
                    <Link :href="action.href">
                        <component
                            :is="action.icon"
                            v-if="action.icon"
                            class="size-4"
                        />
                        {{ action.label }}
                    </Link>
                </DropdownMenuItem>
                <DropdownMenuItem
                    v-else
                    :class="
                        action.variant === 'destructive'
                            ? 'text-destructive focus:text-destructive'
                            : ''
                    "
                    @click="action.onClick"
                >
                    <component
                        :is="action.icon"
                        v-if="action.icon"
                        class="size-4"
                    />
                    {{ action.label }}
                </DropdownMenuItem>
            </template>
        </DropdownMenuContent>
    </DropdownMenu>
</template>

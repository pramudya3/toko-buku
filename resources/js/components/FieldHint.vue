<script setup lang="ts">
import { Info } from '@lucide/vue';
import {
    Tooltip,
    TooltipContent,
    TooltipProvider,
    TooltipTrigger,
} from '@/components/ui/tooltip';

defineProps<{
    text: string;
}>();
</script>

<template>
    <TooltipProvider>
        <Tooltip :delay-duration="0">
            <TooltipTrigger as-child @click.prevent>
                <span
                    tabindex="0"
                    class="inline-flex cursor-help items-center rounded-sm text-muted-foreground outline-none focus-visible:ring-2 focus-visible:ring-ring"
                >
                    <Info class="size-3.5" />
                </span>
            </TooltipTrigger>
            <TooltipContent side="top" class="max-w-xs text-xs">
                {{ text }}
            </TooltipContent>
        </Tooltip>
    </TooltipProvider>
</template>

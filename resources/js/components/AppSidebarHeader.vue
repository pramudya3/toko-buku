<script setup lang="ts">
import { StoreIcon } from '@lucide/vue';
import { onBeforeUnmount, onMounted, ref } from 'vue';
import Breadcrumbs from '@/components/Breadcrumbs.vue';
import NavUser from '@/components/NavUser.vue';
import { Button } from '@/components/ui/button';
import { SidebarTrigger } from '@/components/ui/sidebar';
import {
    Tooltip,
    TooltipContent,
    TooltipProvider,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import type { BreadcrumbItem } from '@/types';

withDefaults(
    defineProps<{
        breadcrumbs?: BreadcrumbItem[];
    }>(),
    {
        breadcrumbs: () => [],
    },
);

// Border bawah header muncul hanya setelah halaman discroll.
const isScrolled = ref(false);

function updateScroll() {
    isScrolled.value = window.scrollY > 8;
}

onMounted(() => {
    updateScroll();
    window.addEventListener('scroll', updateScroll, { passive: true });
});

onBeforeUnmount(() => {
    window.removeEventListener('scroll', updateScroll);
});
</script>

<template>
    <header
        class="sticky top-0 z-10 flex h-16 shrink-0 items-center gap-2 bg-background px-6 transition-[width,height,border-color,box-shadow] ease-linear group-has-data-[collapsible=icon]/sidebar-wrapper:h-12 md:px-4"
        :class="
            isScrolled
                ? 'border border-sidebar-border/70 shadow-xs'
                : 'border border-transparent shadow-none'
        "
    >
        <div class="flex items-center gap-2">
            <SidebarTrigger class="-ml-1" />
            <template v-if="breadcrumbs && breadcrumbs.length > 0">
                <Breadcrumbs :breadcrumbs="breadcrumbs" />
            </template>
        </div>

        <!-- Pojok kanan: storefront + user (avatar & nama) -->
        <div class="ml-auto flex items-center gap-2">
            <TooltipProvider>
                <Tooltip>
                    <TooltipTrigger as-child>
                        <Button
                            variant="ghost"
                            size="icon"
                            class="size-9 rounded-full text-muted-foreground"
                            as-child
                        >
                            <a
                                href="/"
                                target="_blank"
                                rel="noopener noreferrer"
                            >
                                <StoreIcon class="size-4" />
                                <span class="sr-only">Buka Storefront</span>
                            </a>
                        </Button>
                    </TooltipTrigger>
                    <TooltipContent>Buka Storefront</TooltipContent>
                </Tooltip>
            </TooltipProvider>
            <NavUser />
        </div>
    </header>
</template>

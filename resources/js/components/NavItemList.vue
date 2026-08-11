<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ChevronRight } from '@lucide/vue';
import { reactive } from 'vue';
import { Collapsible, CollapsibleContent } from '@/components/ui/collapsible';
import {
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
    SidebarMenuSub,
    SidebarMenuSubButton,
    SidebarMenuSubItem,
} from '@/components/ui/sidebar';
import { useSidebar } from '@/components/ui/sidebar';
import { useCurrentUrl } from '@/composables/useCurrentUrl';
import type { NavItem, NavSection } from '@/types';

defineProps<{
    items: Array<NavItem | NavSection>;
}>();

const { isMobile, setOpenMobile } = useSidebar();
const { isCurrentUrl, isCurrentOrParentUrl } = useCurrentUrl();

// Sub menu default TERBUKA (mengurangi klik); toggle user dihormati selama sesi.
// Force-open saat route aktif berada di dalam sub menu (tidak pernah "terkunci").
const openStates = reactive<Record<string, boolean>>({});

function hasSubmenu(item: NavItem | NavSection): item is NavSection {
    return 'items' in item && item.items.length > 0;
}

function isOpen(item: NavSection): boolean {
    const hasActiveChild = item.items.some((child) =>
        isCurrentOrParentUrl(child.href),
    );

    return hasActiveChild || (openStates[item.title] ?? true);
}

function toggle(item: NavSection): void {
    openStates[item.title] = !isOpen(item);
}

function closeOnMobile(): void {
    if (isMobile) {
        setOpenMobile(false);
    }
}
</script>

<template>
    <SidebarMenu class="px-2 py-2">
        <SidebarMenuItem v-for="item in items" :key="item.title">
            <Collapsible
                v-if="hasSubmenu(item)"
                as-child
                :open="isOpen(item)"
                class="group/collapsible"
            >
                <SidebarMenuButton :tooltip="item.title" @click="toggle(item)">
                    <component :is="item.icon" />
                    <span>{{ item.title }}</span>
                    <ChevronRight
                        class="ml-auto transition-transform duration-200 group-data-[state=open]/collapsible:rotate-90"
                    />
                </SidebarMenuButton>
                <CollapsibleContent>
                    <SidebarMenuSub>
                        <SidebarMenuSubItem
                            v-for="child in item.items"
                            :key="child.title"
                        >
                            <SidebarMenuSubButton
                                as-child
                                :is-active="isCurrentUrl(child.href)"
                                @click="closeOnMobile"
                            >
                                <Link :href="child.href">
                                    <span>{{ child.title }}</span>
                                </Link>
                            </SidebarMenuSubButton>
                        </SidebarMenuSubItem>
                    </SidebarMenuSub>
                </CollapsibleContent>
            </Collapsible>

            <SidebarMenuButton
                v-else
                as-child
                :is-active="isCurrentUrl(item.href)"
                :tooltip="item.title"
            >
                <Link :href="item.href" @click="closeOnMobile">
                    <component :is="item.icon" />
                    <span>{{ item.title }}</span>
                    <span
                        v-if="item.badge && item.badge > 0"
                        class="ml-auto inline-flex size-5 items-center justify-center rounded-full bg-primary text-xs font-semibold text-primary-foreground"
                    >
                        {{ item.badge }}
                    </span>
                </Link>
            </SidebarMenuButton>
        </SidebarMenuItem>
    </SidebarMenu>
</template>

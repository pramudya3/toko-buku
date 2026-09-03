<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ChevronRight } from '@lucide/vue';
import { reactive } from 'vue';
import NavItemList from '@/components/NavItemList.vue';
import { Collapsible, CollapsibleContent } from '@/components/ui/collapsible';
import {
    SidebarGroup,
    SidebarGroupLabel,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { useSidebar } from '@/components/ui/sidebar';
import { useCurrentUrl } from '@/composables/useCurrentUrl';
import type { NavGroup, NavItem, NavSection } from '@/types';

defineProps<{
    groups: NavGroup[];
    /** Item mandiri di atas (Dashboard, Pelanggan). */
    items?: Array<NavItem | NavSection>;
    /** Item mandiri di paling bawah (Pengaturan). */
    footerItems?: Array<NavItem | NavSection>;
}>();

const { isMobile, setOpenMobile } = useSidebar();
const { isCurrentUrl } = useCurrentUrl();

// Grup default TERBUKA (mengurangi klik); toggle user dihormati selama sesi.
const openGroups = reactive<Record<string, boolean>>({});

function isGroupOpen(group: NavGroup): boolean {
    return openGroups[group.label] ?? true;
}

function toggleGroup(group: NavGroup): void {
    openGroups[group.label] = !isGroupOpen(group);
}

function closeOnMobile(): void {
    if (isMobile) {
        setOpenMobile(false);
    }
}
</script>

<template>
    <NavItemList v-if="items?.length" :items="items" />

    <SidebarGroup v-for="group in groups" :key="group.label" class="px-2 py-0">
        <Collapsible
            as-child
            :open="isGroupOpen(group)"
            class="group/collapsible"
        >
            <SidebarGroupLabel
                class="cursor-pointer select-none"
                @click="toggleGroup(group)"
            >
                {{ group.label }}
                <ChevronRight
                    class="ml-auto transition-transform duration-200 group-data-[state=open]/collapsible:rotate-90"
                />
            </SidebarGroupLabel>
            <CollapsibleContent>
                <SidebarMenu>
                    <template v-for="item in group.items" :key="item.title">
                        <SidebarMenuItem v-if="'items' in item">
                            <NavItemList :items="[item]" />
                        </SidebarMenuItem>
                        <SidebarMenuItem v-else>
                            <SidebarMenuButton
                                as-child
                                :is-active="
                                    isCurrentUrl((item as NavItem).href)
                                "
                                :tooltip="item.title"
                            >
                                <Link
                                    :href="(item as NavItem).href"
                                    @click="closeOnMobile"
                                >
                                    <component :is="(item as NavItem).icon" />
                                    <span>{{ item.title }}</span>
                                    <span
                                        v-if="
                                            (item as NavItem).badge &&
                                            (item as NavItem).badge! > 0
                                        "
                                        class="ml-auto inline-flex size-5 items-center justify-center rounded-full bg-primary text-xs font-semibold text-primary-foreground"
                                    >
                                        {{ (item as NavItem).badge }}
                                    </span>
                                </Link>
                            </SidebarMenuButton>
                        </SidebarMenuItem>
                    </template>
                </SidebarMenu>
            </CollapsibleContent>
        </Collapsible>
    </SidebarGroup>

    <NavItemList v-if="footerItems?.length" :items="footerItems" />
</template>

<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { ChevronDown } from '@lucide/vue';
import { computed, ref } from 'vue';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import UserMenuContent from '@/components/UserMenuContent.vue';
import { useInitials } from '@/composables/useInitials';
import type { User } from '@/types';

const page = usePage();
const user = computed(() => page.props.auth.user);
const { getInitials } = useInitials();

const showAvatar = computed(
    () => user.value.avatar && user.value.avatar !== '',
);

// Chevron: down saat tertutup → rotate 180° (up) saat terbuka.
const menuOpen = ref(false);
</script>

<template>
    <DropdownMenu v-model:open="menuOpen">
        <DropdownMenuTrigger as-child>
            <!-- Satu container: klik nama / avatar / chevron → dropdown -->
            <Button
                variant="ghost"
                class="h-9 gap-2 rounded-full px-2 pr-1.5"
                data-test="sidebar-menu-button"
            >
                <span
                    class="hidden max-w-36 truncate text-sm font-medium md:block"
                >
                    {{ user.name }}
                </span>
                <Avatar class="size-8 overflow-hidden rounded-full">
                    <AvatarImage
                        v-if="showAvatar"
                        :src="user.avatar!"
                        :alt="user.name"
                    />
                    <AvatarFallback
                        class="rounded-full text-black dark:text-white"
                    >
                        {{ getInitials(user.name) }}
                    </AvatarFallback>
                </Avatar>
                <ChevronDown
                    class="size-3.5 text-muted-foreground transition-transform duration-200"
                    :class="menuOpen ? 'rotate-180' : ''"
                />
            </Button>
        </DropdownMenuTrigger>
        <DropdownMenuContent
            class="min-w-56 rounded-lg"
            side="bottom"
            align="end"
            :side-offset="4"
        >
            <UserMenuContent :user="user as User" />
        </DropdownMenuContent>
    </DropdownMenu>
</template>

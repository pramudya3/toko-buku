<script setup lang="ts">
import { Link, router, usePage } from '@inertiajs/vue3';
import {
    BookOpen,
    LayoutGrid,
    LogIn,
    LogOut,
    MapPin,
    Package,
    ShoppingCart,
    UserPen,
} from '@lucide/vue';
import { computed } from 'vue';
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import { Avatar, AvatarFallback } from '@/components/ui/avatar';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Toaster } from '@/components/ui/sonner';
import { about, home, logout } from '@/routes';
import { edit as editAddress } from '@/routes/address';
import { dashboard as adminDashboard } from '@/routes/admin';
import { edit as editProfile } from '@/routes/profile';
import type { User } from '@/types';

const page = usePage();
const storeName = computed(() => page.props.storeName ?? page.props.name);
const storeLogoUrl = computed(() => page.props.storeLogoUrl ?? '');
const user = computed<User | null>(() => page.props.auth?.user ?? null);
const isAdmin = computed(() => user.value?.is_admin === true);
const cartCount = computed<number>(() => Number(page.props.cartCount ?? 0));

const initials = computed(() => {
    const name = user.value?.name ?? '';

    return (
        name
            .split(' ')
            .filter(Boolean)
            .map((word) => word[0])
            .slice(0, 2)
            .join('')
            .toUpperCase() || '?'
    );
});

// ── Desktop nav (header, hidden md:flex) ──
const desktopNavItems = computed(() => {
    const items: { label: string; href: string; badge?: number }[] = [
        { label: 'Beranda', href: home().url },
    ];

    if (user.value && !isAdmin.value) {
        items.push({
            label: 'Keranjang',
            href: '/checkout',
            badge: cartCount.value,
        });
        items.push({ label: 'Pesanan Saya', href: '/pesanan-saya' });
    }

    if (isAdmin.value) {
        items.push({
            label: 'Dashboard',
            href: adminDashboard().url,
        });
    }

    // Guest: hanya Keranjang setelah Beranda
    if (!user.value) {
        items.push({
            label: 'Keranjang',
            href: '/checkout',
            badge: cartCount.value,
        });
    }

    return items;
});

// ── Bottom nav (mobile only) ──
const bottomNavItems = computed(() => {
    if (user.value && !isAdmin.value) {
        return [
            { label: 'Beranda', href: home().url, icon: BookOpen },
            {
                label: 'Keranjang',
                href: '/checkout',
                icon: ShoppingCart,
                badge: cartCount.value,
            },
            { label: 'Pesanan', href: '/pesanan-saya', icon: Package },
            { label: 'Profil', href: '/settings/alamat', icon: UserPen },
        ];
    }

    if (isAdmin.value) {
        return [
            { label: 'Beranda', href: home().url, icon: BookOpen },
            {
                label: 'Dashboard',
                href: adminDashboard().url,
                icon: LayoutGrid,
            },
            { label: 'Profil', href: '/settings/alamat', icon: UserPen },
        ];
    }

    // Guest
    return [
        { label: 'Beranda', href: home().url, icon: BookOpen },
        {
            label: 'Keranjang',
            href: '/checkout',
            icon: ShoppingCart,
            badge: cartCount.value,
        },
        { label: 'Masuk', href: '/login', icon: LogIn },
        { label: 'Daftar', href: '/register', icon: UserPen },
    ];
});

function isBottomNavActive(href: string): boolean {
    if (href === home().url) {
        return page.url === '/';
    }

    return page.url.startsWith(href);
}
</script>

<template>
    <div class="flex min-h-svh flex-col bg-background">
        <!-- ── Top bar ── -->
        <header
            class="sticky top-0 z-40 border-b bg-background/95 backdrop-blur supports-[backdrop-filter]:bg-background/60"
        >
            <div
                class="mx-auto flex h-14 w-full max-w-6xl items-center gap-2 px-4"
            >
                <!-- Logo → Tentang Kami -->
                <Link
                    :href="about().url"
                    class="flex items-center gap-2"
                    title="Tentang Kami"
                >
                    <img
                        v-if="storeLogoUrl"
                        :src="storeLogoUrl"
                        :alt="storeName"
                        class="h-8 w-auto object-contain"
                    />
                    <AppLogoIcon v-else class="size-5 fill-current" />
                    <span class="text-sm font-semibold">{{ storeName }}</span>
                </Link>

                <!-- Nav desktop -->
                <nav class="ml-4 hidden items-center gap-1 text-sm md:flex">
                    <Link
                        v-for="item in desktopNavItems"
                        :key="item.label"
                        :href="item.href"
                        class="relative rounded-md px-3 py-1.5 text-muted-foreground transition-colors hover:bg-muted hover:text-foreground"
                    >
                        {{ item.label }}
                        <span
                            v-if="item.badge && item.badge > 0"
                            class="ml-1.5 inline-flex size-5 items-center justify-center rounded-full bg-primary text-[11px] font-semibold text-primary-foreground"
                        >
                            {{ item.badge }}
                        </span>
                    </Link>
                </nav>

                <div class="ml-auto flex items-center gap-1">
                    <!-- Avatar dropdown (customer & admin) -->
                    <DropdownMenu v-if="user">
                        <DropdownMenuTrigger as-child>
                            <Button
                                variant="ghost"
                                class="size-9 rounded-full p-0"
                                title="Menu akun"
                            >
                                <Avatar class="size-8">
                                    <AvatarFallback
                                        class="bg-primary text-primary-foreground"
                                    >
                                        {{ initials }}
                                    </AvatarFallback>
                                </Avatar>
                            </Button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent align="end" class="min-w-40">
                            <DropdownMenuLabel class="font-normal">
                                <p class="truncate text-sm font-medium">
                                    {{ user.name }}
                                </p>
                                <p
                                    class="truncate text-xs text-muted-foreground"
                                >
                                    {{ user.email }}
                                </p>
                            </DropdownMenuLabel>
                            <DropdownMenuSeparator />
                            <div class="hidden md:block">
                                <DropdownMenuLabel
                                    class="text-xs text-muted-foreground"
                                >
                                    Pengaturan
                                </DropdownMenuLabel>
                                <DropdownMenuItem as-child>
                                    <Link :href="editProfile()">
                                        <UserPen class="size-4" />
                                        Akun
                                    </Link>
                                </DropdownMenuItem>
                                <DropdownMenuItem as-child>
                                    <Link :href="editAddress()">
                                        <MapPin class="size-4" />
                                        Alamat
                                    </Link>
                                </DropdownMenuItem>
                                <DropdownMenuSeparator />
                            </div>
                            <DropdownMenuItem
                                class="text-destructive focus:text-destructive"
                                as-child
                            >
                                <Link
                                    :href="logout()"
                                    @click="router.flushAll()"
                                    as="button"
                                >
                                    <LogOut class="size-4" />
                                    Logout
                                </Link>
                            </DropdownMenuItem>
                        </DropdownMenuContent>
                    </DropdownMenu>

                    <!-- Guest: login/register (desktop only) -->
                    <template v-if="!user">
                        <Button
                            variant="ghost"
                            size="sm"
                            class="hidden md:inline-flex"
                            as-child
                        >
                            <Link :href="'/login'">Masuk</Link>
                        </Button>
                        <Button
                            size="sm"
                            class="hidden md:inline-flex"
                            as-child
                        >
                            <Link :href="'/register'">
                                <UserPen class="size-4" />
                                Daftar
                            </Link>
                        </Button>
                    </template>
                </div>
            </div>
        </header>

        <!-- ── Main content ── -->
        <main class="mx-auto w-full max-w-6xl flex-1 px-4 pt-8 pb-20 md:pb-8">
            <slot />
        </main>

        <!-- ── Footer ── -->
        <footer class="border-t py-6 pb-20 md:pb-6">
            <p class="text-center text-sm text-muted-foreground">
                © {{ new Date().getFullYear() }} {{ storeName }}
            </p>
        </footer>

        <!-- ── Bottom nav (mobile only) ── -->
        <nav
            class="fixed inset-x-0 bottom-0 z-40 border-t bg-background/95 backdrop-blur md:hidden"
        >
            <div
                class="mx-auto flex h-16 max-w-6xl items-center justify-around px-2"
            >
                <Link
                    v-for="item in bottomNavItems"
                    :key="item.label"
                    :href="item.href"
                    class="relative flex flex-col items-center gap-0.5 px-3 py-1.5 text-[10px] font-medium transition-colors"
                    :class="
                        isBottomNavActive(item.href)
                            ? 'text-primary'
                            : 'text-muted-foreground'
                    "
                >
                    <component :is="item.icon" class="size-5" />
                    <span>{{ item.label }}</span>
                    <span
                        v-if="item.badge && item.badge > 0"
                        class="absolute -top-0.5 right-1 inline-flex size-4 items-center justify-center rounded-full bg-primary text-[10px] font-semibold text-primary-foreground"
                    >
                        {{ item.badge }}
                    </span>
                </Link>
            </div>
        </nav>

        <Toaster />
    </div>
</template>

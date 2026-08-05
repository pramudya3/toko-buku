<script setup lang="ts">
import { Link, router, usePage } from '@inertiajs/vue3';
import { BookOpen, LogOut, Menu, Package, ShoppingCart } from '@lucide/vue';
import { computed, ref } from 'vue';
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import { Button } from '@/components/ui/button';
import {
    Sheet,
    SheetContent,
    SheetHeader,
    SheetTitle,
    SheetTrigger,
} from '@/components/ui/sheet';
import { logout } from '@/routes';
import { home } from '@/routes';
import { dashboard as adminDashboard } from '@/routes/admin';
import type { User } from '@/types';

const page = usePage();
const user = computed<User | null>(() => page.props.auth?.user ?? null);
const isAdmin = computed(() => user.value?.is_admin === true);
const cartCount = computed<number>(() => page.props.cartCount ?? 0);
const mobileMenuOpen = ref(false);

// Beranda tidak perlu di menu — logo sudah menuju beranda.
const navItems = computed(() => {
    const items = [
        { label: 'Katalog', href: '/buku' },
        { label: 'Tentang Kami', href: '/tentang-kami' },
    ];

    if (user.value && !isAdmin.value) {
        items.push({ label: 'Checkout', href: '/checkout' });
        items.push({ label: 'Pesanan Saya', href: '/pesanan-saya' });
    }

    return items;
});
</script>

<template>
    <div class="flex min-h-svh flex-col bg-background">
        <header
            class="sticky top-0 z-40 border-b bg-background/95 backdrop-blur supports-[backdrop-filter]:bg-background/60"
        >
            <div class="mx-auto flex h-14 w-full max-w-6xl items-center gap-2 px-4">
                <!-- Logo → Beranda -->
                <Link :href="home()" class="flex items-center gap-2">
                    <span
                        class="flex size-8 items-center justify-center rounded-md bg-primary text-primary-foreground"
                    >
                        <AppLogoIcon class="size-5 fill-current" />
                    </span>
                    <span class="text-sm font-semibold">{{
                        page.props.name
                    }}</span>
                </Link>

                <!-- Nav desktop -->
                <nav
                    class="ml-4 hidden items-center gap-1 text-sm md:flex"
                >
                    <Link
                        v-for="item in navItems"
                        :key="item.label"
                        :href="item.href"
                        class="rounded-md px-3 py-1.5 text-muted-foreground transition-colors hover:bg-muted hover:text-foreground"
                    >
                        {{ item.label }}
                    </Link>
                </nav>

                <div class="ml-auto flex items-center gap-1">
                    <!-- Keranjang -->
                    <Button variant="ghost" size="sm" as-child>
                        <Link :href="'/checkout'" class="relative">
                            <ShoppingCart class="size-4" />
                            <span class="hidden sm:inline">Keranjang</span>
                            <span
                                v-if="cartCount > 0"
                                class="inline-flex size-5 items-center justify-center rounded-full bg-primary text-xs font-semibold text-primary-foreground"
                            >
                                {{ cartCount }}
                            </span>
                        </Link>
                    </Button>

                    <!-- Admin: shortcut ke panel -->
                    <Button
                        v-if="isAdmin"
                        variant="outline"
                        size="sm"
                        class="hidden md:inline-flex"
                        as-child
                    >
                        <Link :href="adminDashboard()">
                            <Package class="size-4" />
                            Dashboard Admin
                        </Link>
                    </Button>

                    <!-- Desktop: nama + logout customer -->
                    <template v-if="user && !isAdmin">
                        <span
                            class="hidden text-sm text-muted-foreground lg:inline"
                        >
                            {{ user.name }}
                        </span>
                        <Button variant="ghost" size="sm" class="hidden md:inline-flex" as-child>
                            <Link
                                :href="logout()"
                                @click="router.flushAll()"
                                as="button"
                            >
                                <LogOut class="size-4" />
                                Logout
                            </Link>
                        </Button>
                    </template>

                    <!-- Desktop: login/register guest -->
                    <template v-else-if="!user">
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
                                <BookOpen class="size-4" />
                                Daftar
                            </Link>
                        </Button>
                    </template>

                    <!-- Mobile: hamburger menu -->
                    <Sheet v-model:open="mobileMenuOpen">
                        <SheetTrigger as-child>
                            <Button
                                variant="ghost"
                                size="icon"
                                class="md:hidden"
                            >
                                <Menu class="size-5" />
                            </Button>
                        </SheetTrigger>
                        <SheetContent side="right" class="w-72">
                            <SheetHeader>
                                <SheetTitle>{{
                                    page.props.name
                                }}</SheetTitle>
                            </SheetHeader>
                            <div class="flex flex-col gap-1 px-2 py-4">
                                <Link
                                    v-for="item in navItems"
                                    :key="item.label"
                                    :href="item.href"
                                    class="rounded-md px-3 py-2.5 text-sm font-medium text-muted-foreground transition-colors hover:bg-muted hover:text-foreground"
                                    @click="mobileMenuOpen = false"
                                >
                                    {{ item.label }}
                                </Link>
                            </div>

                            <div class="mt-auto flex flex-col gap-2 border-t px-2 pt-4">
                                <template v-if="user">
                                    <p
                                        v-if="!isAdmin"
                                        class="px-3 text-sm text-muted-foreground"
                                    >
                                        {{ user.name }}
                                    </p>
                                    <Button
                                        v-if="isAdmin"
                                        variant="outline"
                                        size="sm"
                                        as-child
                                    >
                                        <Link :href="adminDashboard()">
                                            <Package class="size-4" />
                                            Dashboard Admin
                                        </Link>
                                    </Button>
                                    <Button variant="ghost" size="sm" as-child>
                                        <Link
                                            :href="logout()"
                                            @click="
                                                mobileMenuOpen = false;
                                                router.flushAll();
                                            "
                                            as="button"
                                        >
                                            <LogOut class="size-4" />
                                            Logout
                                        </Link>
                                    </Button>
                                </template>
                                <template v-else>
                                    <Button variant="ghost" size="sm" as-child>
                                        <Link
                                            :href="'/login'"
                                            @click="mobileMenuOpen = false"
                                            >Masuk</Link
                                        >
                                    </Button>
                                    <Button size="sm" as-child>
                                        <Link
                                            :href="'/register'"
                                            @click="mobileMenuOpen = false"
                                        >
                                            <BookOpen class="size-4" />
                                            Daftar
                                        </Link>
                                    </Button>
                                </template>
                            </div>
                        </SheetContent>
                    </Sheet>
                </div>
            </div>
        </header>

        <main class="mx-auto w-full max-w-6xl flex-1 px-4 py-8">
            <slot />
        </main>

        <footer class="border-t py-6">
            <p class="text-center text-sm text-muted-foreground">
                © {{ new Date().getFullYear() }} {{ page.props.name }} — Toko
                Buku Online
            </p>
        </footer>
    </div>
</template>

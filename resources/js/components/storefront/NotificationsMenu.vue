<script setup lang="ts">
/**
 * NotificationsMenu — dropdown pusat notifikasi untuk customer storefront.
 *
 * Trigger dikustomisasi lewat slot #trigger (ikon header desktop / item
 * bottom nav mobile); isi dropdown selalu sama: daftar notifikasi, badge
 * belum-dibaca, tandai dibaca satu & semua. Klik notifikasi membuka
 * /pesanan-saya.
 */
import { router, usePage } from '@inertiajs/vue3';
import { Bell, CheckCheck } from '@lucide/vue';
import { computed } from 'vue';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { timeAgoID } from '@/lib/date';
import {
    read as readNotification,
    readAll as notificationsReadAll,
} from '@/routes/notifications';

type NotificationItem = {
    id: string;
    message: string;
    read_at: string | null;
    created_at: string;
};

withDefaults(
    defineProps<{
        side?: 'top' | 'bottom' | 'left' | 'right';
        sideOffset?: number;
    }>(),
    { side: 'bottom', sideOffset: 8 },
);

const page = usePage();
const notifications = computed<NotificationItem[]>(
    () => (page.props.notifications ?? []) as NotificationItem[],
);
const notificationsCount = computed<number>(() =>
    Number(page.props.notificationsCount ?? 0),
);

function markNotificationRead(notification: NotificationItem): void {
    if (notification.read_at) {
        return;
    }

    router.post(
        readNotification(notification.id).url,
        {},
        {
            preserveScroll: true,
            onSuccess: () => {
                notification.read_at = new Date().toISOString();
            },
        },
    );
}

function markAllNotificationsRead(): void {
    router.post(notificationsReadAll().url, {}, { preserveScroll: true });
}

function openNotification(notification: NotificationItem): void {
    markNotificationRead(notification);
    router.visit('/pesanan-saya');
}
</script>

<template>
    <DropdownMenu>
        <DropdownMenuTrigger as-child>
            <slot name="trigger" :count="notificationsCount" />
        </DropdownMenuTrigger>
        <DropdownMenuContent
            align="end"
            :side="side"
            :side-offset="sideOffset"
            class="w-80"
        >
            <DropdownMenuLabel
                class="flex items-center justify-between font-normal"
            >
                <span class="text-sm font-medium">Notifikasi</span>
                <button
                    v-if="notificationsCount > 0"
                    type="button"
                    class="inline-flex items-center gap-1 text-xs text-muted-foreground transition-colors hover:text-foreground"
                    @click="markAllNotificationsRead"
                >
                    <CheckCheck class="size-3.5" />
                    Tandai dibaca
                </button>
            </DropdownMenuLabel>
            <DropdownMenuSeparator />
            <div
                v-if="notifications.length === 0"
                class="px-4 py-6 text-center"
            >
                <Bell class="mx-auto mb-2 size-6 text-muted-foreground/60" />
                <p class="text-sm text-muted-foreground">
                    Belum ada notifikasi.
                </p>
            </div>
            <div v-else class="max-h-80 overflow-y-auto">
                <button
                    v-for="notification in notifications"
                    :key="notification.id"
                    type="button"
                    class="flex w-full flex-col gap-0.5 border-b px-4 py-3 text-left transition-colors last:border-b-0 hover:bg-muted"
                    :class="
                        notification.read_at ? 'opacity-60' : 'bg-primary/5'
                    "
                    @click="openNotification(notification)"
                >
                    <span class="text-sm font-medium">
                        {{ notification.message }}
                    </span>
                    <span class="text-xs text-muted-foreground">
                        {{ timeAgoID(notification.created_at) }}
                    </span>
                </button>
            </div>
        </DropdownMenuContent>
    </DropdownMenu>
</template>

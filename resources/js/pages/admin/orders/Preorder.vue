<script setup lang="ts">
defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Beranda', href: '/admin/dashboard' },
            { title: 'Pesanan', href: '/admin/orders' },
            { title: 'Pre-Order' },
        ],
    },
});

import { Head } from '@inertiajs/vue3';
import {
    CalendarClock,
    MessageCircle,
    Send,
    ShoppingBag,
    Users,
} from '@lucide/vue';
import { computed, ref } from 'vue';
import { toast } from 'vue-sonner';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { formatDateID } from '@/lib/date';
import { waMeUrl } from '@/lib/phone';

type PreorderOrder = {
    id: string;
    no_order: string;
    nama_pembeli: string;
    no_hp: string | null;
    payment_status: string;
    total_qty: number;
    eta: string | null;
    books: string[];
    created_at: string;
    detail_url: string;
};

type WaitlistEntry = {
    id: string;
    nama: string;
    whatsapp_number: string | null;
    book: {
        judul: string;
        eta: string | null;
    };
    created_at: string;
};

type BookSummary = {
    judul: string;
    eta: string | null;
    qty_dipesan: number;
    jumlah_pembeli: number;
    jumlah_waitlist: number;
};

type ChatRecipient = {
    id: string;
    nama: string;
    nomor: string;
    waUrl: string;
    detail: string;
};

const props = defineProps<{
    orders: PreorderOrder[];
    waitlist: WaitlistEntry[];
    summary: BookSummary[];
    wa_template_ready: string;
    nama_lembaga: string;
}>();

/**
 * Isi placeholder {judul} & {keterangan} pada template WA. Placeholder lain
 * (tidak dikenal) dibiarkan apa adanya.
 */
function fillWaTemplate(
    template: string,
    values: Record<string, string>,
): string {
    return Object.entries(values).reduce(
        (text, [key, value]) => text.replaceAll(`{${key}}`, value),
        template,
    );
}

function orderWaLink(order: PreorderOrder): string | null {
    const judul =
        order.books.length > 0 ? order.books.join(', ') : 'yang Anda pesan';

    const message = fillWaTemplate(props.wa_template_ready, {
        judul,
        keterangan: `(pesanan ${order.no_order}) sudah tersedia dan siap diproses.`,
        lembaga: props.nama_lembaga,
    });

    return waMeUrl(order.no_hp, message);
}

function waitlistWaLink(entry: WaitlistEntry): string | null {
    const message = fillWaTemplate(props.wa_template_ready, {
        judul: entry.book.judul,
        keterangan:
            'yang Anda tunggu sudah tersedia dan dapat dipesan kembali.',
        lembaga: props.nama_lembaga,
    });

    return waMeUrl(entry.whatsapp_number, message);
}

function etaLabel(eta: string | null): string {
    return eta
        ? formatDateID(eta, {
              year: 'numeric',
              month: 'short',
              day: '2-digit',
          })
        : '(menyusul)';
}

// --- Bulk WhatsApp (wa.me) ---
const selectedIds = ref<Set<string>>(new Set());
const chatOpen = ref(false);
const chatIndex = ref(0);

const recipients = computed<ChatRecipient[]>(() => {
    const orders: ChatRecipient[] = props.orders.flatMap((order) => {
        const waUrl = orderWaLink(order);

        if (waUrl === null || order.no_hp === null) {
            return [];
        }

        return [
            {
                id: `order-${order.id}`,
                nama: order.nama_pembeli,
                nomor: order.no_hp,
                waUrl,
                detail: `${order.no_order} · ${order.books.join(', ')}`,
            },
        ];
    });

    const waiters: ChatRecipient[] = props.waitlist.flatMap((entry) => {
        const waUrl = waitlistWaLink(entry);

        if (waUrl === null || entry.whatsapp_number === null) {
            return [];
        }

        return [
            {
                id: `waitlist-${entry.id}`,
                nama: entry.nama,
                nomor: entry.whatsapp_number,
                waUrl,
                detail: entry.book.judul,
            },
        ];
    });

    return [...orders, ...waiters];
});

const chatQueue = computed<ChatRecipient[]>(() =>
    recipients.value.filter((recipient) => selectedIds.value.has(recipient.id)),
);

const currentRecipient = computed<ChatRecipient | null>(
    () => chatQueue.value[chatIndex.value] ?? null,
);

function toggleSelected(id: string, checked: boolean | 'indeterminate'): void {
    const next = new Set(selectedIds.value);

    if (checked === true) {
        next.add(id);
    } else {
        next.delete(id);
    }

    selectedIds.value = next;
}

function openChatWizard(): void {
    if (chatQueue.value.length === 0) {
        return;
    }

    chatIndex.value = 0;
    chatOpen.value = true;
}

function openCurrentChat(): void {
    if (currentRecipient.value) {
        window.open(currentRecipient.value.waUrl, '_blank');
    }

    advanceChat();
}

function advanceChat(): void {
    if (chatIndex.value + 1 >= chatQueue.value.length) {
        finishChat();

        return;
    }

    chatIndex.value += 1;
}

function finishChat(): void {
    chatOpen.value = false;
    selectedIds.value = new Set();
}

async function copyNumbers(): Promise<void> {
    const numbers = chatQueue.value.map((recipient) => recipient.nomor);

    if (numbers.length === 0) {
        return;
    }

    try {
        await navigator.clipboard.writeText(numbers.join(', '));
        toast.success(`${numbers.length} nomor WhatsApp disalin.`);
    } catch {
        toast.error('Gagal menyalin nomor.');
    }

    selectedIds.value = new Set();
}
</script>

<template>
    <Head title="Pesanan — Pre-Order" />

    <div class="flex flex-col gap-4 p-4 md:p-6">
        <div>
            <h1 class="text-xl font-semibold tracking-tight">Pre-Order</h1>
            <p class="text-sm text-muted-foreground">
                Pesanan buku new coming yang menunggu stok + pengguna yang
                menunggu tanpa membayar
            </p>
        </div>

        <!-- Toolbar bulk WhatsApp -->
        <div v-if="selectedIds.size > 0" class="flex items-center gap-2">
            <Button size="sm" @click="openChatWizard">
                <Send class="size-3.5" />
                Buka Chat ({{ chatQueue.length }})
            </Button>
            <Button variant="outline" size="sm" @click="copyNumbers">
                <MessageCircle class="size-3.5" />
                Salin Nomor ({{ chatQueue.length }})
            </Button>
            <Button
                variant="ghost"
                size="sm"
                class="text-muted-foreground"
                @click="selectedIds = new Set()"
            >
                Batal Pilih
            </Button>
        </div>

        <!-- Ringkasan per buku -->
        <Card v-if="summary.length > 0">
            <CardHeader>
                <CardTitle
                    class="flex items-center gap-2 text-base font-medium"
                >
                    <CalendarClock class="size-4" />
                    Ringkasan per Buku
                </CardTitle>
            </CardHeader>
            <CardContent>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr
                                class="border-b text-left text-xs text-muted-foreground"
                            >
                                <th class="pb-2 font-medium">Buku</th>
                                <th class="pb-2 font-medium">ETA</th>
                                <th class="pb-2 text-right font-medium">
                                    Qty Dipesan
                                </th>
                                <th class="pb-2 text-right font-medium">
                                    Pembeli
                                </th>
                                <th class="pb-2 text-right font-medium">
                                    Waitlist
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="row in summary"
                                :key="row.judul"
                                class="border-b border-border/60 last:border-0"
                            >
                                <td class="py-2.5 font-medium">
                                    {{ row.judul }}
                                </td>
                                <td class="py-2.5 text-muted-foreground">
                                    {{ etaLabel(row.eta) }}
                                </td>
                                <td class="py-2.5 text-right tabular-nums">
                                    {{ row.qty_dipesan }}
                                </td>
                                <td class="py-2.5 text-right tabular-nums">
                                    {{ row.jumlah_pembeli }}
                                </td>
                                <td class="py-2.5 text-right tabular-nums">
                                    {{ row.jumlah_waitlist }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </CardContent>
        </Card>

        <div class="grid gap-4 lg:grid-cols-2">
            <!-- Sudah bayar -->
            <Card>
                <CardHeader>
                    <CardTitle
                        class="flex items-center gap-2 text-base font-medium"
                    >
                        <ShoppingBag class="size-4" />
                        Sudah Bayar ({{ orders.length }})
                    </CardTitle>
                </CardHeader>
                <CardContent>
                    <div
                        v-if="orders.length === 0"
                        class="py-6 text-center text-sm text-muted-foreground"
                    >
                        Belum ada order pre-order yang menunggu.
                    </div>
                    <ul v-else class="flex flex-col gap-3">
                        <li
                            v-for="order in orders"
                            :key="order.id"
                            class="relative rounded-lg border p-3"
                        >
                            <Checkbox
                                class="absolute top-3 left-3"
                                :model-value="
                                    selectedIds.has(`order-${order.id}`)
                                "
                                :disabled="!orderWaLink(order)"
                                aria-label="Pilih untuk chat massal"
                                @update:model-value="
                                    (value) =>
                                        toggleSelected(
                                            `order-${order.id}`,
                                            value,
                                        )
                                "
                            />
                            <div
                                class="flex items-start justify-between gap-2 pl-7"
                            >
                                <div class="min-w-0">
                                    <p class="truncate text-sm font-medium">
                                        {{ order.nama_pembeli }}
                                    </p>
                                    <a
                                        :href="order.detail_url"
                                        class="font-mono text-sm font-semibold hover:underline"
                                    >
                                        {{ order.no_order }}
                                    </a>
                                    <p
                                        class="truncate text-xs text-muted-foreground"
                                    >
                                        {{ order.books.join(', ') }}
                                    </p>
                                    <p
                                        class="mt-1 text-xs text-muted-foreground"
                                    >
                                        {{ order.total_qty }} eksemplar · ETA
                                        {{ etaLabel(order.eta) }} ·
                                        {{ formatDateID(order.created_at) }}
                                    </p>
                                </div>
                                <div
                                    class="flex shrink-0 flex-col items-end gap-1.5"
                                >
                                    <Badge
                                        variant="outline"
                                        :class="
                                            order.payment_status === 'lunas'
                                                ? 'border-green-200 bg-green-100 text-green-800'
                                                : 'border-amber-200 bg-amber-100 text-amber-800'
                                        "
                                    >
                                        {{
                                            order.payment_status === 'lunas'
                                                ? 'Lunas'
                                                : 'Menunggu Bayar'
                                        }}
                                    </Badge>
                                    <Button
                                        v-if="orderWaLink(order)"
                                        variant="outline"
                                        size="sm"
                                        as-child
                                    >
                                        <a
                                            :href="orderWaLink(order)!"
                                            target="_blank"
                                            rel="noopener"
                                        >
                                            <MessageCircle class="size-3.5" />
                                            Chat WhatsApp
                                        </a>
                                    </Button>
                                </div>
                            </div>
                        </li>
                    </ul>
                </CardContent>
            </Card>

            <!-- Waitlist (belum bayar) -->
            <Card>
                <CardHeader>
                    <CardTitle
                        class="flex items-center gap-2 text-base font-medium"
                    >
                        <Users class="size-4" />
                        Belum Bayar — Menunggu Info ({{ waitlist.length }})
                    </CardTitle>
                </CardHeader>
                <CardContent>
                    <div
                        v-if="waitlist.length === 0"
                        class="py-6 text-center text-sm text-muted-foreground"
                    >
                        Belum ada yang menunggu tanpa membayar.
                    </div>
                    <ul v-else class="flex flex-col gap-3">
                        <li
                            v-for="entry in waitlist"
                            :key="entry.id"
                            class="relative rounded-lg border p-3"
                        >
                            <div
                                class="flex items-start justify-between gap-2 pl-7"
                            >
                                <div class="min-w-0">
                                    <p class="truncate text-sm font-medium">
                                        {{ entry.nama }}
                                    </p>
                                    <p
                                        class="truncate text-xs text-muted-foreground"
                                    >
                                        {{ entry.book.judul }}
                                    </p>
                                    <p
                                        class="mt-1 text-xs text-muted-foreground"
                                    >
                                        ETA {{ etaLabel(entry.book.eta) }} ·
                                        {{ formatDateID(entry.created_at) }}
                                    </p>
                                </div>
                                <Button
                                    v-if="waitlistWaLink(entry)"
                                    variant="outline"
                                    size="sm"
                                    as-child
                                >
                                    <a
                                        :href="waitlistWaLink(entry)!"
                                        target="_blank"
                                        rel="noopener"
                                    >
                                        <MessageCircle class="size-3.5" />
                                        Chat WhatsApp
                                    </a>
                                </Button>
                            </div>
                            <Checkbox
                                class="absolute top-3 left-3"
                                :model-value="
                                    selectedIds.has(`waitlist-${entry.id}`)
                                "
                                :disabled="!waitlistWaLink(entry)"
                                aria-label="Pilih untuk chat massal"
                                @update:model-value="
                                    (value) =>
                                        toggleSelected(
                                            `waitlist-${entry.id}`,
                                            value,
                                        )
                                "
                            />
                        </li>
                    </ul>
                </CardContent>
            </Card>
        </div>
    </div>

    <!-- Wizard buka chat massal -->
    <Dialog v-model:open="chatOpen">
        <DialogContent class="sm:max-w-md">
            <DialogHeader>
                <DialogTitle>Buka Chat WhatsApp</DialogTitle>
                <DialogDescription>
                    Chat {{ chatIndex + 1 }} dari {{ chatQueue.length }} — buka
                    satu per satu agar tidak diblokir browser.
                </DialogDescription>
            </DialogHeader>

            <div
                v-if="currentRecipient"
                class="grid gap-1 rounded-lg border p-3"
            >
                <p class="text-sm font-medium">
                    {{ currentRecipient.nama }}
                </p>
                <p class="text-xs text-muted-foreground">
                    {{ currentRecipient.nomor }}
                </p>
                <p class="truncate text-xs text-muted-foreground">
                    {{ currentRecipient.detail }}
                </p>
            </div>

            <DialogFooter class="flex-col-reverse sm:flex-row">
                <Button variant="outline" type="button" @click="finishChat">
                    Tutup
                </Button>
                <Button
                    variant="ghost"
                    type="button"
                    class="text-muted-foreground"
                    @click="advanceChat"
                >
                    Lewati
                </Button>
                <Button type="button" @click="openCurrentChat">
                    <MessageCircle class="size-3.5" />
                    Buka Chat
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>

<script setup lang="ts">
/**
 * Halaman Tentang proto-d — /pcd/tentang. Data nyata dari pengaturan
 * Lembaga (Setting) + rekening bank aktif.
 */
import { Head } from '@inertiajs/vue3';
import { Clock, Landmark, Mail, MapPin, Phone } from '@lucide/vue';
import { computed } from 'vue';
import StorefrontPcdLayout from '@/layouts/customer/StorefrontPcdLayout.vue';

type BankAccount = {
    id: string;
    bank_name: string;
    account_number: string;
    account_holder: string;
};

const props = defineProps<{
    nama_lembaga: string;
    logo_url: string;
    tagline: string;
    deskripsi: string;
    visi: string;
    misi: string;
    keamanan: string;
    syarat: string;
    telepon: string;
    email: string;
    jam_operasional: string;
    alamat: string;
    bankAccounts: BankAccount[];
}>();

defineOptions({ layout: StorefrontPcdLayout });

const misiList = computed(() =>
    props.misi
        .split('\n')
        .map((line) => line.trim())
        .filter(Boolean),
);

const contact = computed(() =>
    [
        { icon: Phone, label: 'Telepon / WhatsApp', value: props.telepon },
        { icon: Mail, label: 'Email', value: props.email },
        { icon: MapPin, label: 'Alamat', value: props.alamat },
        { icon: Clock, label: 'Jam Operasional', value: props.jam_operasional },
    ].filter((c) => c.value),
);
</script>

<template>
    <Head :title="`Tentang — ${nama_lembaga || 'Pustaka Cahaya Peradaban'}`" />

    <div class="mx-auto max-w-2xl px-4 pt-14 pb-24 md:pt-20 md:pb-32">
        <p
            class="text-center text-xs font-semibold tracking-[0.16em] text-flat-primary uppercase"
        >
            Tentang
        </p>
        <h1
            class="mt-3 text-center text-3xl font-extrabold tracking-tight md:text-4xl"
        >
            {{ nama_lembaga || 'Pustaka Cahaya Peradaban' }}
        </h1>
        <p
            v-if="tagline"
            class="mt-3 text-center text-[15px] leading-relaxed text-gray-500"
        >
            {{ tagline }}
        </p>

        <div
            v-if="deskripsi"
            class="mt-10 rounded-lg bg-flat-muted p-6 text-[17px] leading-[1.8] whitespace-pre-line text-flat-ink/90 md:p-8"
        >
            {{ deskripsi }}
        </div>

        <div
            v-if="visi || misiList.length > 0"
            class="mt-12 border-t-2 border-flat-border pt-10"
        >
            <h2 class="text-2xl font-extrabold tracking-tight">
                Visi &amp; Misi
            </h2>
            <p
                v-if="visi"
                class="mt-4 text-sm leading-[1.7] whitespace-pre-line text-gray-500"
            >
                {{ visi }}
            </p>
            <ul v-if="misiList.length > 0" class="mt-4 space-y-2.5">
                <li
                    v-for="misi in misiList"
                    :key="misi"
                    class="flex items-start gap-3 text-sm leading-[1.7] text-gray-500"
                >
                    <span
                        class="mt-2 size-2 shrink-0 rounded-full bg-flat-primary"
                        aria-hidden="true"
                    ></span>
                    {{ misi }}
                </li>
            </ul>
        </div>

        <div
            v-if="contact.length > 0"
            class="mt-12 border-t-2 border-flat-border pt-10"
        >
            <h2 class="text-2xl font-extrabold tracking-tight">
                Hubungi Kami
            </h2>
            <dl class="mt-5 space-y-4">
                <div
                    v-for="item in contact"
                    :key="item.label"
                    class="flex items-start gap-4 text-sm"
                >
                    <item.icon
                        class="mt-0.5 size-4 shrink-0 text-flat-primary"
                        aria-hidden="true"
                    />
                    <dt class="w-32 shrink-0 text-gray-500">
                        {{ item.label }}
                    </dt>
                    <dd class="min-w-0 font-medium whitespace-pre-line">
                        {{ item.value }}
                    </dd>
                </div>
            </dl>
        </div>

        <div
            v-if="bankAccounts.length > 0"
            class="mt-12 border-t-2 border-flat-border pt-10"
        >
            <h2
                class="flex items-center gap-2 text-2xl font-extrabold tracking-tight"
            >
                <Landmark class="size-5 text-flat-primary" aria-hidden="true" />
                Rekening Pembayaran
            </h2>
            <ul class="mt-5 space-y-3">
                <li
                    v-for="bank in bankAccounts"
                    :key="bank.id"
                    class="flex flex-wrap items-center justify-between gap-2 rounded-lg bg-flat-muted px-4 py-3 text-sm"
                >
                    <span class="font-bold">{{ bank.bank_name }}</span>
                    <span class="font-mono text-xs tracking-wide">{{
                        bank.account_number
                    }}</span>
                    <span class="text-gray-500"
                        >a.n. {{ bank.account_holder }}</span
                    >
                </li>
            </ul>
        </div>

        <p
            v-if="keamanan"
            class="mt-12 border-t-2 border-flat-border pt-8 text-xs leading-relaxed whitespace-pre-line text-gray-500"
        >
            {{ keamanan }}
        </p>
    </div>
</template>

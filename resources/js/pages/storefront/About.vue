<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { Building2, Clock, Landmark, Mail, Phone } from '@lucide/vue';
import CustomerLayout from '@/layouts/customer/CustomerLayout.vue';

type BankAccount = {
    id: string;
    bank_name: string;
    account_number: string;
    account_holder: string;
};

defineOptions({
    layout: CustomerLayout,
});

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

const misiList = props.misi
    .split('\n')
    .map((line) => line.trim())
    .filter(Boolean);

const syaratList = props.syarat
    .split('\n')
    .map((line) => line.trim())
    .filter(Boolean);

const sectionClass = 'rounded-xl border p-6';
const headingClass = 'font-semibold';
const bodyClass = 'mt-2 text-sm leading-relaxed text-muted-foreground';
</script>

<template>
    <Head title="Tentang Kami">
        <meta
            name="description"
            :content="
                props.tagline ||
                'Tentang Pustaka Cahaya Peradaban — cerita, visi, misi, dan kontak toko buku kami.'
            "
        />
    </Head>

    <div class="mx-auto flex max-w-3xl flex-col gap-8">
        <div class="flex flex-col items-center gap-3 text-center">
            <img
                v-if="props.logo_url"
                :src="props.logo_url"
                :alt="props.nama_lembaga || 'Logo'"
                class="h-20 w-auto object-contain"
            />
            <div>
                <h1 class="text-3xl font-bold tracking-tight">Tentang Kami</h1>
                <p class="mt-2 text-muted-foreground">
                    {{
                        props.tagline ||
                        'Toko buku yang berfokus menyediakan bacaan berkualitas untuk semua kalangan.'
                    }}
                </p>
            </div>
        </div>

        <div class="flex flex-col gap-4">
            <section v-if="props.deskripsi" :class="sectionClass">
                <h2 :class="headingClass">Cerita Kami</h2>
                <p :class="bodyClass">{{ props.deskripsi }}</p>
            </section>

            <section v-if="props.visi" :class="sectionClass">
                <h2 :class="headingClass">Visi</h2>
                <p :class="bodyClass">{{ props.visi }}</p>
            </section>

            <section v-if="misiList.length" :class="sectionClass">
                <h2 :class="headingClass">Misi</h2>
                <ul :class="[bodyClass, 'list-disc space-y-1 pl-5']">
                    <li v-for="(item, i) in misiList" :key="i">
                        {{ item }}
                    </li>
                </ul>
            </section>

            <section v-if="props.keamanan" :class="sectionClass">
                <h2 :class="headingClass">Keamanan & Kepercayaan</h2>
                <p :class="bodyClass">{{ props.keamanan }}</p>
            </section>

            <section v-if="syaratList.length" :class="sectionClass">
                <h2 :class="headingClass">Syarat & Prasyarat</h2>
                <ul :class="[bodyClass, 'list-disc space-y-1 pl-5']">
                    <li v-for="(item, i) in syaratList" :key="i">
                        {{ item }}
                    </li>
                </ul>
            </section>

            <section v-if="props.bankAccounts.length" :class="sectionClass">
                <h2 :class="headingClass">Rekening Bank</h2>
                <div class="mt-2 grid gap-1 text-sm text-muted-foreground">
                    <p
                        v-for="account in props.bankAccounts"
                        :key="account.id"
                        class="flex items-center gap-2"
                    >
                        <Landmark class="size-4 shrink-0" />
                        <span class="font-medium text-foreground">
                            {{ account.bank_name }}
                        </span>
                        <span class="font-mono">{{
                            account.account_number
                        }}</span>
                        <span>a.n. {{ account.account_holder }}</span>
                    </p>
                </div>
            </section>

            <section :class="sectionClass">
                <h2 :class="headingClass">Kontak</h2>
                <div class="mt-2 grid gap-1 text-sm text-muted-foreground">
                    <p v-if="props.telepon" class="flex items-center gap-2">
                        <Phone class="size-4 shrink-0" />
                        <span class="font-medium text-foreground">
                            {{ props.telepon }}
                        </span>
                    </p>
                    <p v-if="props.email" class="flex items-center gap-2">
                        <Mail class="size-4 shrink-0" />
                        <span class="font-medium text-foreground">
                            {{ props.email }}
                        </span>
                    </p>
                    <p v-if="props.alamat" class="flex items-center gap-2">
                        <Building2 class="size-4 shrink-0" />
                        <span class="font-medium text-foreground">
                            {{ props.alamat }}
                        </span>
                    </p>
                    <p
                        v-if="props.jam_operasional"
                        class="flex items-center gap-2"
                    >
                        <Clock class="size-4 shrink-0" />
                        <span class="font-medium text-foreground">
                            {{ props.jam_operasional }}
                        </span>
                    </p>
                </div>
            </section>
        </div>
    </div>
</template>

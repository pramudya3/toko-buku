<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { BookOpen } from '@lucide/vue';
import { computed } from 'vue';

const props = defineProps<{
    status: number;
    message?: string;
}>();

const title = computed(() => {
    if (props.status === 404) {
        return 'Halaman Tidak Ditemukan';
    }

    if (props.status === 403) {
        return 'Akses Ditolak';
    }

    if (props.status === 500) {
        return 'Kesalahan Server';
    }

    return `Kesalahan ${props.status}`;
});

const description = computed(() => {
    if (props.message && props.message !== '') {
        return props.message;
    }

    if (props.status === 404) {
        return 'Halaman yang Anda cari tidak tersedia atau telah dipindahkan.';
    }

    return 'Terjadi kesalahan. Silakan coba kembali.';
});
</script>

<template>
    <Head :title="title" />

    <div
        class="flex min-h-screen flex-col items-center justify-center bg-background p-6 text-center"
    >
        <BookOpen
            class="mb-6 h-16 w-16 text-muted-foreground"
            aria-hidden="true"
        />
        <h1 class="text-3xl font-bold tracking-tight">
            {{ status }} — {{ title }}
        </h1>
        <p class="mt-2 max-w-md text-sm text-muted-foreground">
            {{ description }}
        </p>
        <Link
            href="/"
            class="mt-6 inline-flex items-center justify-center rounded-md bg-primary px-4 py-2 text-sm font-medium text-primary-foreground hover:bg-primary/90"
        >
            Kembali ke Beranda
        </Link>
    </div>
</template>

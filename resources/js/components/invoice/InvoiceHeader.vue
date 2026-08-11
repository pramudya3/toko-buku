<script setup lang="ts">
// Kop nota: identitas toko (kiri) + judul dokumen, nomor & tanggal (kanan).

export type InvoiceStore = {
    nama: string;
    logo_url: string;
    alamat: string;
    npwp: string;
    telepon: string;
    email: string;
};

defineProps<{
    store: InvoiceStore;
    title: string;
    no: string;
    tanggal: string;
    subtitle?: string;
}>();
</script>

<template>
    <div
        class="flex items-start justify-between gap-6 border-b-2 border-slate-900 pb-4"
    >
        <div class="flex items-center gap-3">
            <img
                v-if="store.logo_url"
                :src="store.logo_url"
                alt="Logo"
                class="h-14 w-14 object-contain"
            />
            <div>
                <p class="text-lg leading-tight font-bold">{{ store.nama }}</p>
                <p v-if="store.alamat" class="text-xs text-slate-600">
                    {{ store.alamat }}
                </p>
                <p class="text-xs text-slate-600">
                    {{
                        [store.telepon, store.email].filter(Boolean).join(' • ')
                    }}
                </p>
                <p v-if="store.npwp" class="text-xs text-slate-600">
                    NPWP: {{ store.npwp }}
                </p>
            </div>
        </div>
        <div class="text-right">
            <p class="text-xl font-bold tracking-wide uppercase">{{ title }}</p>
            <p v-if="subtitle" class="text-xs text-slate-600">{{ subtitle }}</p>
            <p class="mt-2 font-medium">No: {{ no }}</p>
            <p class="text-xs text-slate-600">Tanggal: {{ tanggal }}</p>
        </div>
    </div>
</template>

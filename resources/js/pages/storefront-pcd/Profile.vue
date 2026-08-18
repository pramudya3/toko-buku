<script setup lang="ts">
/**
 * Profil storefront proto-d — /pcd/profil. Desain Flat.
 *
 * Akun (nama & email) + alamat lengkap untuk checkout cepat + logout.
 * Backend memakai controller & validasi yang sama dengan halaman settings.
 */
import { Form, Head, router, usePage } from '@inertiajs/vue3';
import { LogOut, MapPin, ShieldCheck, UserRound } from '@lucide/vue';
import { computed } from 'vue';
import ProfilePcdController from '@/actions/App/Http/Controllers/ProfilePcdController';
import AddressFields from '@/components/AddressFields.vue';
import InputError from '@/components/InputError.vue';
import StorefrontPcdLayout from '@/layouts/customer/StorefrontPcdLayout.vue';
import { logout } from '@/routes';

defineProps<{
    mustVerifyEmail: boolean;
    status?: string | null;
}>();

defineOptions({
    layout: StorefrontPcdLayout,
});

const page = usePage();
const user = computed(() => page.props.auth.user);

const addressValue = computed(() => ({
    provinsi: user.value?.provinsi ?? '',
    kabupaten_kota: user.value?.kabupaten_kota ?? '',
    kecamatan: user.value?.kecamatan ?? '',
    kelurahan: user.value?.kelurahan ?? '',
    village_code: user.value?.village_code ?? '',
    kode_pos: user.value?.kode_pos ?? '',
    alamat: user.value?.alamat ?? '',
}));

function handleLogout(): void {
    router.flushAll();
    router.post(logout().url);
}
</script>

<template>
    <Head title="Profil — Pustaka Cahaya Peradaban" />

    <div class="mx-auto max-w-3xl px-4 pt-14 pb-24 md:px-6 md:pt-20 md:pb-32">
        <p
            class="text-xs font-semibold tracking-[0.16em] text-flat-primary uppercase"
        >
            Profil
        </p>
        <h1 class="mt-3 text-3xl font-extrabold tracking-tight md:text-4xl">
            Profil Saya
        </h1>
        <p class="mt-3 text-sm text-gray-500">
            Kelola data akun dan alamat untuk checkout yang lebih cepat.
        </p>

        <div class="mt-10 rounded-lg bg-flat-muted p-6 md:p-8">
            <h2 class="flex items-center gap-2 text-lg font-extrabold tracking-tight">
                <UserRound class="size-5 text-flat-primary" aria-hidden="true" />
                Akun
            </h2>
            <p class="mt-1 text-sm text-gray-500">
                Update nama dan email akun Anda.
            </p>

            <Form
                v-bind="ProfilePcdController.update.form()"
                class="mt-6 space-y-5"
                v-slot="{ errors, processing }"
            >
                <div>
                    <label for="pcd-profile-name" class="text-sm font-medium"
                        >Nama lengkap</label
                    >
                    <input
                        id="pcd-profile-name"
                        name="name"
                        :default-value="user.name"
                        required
                        autocomplete="name"
                        placeholder="Nama lengkap"
                        class="mt-2 w-full rounded-md border-2 border-transparent bg-white px-4 py-3 text-sm transition-colors outline-none placeholder:text-gray-400 focus:border-flat-primary"
                    />
                    <InputError class="mt-2" :message="errors.name" />
                </div>

                <div>
                    <label for="pcd-profile-email" class="text-sm font-medium"
                        >Email</label
                    >
                    <input
                        id="pcd-profile-email"
                        type="email"
                        name="email"
                        :default-value="user.email"
                        required
                        autocomplete="username"
                        placeholder="Email address"
                        class="mt-2 w-full rounded-md border-2 border-transparent bg-white px-4 py-3 text-sm transition-colors outline-none placeholder:text-gray-400 focus:border-flat-primary"
                    />
                    <InputError class="mt-2" :message="errors.email" />
                </div>

                <button
                    type="submit"
                    :disabled="processing"
                    class="inline-flex min-h-12 items-center justify-center rounded-md bg-flat-primary px-6 text-sm font-semibold text-white transition-all duration-200 hover:scale-105 hover:bg-flat-primary-dark focus-visible:ring-2 focus-visible:ring-flat-primary focus-visible:ring-offset-2 focus-visible:outline-none disabled:opacity-60"
                >
                    Simpan Perubahan
                </button>
            </Form>
        </div>

        <div
            id="alamat"
            class="mt-6 scroll-mt-24 rounded-lg bg-flat-muted p-6 md:p-8"
        >
            <h2 class="flex items-center gap-2 text-lg font-extrabold tracking-tight">
                <MapPin class="size-5 text-flat-primary" aria-hidden="true" />
                Alamat
            </h2>
            <p class="mt-1 text-sm text-gray-500">
                Simpan alamat lengkap untuk checkout lebih cepat.
            </p>

            <Form
                v-bind="ProfilePcdController.updateAddress.form()"
                class="mt-6 space-y-5"
                v-slot="{ errors, processing }"
            >
                <AddressFields
                    :endpoint="'public'"
                    :model-value="addressValue"
                />

                <InputError :message="errors.alamat" />

                <button
                    type="submit"
                    :disabled="processing"
                    class="inline-flex min-h-12 items-center justify-center rounded-md bg-flat-primary px-6 text-sm font-semibold text-white transition-all duration-200 hover:scale-105 hover:bg-flat-primary-dark focus-visible:ring-2 focus-visible:ring-flat-primary focus-visible:ring-offset-2 focus-visible:outline-none disabled:opacity-60"
                >
                    Simpan Alamat
                </button>
            </Form>
        </div>

        <div class="mt-6 rounded-lg bg-flat-muted p-6 md:p-8">
            <h2 class="flex items-center gap-2 text-lg font-extrabold tracking-tight">
                <ShieldCheck class="size-5 text-flat-primary" aria-hidden="true" />
                Keamanan
            </h2>
            <p class="mt-1 text-sm text-gray-500">
                Ubah kata sandi atau keluar dari akun ini.
            </p>
            <div class="mt-6 flex flex-wrap gap-3">
                <a
                    href="/settings/security"
                    class="inline-flex min-h-11 items-center justify-center rounded-md border-2 border-flat-border bg-white px-5 text-sm font-semibold transition-all duration-200 hover:bg-flat-muted"
                >
                    Ubah Kata Sandi
                </a>
                <button
                    type="button"
                    class="inline-flex min-h-11 items-center justify-center gap-2 rounded-md border-2 border-red-200 bg-white px-5 text-sm font-semibold text-red-700 transition-all duration-200 hover:bg-red-50"
                    @click="handleLogout"
                >
                    <LogOut class="size-4" aria-hidden="true" />
                    Logout
                </button>
            </div>
        </div>
    </div>
</template>

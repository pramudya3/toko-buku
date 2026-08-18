<script setup lang="ts">
/**
 * Profil storefront utama — /profil. Desain editorial.
 *
 * Akun (nama & email) + alamat lengkap untuk checkout cepat + logout.
 * Backend memakai controller & validasi yang sama dengan halaman settings.
 */
import { Form, Head, router, usePage } from '@inertiajs/vue3';
import { LogOut, MapPin, ShieldCheck, UserRound } from '@lucide/vue';
import { computed } from 'vue';
import StorefrontProfileController from '@/actions/App/Http/Controllers/StorefrontProfileController';
import AddressFields from '@/components/AddressFields.vue';
import InputError from '@/components/InputError.vue';
import EditorialLayout from '@/layouts/customer/EditorialLayout.vue';
import { logout } from '@/routes';

defineProps<{
    mustVerifyEmail: boolean;
    status?: string | null;
}>();

defineOptions({
    layout: EditorialLayout,
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
    <Head title="Profil" />

    <div class="mx-auto max-w-6xl px-4 pb-24 md:px-6 md:pb-32">
        <div class="mx-auto max-w-3xl">
            <div
                class="mt-10 rounded-xl border border-article-border bg-article-surface p-6 md:p-8"
            >
                <h2
                    class="flex items-center gap-2 text-lg font-bold tracking-tight text-article-ink"
                >
                    <UserRound
                        class="size-5 text-article-primary"
                        aria-hidden="true"
                    />
                    Akun
                </h2>
                <p class="mt-1 text-sm text-article-muted">
                    Update nama dan email akun Anda.
                </p>

                <Form
                    v-bind="StorefrontProfileController.update.form()"
                    class="mt-6 space-y-5"
                    v-slot="{ errors, processing }"
                >
                    <div>
                        <label
                            for="profile-name"
                            class="text-sm font-medium text-article-ink"
                            >Nama lengkap</label
                        >
                        <input
                            id="profile-name"
                            name="name"
                            :value="user.name"
                            required
                            autocomplete="name"
                            placeholder="Nama lengkap"
                            class="mt-2 w-full rounded-lg border border-article-border bg-article-surface px-4 py-3 text-sm text-article-ink transition-colors outline-none placeholder:text-article-muted focus:border-article-primary focus:ring-2 focus:ring-article-primary/20"
                        />
                        <InputError class="mt-2" :message="errors.name" />
                    </div>

                    <div>
                        <label
                            for="profile-email"
                            class="text-sm font-medium text-article-ink"
                            >Email</label
                        >
                        <input
                            id="profile-email"
                            type="email"
                            name="email"
                            :value="user.email"
                            required
                            autocomplete="username"
                            placeholder="Email address"
                            class="mt-2 w-full rounded-lg border border-article-border bg-article-surface px-4 py-3 text-sm text-article-ink transition-colors outline-none placeholder:text-article-muted focus:border-article-primary focus:ring-2 focus:ring-article-primary/20"
                        />
                        <InputError class="mt-2" :message="errors.email" />
                    </div>

                    <button
                        type="submit"
                        :disabled="processing"
                        class="inline-flex min-h-11 items-center justify-center rounded-lg bg-article-primary px-6 text-sm font-semibold text-white transition-colors hover:bg-article-primary-dark focus-visible:ring-2 focus-visible:ring-article-primary focus-visible:ring-offset-2 focus-visible:outline-none disabled:opacity-60"
                    >
                        Simpan Perubahan
                    </button>
                </Form>
            </div>

            <div
                id="alamat"
                class="mt-6 scroll-mt-24 rounded-xl border border-article-border bg-article-surface p-6 md:p-8"
            >
                <h2
                    class="flex items-center gap-2 text-lg font-bold tracking-tight text-article-ink"
                >
                    <MapPin
                        class="size-5 text-article-primary"
                        aria-hidden="true"
                    />
                    Alamat
                </h2>
                <p class="mt-1 text-sm text-article-muted">
                    Simpan alamat lengkap untuk checkout lebih cepat.
                </p>

                <Form
                    v-bind="StorefrontProfileController.updateAddress.form()"
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
                        class="inline-flex min-h-11 items-center justify-center rounded-lg bg-article-primary px-6 text-sm font-semibold text-white transition-colors hover:bg-article-primary-dark focus-visible:ring-2 focus-visible:ring-article-primary focus-visible:ring-offset-2 focus-visible:outline-none disabled:opacity-60"
                    >
                        Simpan Alamat
                    </button>
                </Form>
            </div>

            <div
                class="mt-6 rounded-xl border border-article-border bg-article-surface p-6 md:p-8"
            >
                <h2
                    class="flex items-center gap-2 text-lg font-bold tracking-tight text-article-ink"
                >
                    <ShieldCheck
                        class="size-5 text-article-primary"
                        aria-hidden="true"
                    />
                    Keamanan
                </h2>
                <p class="mt-1 text-sm text-article-muted">
                    Ubah kata sandi atau keluar dari akun ini.
                </p>
                <div class="mt-6 flex flex-wrap gap-3">
                    <a
                        href="/settings/security"
                        class="inline-flex min-h-11 items-center justify-center rounded-lg border border-article-border bg-article-surface px-5 text-sm font-semibold text-article-ink transition-colors hover:bg-article-border/50"
                    >
                        Ubah Kata Sandi
                    </a>
                    <button
                        type="button"
                        class="inline-flex min-h-11 items-center justify-center gap-2 rounded-lg border border-red-200 bg-article-surface px-5 text-sm font-semibold text-red-700 transition-colors hover:bg-red-50"
                        @click="handleLogout"
                    >
                        <LogOut class="size-4" aria-hidden="true" />
                        Logout
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>

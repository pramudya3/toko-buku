<script setup lang="ts">
import { Form, Head, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import AddressController from '@/actions/App/Http/Controllers/Settings/AddressController';
import AddressFields from '@/components/AddressFields.vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import SettingsTabs from '@/components/SettingsTabs.vue';
import { Button } from '@/components/ui/button';
import { edit } from '@/routes/address';

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Address settings',
                href: edit(),
            },
        ],
    },
});

const page = usePage();
const user = computed(() => page.props.auth.user);
</script>

<template>
    <Head title="Address settings" />

    <h1 class="sr-only">Address settings</h1>

    <div class="flex flex-col space-y-6">
        <SettingsTabs />

        <Heading
            variant="small"
            title="Alamat"
            description="Simpan alamat lengkap untuk checkout lebih cepat"
        />

        <Form
            v-bind="AddressController.update.form()"
            class="space-y-6"
            v-slot="{ errors, processing }"
        >
            <AddressFields
                :endpoint="'public'"
                :model-value="{
                    provinsi: user.provinsi ?? '',
                    kabupaten_kota: user.kabupaten_kota ?? '',
                    kecamatan: user.kecamatan ?? '',
                    kode_pos: user.kode_pos ?? '',
                    alamat: user.alamat ?? '',
                }"
            />

            <InputError :message="errors.alamat" />

            <div class="flex items-center gap-4">
                <Button :disabled="processing" data-test="update-address-button"
                    >Save</Button
                >
            </div>
        </Form>
    </div>
</template>

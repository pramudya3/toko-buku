<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import AppLayout from '@/layouts/AppLayout.vue';
import EditorialLayout from '@/layouts/customer/EditorialLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';

const page = usePage();
const isAdmin = computed(() => page.props.auth?.user?.is_admin === true);
</script>

<template>
    <!-- Admin: sidebar + nav settings seperti biasa -->
    <AppLayout v-if="isAdmin">
        <SettingsLayout>
            <slot />
        </SettingsLayout>
    </AppLayout>

    <!-- Customer: header storefront editorial, tanpa sidebar -->
    <EditorialLayout v-else>
        <slot />
    </EditorialLayout>
</template>

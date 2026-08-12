<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import { Button } from '@/components/ui/button';
import { useCurrentUrl } from '@/composables/useCurrentUrl';
import { edit as editAddress } from '@/routes/address';
import { edit as editProfile } from '@/routes/profile';

const page = usePage();
const isAdmin = computed(() => page.props.auth?.user?.is_admin === true);
const { isCurrentUrl } = useCurrentUrl();

const tabs = [
    { label: 'Alamat', href: editAddress() },
    { label: 'Akun', href: editProfile() },
];
</script>

<template>
    <div v-if="!isAdmin" class="flex gap-2">
        <Button
            v-for="tab in tabs"
            :key="tab.label"
            variant="ghost"
            size="sm"
            :class="isCurrentUrl(tab.href) ? 'bg-muted' : ''"
            as-child
        >
            <Link :href="tab.href">{{ tab.label }}</Link>
        </Button>
    </div>
</template>

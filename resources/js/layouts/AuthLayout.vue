<script setup lang="ts">
import AuthLayout from '@/layouts/auth/AuthSimpleLayout.vue';

const {
    title = '',
    description = '',
    showLogo = true,
} = defineProps<{
    title?: string;
    description?: string;
    showLogo?: boolean;
}>();
</script>

<template>
    <AuthLayout :title="title" :description="description" :show-logo="showLogo">
        <slot />
    </AuthLayout>
</template>

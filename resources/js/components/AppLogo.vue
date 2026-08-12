<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import AppLogoIcon from '@/components/AppLogoIcon.vue';

const page = usePage();
const name = computed(() => page.props.storeName ?? page.props.name);
const logoUrl = computed(() => page.props.storeLogoUrl ?? '');
</script>

<template>
    <img
        v-if="logoUrl"
        :src="logoUrl"
        :alt="name"
        class="h-8 w-auto object-contain"
    />
    <AppLogoIcon v-else class="size-5 fill-current" />
    <div class="ml-1 grid flex-1 text-left text-sm">
        <span class="mb-0.5 truncate leading-tight font-semibold">{{ name }}</span>
    </div>
</template>

<script setup lang="ts">
import { computed } from 'vue';
import { Badge } from '@/components/ui/badge';

type Props = {
    variant?: 'success' | 'warning' | 'danger' | 'info' | 'neutral';
    label?: string;
};

const props = withDefaults(defineProps<Props>(), {
    variant: 'neutral',
});

const classes = computed(() => {
    switch (props.variant) {
        case 'success':
            return 'bg-green-100 text-green-800 border-green-200';
        case 'warning':
            return 'bg-amber-100 text-amber-800 border-amber-200';
        case 'danger':
            return 'bg-red-100 text-red-700 border-red-200';
        case 'info':
            return 'bg-blue-100 text-blue-700 border-blue-200';
        default:
            return 'bg-gray-100 text-gray-600 border-gray-200';
    }
});
</script>

<template>
    <Badge variant="outline" :class="classes">
        <slot>{{ label }}</slot>
    </Badge>
</template>

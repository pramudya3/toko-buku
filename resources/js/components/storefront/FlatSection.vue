<script setup lang="ts">
/**
 * FlatSection — Section wrapper with alternating backgrounds
 * following the Flat Design system.
 */
defineProps<{
    /** Background variant */
    variant?: 'white' | 'muted' | 'primary' | 'secondary' | 'accent';
    /** Add geometric decoration */
    decoration?: boolean;
    /** Remove vertical padding */
    noPadding?: boolean;
}>();
</script>

<template>
    <section
        class="relative overflow-hidden"
        :class="[
            variant === 'white' && 'bg-white',
            variant === 'muted' && 'bg-flat-muted',
            variant === 'primary' && 'bg-flat-primary text-white',
            variant === 'secondary' && 'bg-flat-secondary text-white',
            variant === 'accent' && 'bg-flat-accent text-white',
            !variant && 'bg-white',
            !noPadding && 'py-12 md:py-16',
        ]"
    >
        <!-- Geometric decoration -->
        <div
            v-if="decoration"
            class="pointer-events-none absolute inset-0 overflow-hidden"
            aria-hidden="true"
        >
            <div
                class="absolute -top-24 -right-24 size-48 rounded-full opacity-10"
                :class="[
                    variant === 'primary' && 'bg-white',
                    variant === 'secondary' && 'bg-white',
                    variant === 'accent' && 'bg-white',
                    (!variant || variant === 'white' || variant === 'muted') && 'bg-flat-primary',
                ]"
            />
            <div
                class="absolute -bottom-16 -left-16 size-32 rounded-full opacity-10"
                :class="[
                    variant === 'primary' && 'bg-white',
                    variant === 'secondary' && 'bg-white',
                    variant === 'accent' && 'bg-white',
                    (!variant || variant === 'white' || variant === 'muted') && 'bg-flat-secondary',
                ]"
            />
        </div>

        <div class="relative mx-auto max-w-6xl px-4">
            <slot />
        </div>
    </section>
</template>

<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import TextLink from '@/components/TextLink.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { login } from '@/routes';
import { email } from '@/routes/password';

defineOptions({
    layout: {
        title: 'Forgot password',
        description: 'Enter your email to receive a password reset link',
        showLogo: false,
    },
});

defineProps<{
    status?: string;
}>();
</script>

<template>
    <Head title="Lupa Kata Sandi" />

    <div
        v-if="status"
        class="mb-4 text-center text-sm font-medium text-green-600"
    >
        {{ status }}
    </div>

    <Card class="shadow-sm">
        <CardContent class="pt-6">
            <Form
                v-bind="email.form()"
                v-slot="{ errors, processing }"
                class="flex flex-col gap-6"
            >
                <div class="grid gap-6">
                    <div class="grid gap-2">
                        <Label for="email">Email address</Label>
                        <Input
                            id="email"
                            type="email"
                            name="email"
                            required
                            autofocus
                            :tabindex="1"
                            autocomplete="email"
                            placeholder="email@example.com"
                            :aria-invalid="!!errors.email"
                        />
                        <p v-if="errors.email" class="text-sm text-destructive">
                            {{ errors.email }}
                        </p>
                    </div>

                    <Button
                        type="submit"
                        class="mt-2 w-full"
                        :tabindex="2"
                        :disabled="processing"
                        data-test="email-password-reset-link-button"
                    >
                        <Spinner v-if="processing" />
                        Email password reset link
                    </Button>
                </div>

                <div class="text-center text-sm text-muted-foreground">
                    Or, return to
                    <TextLink
                        :href="login()"
                        class="underline underline-offset-4"
                        :tabindex="3"
                        >log in</TextLink
                    >
                </div>
            </Form>
        </CardContent>
    </Card>
</template>

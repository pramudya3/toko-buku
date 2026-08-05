<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';

import InputError from '@/components/InputError.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import TextLink from '@/components/TextLink.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { register } from '@/routes';
import { store } from '@/routes/login';
import { request } from '@/routes/password';

defineOptions({
    layout: {
        title: 'Log in to your account',
        description: 'Enter your email and password below to log in',
        showLogo: false,
    },
});

const props = defineProps<{
    status?: string;
    canResetPassword: boolean;
    demoCredentials?: { email: string; password: string } | null;
}>();

function fillDemoCredentials() {
    if (!props.demoCredentials) {
        return;
    }

    const emailInput = document.getElementById(
        'email',
    ) as HTMLInputElement | null;
    const passwordInput = document.getElementById(
        'password',
    ) as HTMLInputElement | null;

    for (const [input, value] of [
        [emailInput, props.demoCredentials.email],
        [passwordInput, props.demoCredentials.password],
    ] as const) {
        if (!input) {
            continue;
        }

        input.value = value;
        input.dispatchEvent(new Event('input', { bubbles: true }));
        input.dispatchEvent(new Event('change', { bubbles: true }));
    }
}
</script>

<template>
    <Head title="Log in" />

    <div
        v-if="status"
        class="mb-4 text-center text-sm font-medium text-green-600"
    >
        {{ status }}
    </div>

    <Form
        v-bind="store.form()"
        :reset-on-success="['password']"
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
                />
                <InputError :message="errors.email" />
            </div>

            <div class="grid gap-2">
                <div class="flex items-center justify-between">
                    <Label for="password">Password</Label>
                    <TextLink
                        v-if="canResetPassword"
                        :href="request()"
                        class="text-sm"
                        :tabindex="5"
                    >
                        Forgot your password?
                    </TextLink>
                </div>
                <PasswordInput
                    id="password"
                    name="password"
                    required
                    :tabindex="2"
                    autocomplete="current-password"
                    placeholder="Password"
                />
                <InputError :message="errors.password" />
            </div>

            <div class="flex items-center justify-between">
                <Label for="remember" class="flex items-center space-x-3">
                    <Checkbox id="remember" name="remember" :tabindex="3" />
                    <span>Remember me</span>
                </Label>
            </div>

            <div
                v-if="demoCredentials"
                class="rounded-lg border border-dashed border-amber-300 bg-amber-50 p-3 text-sm"
            >
                <p class="font-medium text-amber-800">Mode demo</p>
                <p class="mt-1 text-amber-700">
                    Admin: <code>{{ demoCredentials.email }}</code> /
                    <code>{{ demoCredentials.password }}</code>
                </p>
                <Button
                    type="button"
                    variant="outline"
                    size="sm"
                    class="mt-2"
                    :tabindex="6"
                    @click="fillDemoCredentials"
                >
                    Isi otomatis
                </Button>
            </div>

            <Button
                type="submit"
                class="mt-4 w-full"
                :tabindex="4"
                :disabled="processing"
                data-test="login-button"
            >
                <Spinner v-if="processing" />
                Log in
            </Button>
        </div>

        <div class="text-center text-sm text-muted-foreground">
            Don't have an account?
            <TextLink :href="register()" :tabindex="5">Sign up</TextLink>
        </div>
    </Form>
</template>

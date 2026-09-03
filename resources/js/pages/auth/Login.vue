<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';

import PasswordInput from '@/components/PasswordInput.vue';
import TextLink from '@/components/TextLink.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { register } from '@/routes';
import { redirect as googleRedirect } from '@/routes/google';
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
    <Head title="Masuk" />

    <div
        v-if="status"
        class="mb-4 text-center text-sm font-medium text-green-600"
    >
        {{ status }}
    </div>

    <Card class="shadow-sm">
        <CardContent class="pt-6">
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
                            :aria-invalid="!!(errors.email || errors.password)"
                        />
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
                            :aria-invalid="!!(errors.email || errors.password)"
                        />
                        <!-- Error tampil di bawah kolom password, border email+password merah via aria-invalid -->
                        <p
                            v-if="errors.email || errors.password"
                            class="text-sm text-destructive"
                        >
                            Invalid email or password
                        </p>
                    </div>

                    <div class="flex items-center justify-between">
                        <Label
                            for="remember"
                            class="flex items-center space-x-3"
                        >
                            <Checkbox
                                id="remember"
                                name="remember"
                                :tabindex="3"
                            />
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

                <div class="relative my-2">
                    <div class="absolute inset-0 flex items-center">
                        <span class="w-full border-t" />
                    </div>
                    <div class="relative flex justify-center text-xs uppercase">
                        <span class="bg-card px-2 text-muted-foreground"
                            >atau</span
                        >
                    </div>
                </div>

                <Button
                    type="button"
                    variant="outline"
                    class="w-full gap-2"
                    :tabindex="5"
                    as-child
                >
                    <a :href="googleRedirect.url()">
                        <svg
                            class="size-5"
                            viewBox="0 0 24 24"
                            aria-hidden="true"
                        >
                            <path
                                fill="#4285F4"
                                d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"
                            />
                            <path
                                fill="#34A853"
                                d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"
                            />
                            <path
                                fill="#FBBC05"
                                d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z"
                            />
                            <path
                                fill="#EA4335"
                                d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z"
                            />
                        </svg>
                        Lanjutkan dengan Google
                    </a>
                </Button>

                <div class="text-center text-sm text-muted-foreground">
                    Don't have an account?
                    <TextLink :href="register()" :tabindex="5"
                        >Sign up</TextLink
                    >
                </div>
            </Form>
        </CardContent>
    </Card>
</template>

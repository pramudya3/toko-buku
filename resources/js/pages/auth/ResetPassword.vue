<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import { ref } from 'vue';
import PasswordInput from '@/components/PasswordInput.vue';
import TextLink from '@/components/TextLink.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { login } from '@/routes';
import { update } from '@/routes/password';

defineOptions({
    layout: {
        title: 'Reset password',
        description: 'Please enter your new password below',
        showLogo: false,
    },
});

const props = defineProps<{
    token: string;
    email: string;
    passwordRules: string;
}>();

const inputEmail = ref(props.email);
</script>

<template>
    <Head title="Atur Ulang Kata Sandi" />

    <Card class="shadow-sm">
        <CardContent class="pt-6">
            <Form
                v-bind="update.form()"
                :transform="(data) => ({ ...data, token, email })"
                :reset-on-success="['password', 'password_confirmation']"
                v-slot="{ errors, processing }"
                class="flex flex-col gap-6"
            >
                <div class="grid gap-6">
                    <div class="grid gap-2">
                        <Label for="email">Email</Label>
                        <Input
                            id="email"
                            type="email"
                            name="email"
                            autocomplete="email"
                            v-model="inputEmail"
                            readonly
                            :tabindex="1"
                            :aria-invalid="!!errors.email"
                        />
                        <p v-if="errors.email" class="text-sm text-destructive">
                            {{ errors.email }}
                        </p>
                    </div>

                    <div class="grid gap-2">
                        <Label for="password">Password</Label>
                        <PasswordInput
                            id="password"
                            name="password"
                            required
                            :tabindex="2"
                            autocomplete="new-password"
                            autofocus
                            placeholder="Password"
                            :passwordrules="passwordRules"
                            :aria-invalid="!!errors.password"
                        />
                        <p
                            v-if="errors.password"
                            class="text-sm text-destructive"
                        >
                            {{ errors.password }}
                        </p>
                    </div>

                    <div class="grid gap-2">
                        <Label for="password_confirmation"
                            >Confirm password</Label
                        >
                        <PasswordInput
                            id="password_confirmation"
                            name="password_confirmation"
                            required
                            :tabindex="3"
                            autocomplete="new-password"
                            placeholder="Confirm password"
                            :passwordrules="passwordRules"
                            :aria-invalid="!!errors.password_confirmation"
                        />
                        <p
                            v-if="errors.password_confirmation"
                            class="text-sm text-destructive"
                        >
                            {{ errors.password_confirmation }}
                        </p>
                    </div>

                    <Button
                        type="submit"
                        class="mt-2 w-full"
                        :tabindex="4"
                        :disabled="processing"
                        data-test="reset-password-button"
                    >
                        <Spinner v-if="processing" />
                        Reset password
                    </Button>
                </div>

                <div class="text-center text-sm text-muted-foreground">
                    Remember your password?
                    <TextLink
                        :href="login()"
                        class="underline underline-offset-4"
                        :tabindex="5"
                        >Log in</TextLink
                    >
                </div>
            </Form>
        </CardContent>
    </Card>
</template>

<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import UserController from '@/actions/App/Http/Controllers/Admin/UserController';
import FieldHint from '@/components/FieldHint.vue';
import FormErrorAlert from '@/components/FormErrorAlert.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { index as indexRoute } from '@/routes/admin/users';

type User = {
    id: string;
    name: string;
    email: string;
    is_active: boolean;
};

const props = defineProps<{
    user: User | null;
}>();

const isEdit = Boolean(props.user);
const action = isEdit
    ? UserController.update.form(props.user!.id)
    : UserController.store.form();
</script>

<template>
    <Head :title="isEdit ? `Edit Staf: ${user?.name}` : 'Buat Staf'" />

    <div class="flex flex-col gap-4 p-4 md:p-6">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div>
                <h1 class="text-xl font-semibold tracking-tight">
                    {{ isEdit ? 'Edit Staf' : 'Buat Staf' }}
                </h1>
                <p class="text-sm text-muted-foreground">
                    {{
                        isEdit
                            ? `Memperbarui akun ${user?.name}`
                            : 'Menambahkan user yang dapat login sebagai admin'
                    }}
                </p>
            </div>
            <Button variant="outline" size="sm" as-child>
                <Link :href="indexRoute().url">← Kembali</Link>
            </Button>
        </div>

        <Form
            v-bind="action"
            class="flex flex-col gap-4"
            v-slot="{ errors, processing }"
        >
            <FormErrorAlert :errors="errors" />
            <Card>
                <CardHeader>
                    <CardTitle class="text-base font-medium"
                        >Data Akun</CardTitle
                    >
                </CardHeader>
                <CardContent class="grid gap-4 md:grid-cols-2">
                    <div class="grid gap-2">
                        <Label for="name">Nama *</Label>
                        <Input
                            id="name"
                            name="name"
                            :default-value="user?.name"
                            required
                        />
                    </div>
                    <div class="grid gap-2">
                        <Label for="email">Email *</Label>
                        <Input
                            id="email"
                            name="email"
                            type="email"
                            :default-value="user?.email"
                            required
                        />
                    </div>
                    <div class="grid gap-2">
                        <Label
                            for="password"
                            class="inline-flex w-fit items-center gap-1"
                        >
                            Password *
                            <FieldHint
                                v-if="isEdit"
                                text="Kosongkan untuk tidak mengubah password."
                            />
                        </Label>
                        <Input
                            id="password"
                            name="password"
                            type="password"
                            autocomplete="new-password"
                            placeholder="Minimal 8 karakter"
                            :required="!isEdit"
                        />
                    </div>
                    <div class="grid gap-2">
                        <Label
                            for="is_active"
                            class="inline-flex w-fit items-center gap-1"
                        >
                            Status
                            <FieldHint
                                v-if="user"
                                text="User nonaktif tidak dapat login."
                            />
                        </Label>
                        <Select
                            name="is_active"
                            :default-value="
                                user ? (user.is_active ? '1' : '0') : '1'
                            "
                        >
                            <SelectTrigger id="is_active">
                                <SelectValue placeholder="Pilih status" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="1">Aktif</SelectItem>
                                <SelectItem value="0">Nonaktif</SelectItem>
                            </SelectContent>
                        </Select>
                    </div>
                </CardContent>
            </Card>

            <div class="flex items-center gap-3">
                <Button type="submit" :disabled="processing">
                    {{
                        processing
                            ? 'Menyimpan...'
                            : isEdit
                              ? 'Simpan Perubahan'
                              : 'Buat Staf'
                    }}
                </Button>
                <Button variant="outline" type="button" as-child>
                    <Link :href="indexRoute().url">Batal</Link>
                </Button>
            </div>
        </Form>
    </div>
</template>

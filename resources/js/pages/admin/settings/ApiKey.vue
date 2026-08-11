<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import { KeyRound, Loader2, ShieldCheck } from '@lucide/vue';
import SettingController from '@/actions/App/Http/Controllers/Admin/SettingController';
import FormErrorAlert from '@/components/FormErrorAlert.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

defineProps<{
    has_api_key: boolean;
    key_masked: string | null;
    key_source: 'database' | 'environment';
}>();
</script>

<template>
    <Head title="Pengaturan — API Key" />

    <div class="flex flex-col gap-4 p-4 md:p-6">
        <div>
            <h1 class="text-xl font-semibold tracking-tight">
                Pengaturan API Key
            </h1>
            <p class="text-sm text-muted-foreground">
                Kunci API untuk layanan pengiriman
            </p>
        </div>

        <Form
            v-bind="SettingController.updateApiKey.form()"
            class="flex flex-col gap-4"
            v-slot="{ errors, processing }"
        >
            <FormErrorAlert :errors="errors" />

            <Card>
                <CardHeader>
                    <CardTitle
                        class="flex items-center gap-2 text-base font-medium"
                    >
                        <KeyRound class="size-4" />
                        API Key — Cek Ongkir (Biteship)
                    </CardTitle>
                </CardHeader>
                <CardContent class="flex flex-col gap-4">
                    <div
                        class="flex flex-wrap items-center gap-2 rounded-lg border bg-muted/40 px-3 py-2 text-sm"
                    >
                        <ShieldCheck class="size-4 text-muted-foreground" />
                        <span class="text-muted-foreground">Status:</span>
                        <span v-if="has_api_key" class="font-medium">
                            Tersimpan {{ key_masked }}
                        </span>
                        <span v-else class="font-medium">Belum diatur</span>
                        <Badge variant="outline" class="ml-auto">
                            {{
                                key_source === 'database'
                                    ? 'Database'
                                    : 'File .env'
                            }}
                        </Badge>
                    </div>

                    <div class="grid gap-2">
                        <Label for="api_key">Key Baru</Label>
                        <Input
                            id="api_key"
                            name="api_key"
                            type="password"
                            autocomplete="off"
                            spellcheck="false"
                            class="select-none"
                            placeholder="Kosongkan jika tidak diganti"
                            @copy.prevent
                            @cut.prevent
                            @contextmenu.prevent
                        />
                        <p class="text-xs text-muted-foreground">
                            Key asli hanya tersimpan di server dan tidak pernah
                            dikirim ke browser. Kosongkan untuk mempertahankan
                            key yang sudah tersimpan.
                        </p>
                    </div>

                    <label
                        class="flex w-fit items-center gap-2 text-sm text-muted-foreground"
                    >
                        <Checkbox name="clear_key" value="1" />
                        Hapus API key yang tersimpan
                    </label>
                </CardContent>
            </Card>

            <div class="flex items-center gap-3">
                <Button type="submit" :disabled="processing">
                    <Loader2 v-if="processing" class="size-4 animate-spin" />
                    {{ processing ? 'Menyimpan...' : 'Simpan' }}
                </Button>
            </div>
        </Form>
    </div>
</template>

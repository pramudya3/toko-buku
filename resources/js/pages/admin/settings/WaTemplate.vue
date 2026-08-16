<script setup lang="ts">
defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Beranda', href: '/admin/dashboard' },
            { title: 'WA Template', href: '/admin/settings/wa-template' },
        ],
    },
});

import { Form, Head } from '@inertiajs/vue3';
import { MessageCircle } from '@lucide/vue';
import SettingController from '@/actions/App/Http/Controllers/Admin/SettingController';
import FormErrorAlert from '@/components/FormErrorAlert.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';

defineProps<{
    wa_template_ready: string;
}>();
</script>

<template>
    <Head title="Pengaturan — WA Template" />

    <div class="flex flex-col gap-4 p-4 md:p-6">
        <div>
            <h1 class="text-xl font-semibold tracking-tight">
                Template WhatsApp
            </h1>
            <p class="text-sm text-muted-foreground">
                Pesan default untuk chat wa.me saat stok buku tersedia
            </p>
        </div>

        <Card class="max-w-2xl">
            <CardHeader>
                <CardTitle
                    class="flex items-center gap-2 text-base font-medium"
                >
                    <MessageCircle class="size-4" />
                    Pesan Stok Tersedia
                </CardTitle>
            </CardHeader>
            <CardContent>
                <Form
                    v-bind="SettingController.updateWaTemplate.form()"
                    method="put"
                    class="flex flex-col gap-4"
                    v-slot="{ errors, processing }"
                >
                    <FormErrorAlert :errors="errors" />

                    <div class="grid gap-2">
                        <Label for="wa_template_ready">
                            Template Pesan *
                        </Label>
                        <Textarea
                            id="wa_template_ready"
                            name="wa_template_ready"
                            :default-value="wa_template_ready"
                            rows="8"
                            required
                            placeholder="Assalamualaikum, ..."
                        />
                        <p class="text-xs text-muted-foreground">
                            Placeholder yang didukung:
                            <code class="rounded bg-muted px-1"
                                >{"{judul}"}</code
                            >
                            (judul buku),
                            <code class="rounded bg-muted px-1"
                                >{"{lembaga}"}</code
                            >
                            (nama toko), dan
                            <code class="rounded bg-muted px-1"
                                >{"{keterangan}"}</code
                            >
                            (<em>opsional</em> — bila disertakan, terisi otomatis
                            sesuai konteks pesanan/waitlist; bila tidak, tulis
                            kalimat lengkap Anda sendiri). Baris baru akan
                            dipertahankan di WhatsApp.
                        </p>
                        <p
                            v-if="errors.wa_template_ready"
                            class="text-sm text-destructive"
                        >
                            {{ errors.wa_template_ready }}
                        </p>
                    </div>

                    <div class="flex items-center gap-3">
                        <Button type="submit" :disabled="processing">
                            {{ processing ? 'Menyimpan...' : 'Simpan' }}
                        </Button>
                    </div>
                </Form>
            </CardContent>
        </Card>
    </div>
</template>

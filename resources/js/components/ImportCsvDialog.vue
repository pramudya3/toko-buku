<script setup lang="ts">
import { Form } from '@inertiajs/vue3';
import { Download } from '@lucide/vue';
import { ref } from 'vue';
import { toast } from 'vue-sonner';
import ImportTemplateController from '@/actions/App/Http/Controllers/Admin/ImportTemplateController';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import type { RouteFormDefinition } from '@/wayfinder';

withDefaults(
    defineProps<{
        open: boolean;
        action: RouteFormDefinition<'post'>;
        templateType: string;
        title: string;
        description: string;
        hint?: string;
    }>(),
    {
        hint: '',
    },
);

const emit = defineEmits<{
    (e: 'update:open', value: boolean): void;
}>();

// Di-bump setelah import sukses → input file di-remount (nilai ter-reset).
const resetKey = ref(0);

/**
 * Validasi client-side sebelum submit: ekstensi .csv/.txt + maks 2 MB.
 */
function validateFile(event: Event): void {
    const input = event.target as HTMLInputElement;
    const file = input.files?.[0];

    if (!file) {
        return;
    }

    if (!/\.(csv|txt)$/i.test(file.name)) {
        toast.error('File harus berformat CSV.');
        input.value = '';
    } else if (file.size > 2 * 1024 * 1024) {
        toast.error('Ukuran file maksimal 2 MB.');
        input.value = '';
    }
}

/**
 * Error validasi server (mis. file bukan CSV, ukuran > 2MB) → toast,
 * dialog tetap terbuka.
 */
function onError(errors: Record<string, string>): void {
    const first = Object.values(errors)[0];

    if (first) {
        toast.error(first);
    }
}

/**
 * Import sukses → dialog tetap terbuka (toast ringkasan dari server),
 * input file di-reset agar bisa upload ulang.
 */
function onSuccess(): void {
    resetKey.value++;
}
</script>

<template>
    <Dialog :open="open" @update:open="emit('update:open', $event)">
        <DialogContent class="sm:max-w-md">
            <DialogHeader>
                <DialogTitle>{{ title }}</DialogTitle>
                <DialogDescription>{{ description }}</DialogDescription>
            </DialogHeader>

            <Form
                v-bind="action"
                class="grid gap-4"
                :on-error="onError"
                :on-success="onSuccess"
                v-slot="{ processing }"
            >
                <div class="grid gap-2">
                    <Label for="import-file">File CSV *</Label>
                    <Input
                        id="import-file"
                        :key="resetKey"
                        name="file"
                        type="file"
                        accept=".csv,.txt"
                        required
                        @change="validateFile"
                    />
                </div>
                <a
                    :href="ImportTemplateController.download(templateType).url"
                    class="inline-flex w-fit items-center gap-1 text-sm text-muted-foreground underline-offset-4 hover:underline"
                >
                    <Download class="size-3.5" />
                    Download template CSV
                </a>
                <p v-if="hint" class="text-sm text-muted-foreground">
                    {{ hint }}
                </p>

                <DialogFooter>
                    <Button
                        type="button"
                        variant="outline"
                        @click="emit('update:open', false)"
                    >
                        Batal
                    </Button>
                    <Button type="submit" :disabled="processing">
                        {{ processing ? 'Mengimpor...' : 'Import' }}
                    </Button>
                </DialogFooter>
            </Form>
        </DialogContent>
    </Dialog>
</template>

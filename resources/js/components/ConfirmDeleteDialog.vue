<script setup lang="ts">
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';

defineProps<{
    open: boolean;
    title: string;
    description: string;
    confirmLabel?: string;
    confirmVariant?: 'default' | 'destructive';
}>();

const emit = defineEmits<{
    (e: 'update:open', value: boolean): void;
    (e: 'confirm'): void;
}>();
</script>

<template>
    <Dialog :open="open" @update:open="emit('update:open', $event)">
        <DialogContent class="sm:max-w-md">
            <DialogHeader class="space-y-3">
                <DialogTitle class="leading-tight font-semibold">{{
                    title
                }}</DialogTitle>
                <DialogDescription
                    class="text-sm leading-relaxed whitespace-pre-line text-muted-foreground"
                >
                    {{ description }}
                </DialogDescription>
            </DialogHeader>
            <DialogFooter class="gap-2">
                <Button variant="outline" @click="emit('update:open', false)">
                    Batal
                </Button>
                <Button
                    :variant="confirmVariant ?? 'destructive'"
                    @click="emit('confirm')"
                >
                    {{ confirmLabel ?? 'Hapus' }}
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>

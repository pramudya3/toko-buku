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
}>();

const emit = defineEmits<{
    (e: 'update:open', value: boolean): void;
    (e: 'confirm'): void;
}>();
</script>

<template>
    <Dialog :open="open" @update:open="emit('update:open', $event)">
        <DialogContent class="sm:max-w-xs">
            <DialogHeader>
                <DialogTitle>{{ title }}</DialogTitle>
                <DialogDescription>{{ description }}</DialogDescription>
            </DialogHeader>
            <DialogFooter>
                <Button variant="ghost" @click="emit('update:open', false)">
                    Batal
                </Button>
                <Button
                    variant="destructive"
                    @click="emit('confirm')"
                >
                    {{ confirmLabel ?? 'Hapus' }}
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>

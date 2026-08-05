import { router } from '@inertiajs/vue3';
import { toast } from 'vue-sonner';
import type { FlashToast } from '@/types/ui';

export function initializeFlashToast(): void {
    router.on('flash', (event) => {
        const flash = (event as CustomEvent).detail?.flash;
        const data = flash?.toast as FlashToast | undefined;

        if (!data) {
            return;
        }

        if (data.undo) {
            const undoUrl = data.undo.url;

            toast[data.type](data.message, {
                action: {
                    label: 'Undo',
                    onClick: () => {
                        router.post(undoUrl, undefined, {
                            preserveScroll: true,
                        });
                    },
                },
                duration: 8_000,
            });

            return;
        }

        toast[data.type](data.message);
    });
}

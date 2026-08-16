<script setup lang="ts">
/**
 * Editor WYSIWYG untuk isi artikel — port penuh dari template resmi
 * "simple-editor" tiptap.dev (ueberdosis/tiptap-ui-components) ke Vue 3.
 *
 * Struktur toolbar mengikuti template:
 * Undo/Redo | Heading▾ + List▾ + Blockquote + CodeBlock | Bold/Italic/Strike/
 * Code/Underline + Highlight▾ + Link▾ | Sub/Sup | TextAlign ×4 | Image
 *
 * Bonus di luar template: bubble toolbar saat teks diseleksi (sesuai
 * permintaan sebelumnya) dan upload gambar langsung ke R2.
 */
import {
    Bold,
    Code,
    CodeXml,
    Highlighter,
    ImagePlus,
    Italic,
    Link as LinkIcon,
    List,
    ListOrdered,
    ListTodo,
    Loader2,
    Quote,
    Redo2,
    Strikethrough,
    Subscript as SubscriptIcon,
    Superscript as SuperscriptIcon,
    TextAlignCenter,
    TextAlignEnd,
    TextAlignJustify,
    TextAlignStart,
    Underline as UnderlineIcon,
    Undo2,
} from '@lucide/vue';
import Highlight from '@tiptap/extension-highlight';
import Image from '@tiptap/extension-image';
import Link from '@tiptap/extension-link';
import Placeholder from '@tiptap/extension-placeholder';
import Subscript from '@tiptap/extension-subscript';
import Superscript from '@tiptap/extension-superscript';
import TaskItem from '@tiptap/extension-task-item';
import TaskList from '@tiptap/extension-task-list';
import TextAlign from '@tiptap/extension-text-align';
import Typography from '@tiptap/extension-typography';
import Underline from '@tiptap/extension-underline';
import StarterKit from '@tiptap/starter-kit';
import { EditorContent, useEditor } from '@tiptap/vue-3';
import type { Editor } from '@tiptap/vue-3';
import { BubbleMenu } from '@tiptap/vue-3/menus';
import { computed, ref, watch } from 'vue';
import type { Component } from 'vue';
import { toast } from 'vue-sonner';
import ArticleController from '@/actions/App/Http/Controllers/Admin/ArticleController';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { cn } from '@/lib/utils';

const props = withDefaults(
    defineProps<{
        modelValue: string;
        placeholder?: string;
    }>(),
    {
        placeholder: 'Mulai menulis artikel…',
    },
);

const emit = defineEmits<{
    (e: 'update:modelValue', value: string): void;
}>();

const editor = useEditor({
    content: props.modelValue,
    extensions: [
        StarterKit.configure({
            heading: { levels: [1, 2, 3, 4] },
            link: false,
        }),
        Link.configure({
            openOnClick: false,
            autolink: true,
            HTMLAttributes: {
                rel: 'noopener noreferrer',
                target: '_blank',
            },
        }),
        Underline,
        Highlight.configure({
            multicolor: true,
        }),
        Image.configure({
            inline: false,
            allowBase64: true,
            HTMLAttributes: {
                class: 'article-inline-image',
            },
        }),
        TaskList,
        TaskItem.configure({
            nested: true,
        }),
        TextAlign.configure({
            types: ['heading', 'paragraph'],
        }),
        Subscript,
        Superscript,
        Typography,
        Placeholder.configure({
            placeholder: props.placeholder,
        }),
    ],
    onUpdate: ({ editor: e }) => {
        emit('update:modelValue', e.getHTML());
    },
});

watch(
    () => props.modelValue,
    (value) => {
        if (editor.value && value !== editor.value.getHTML()) {
            editor.value.commands.setContent(value, {
                emitUpdate: false,
            });
        }
    },
);

function isActive(
    nameOrAttrs: string | Record<string, unknown>,
    attrs?: Record<string, unknown>,
) {
    if (typeof nameOrAttrs === 'string') {
        return editor.value?.isActive(nameOrAttrs, attrs) ?? false;
    }

    return editor.value?.isActive(nameOrAttrs) ?? false;
}

// ── Definisi tombol toolbar (mengikuti urutan template) ──
type Tool = {
    icon: Component;
    title: string;
    active?: () => boolean;
    can?: () => boolean;
    action: () => void;
};

const run = (command: (chain: ReturnType<Editor['chain']>) => unknown) => {
    if (editor.value) {
        command(editor.value.chain().focus());
    }
};

const markTools: Tool[] = [
    {
        icon: Bold,
        title: 'Tebal (Ctrl+B)',
        active: () => isActive('bold'),
        action: () => run((c) => c.toggleBold().run()),
    },
    {
        icon: Italic,
        title: 'Miring (Ctrl+I)',
        active: () => isActive('italic'),
        action: () => run((c) => c.toggleItalic().run()),
    },
    {
        icon: Strikethrough,
        title: 'Coret',
        active: () => isActive('strike'),
        action: () => run((c) => c.toggleStrike().run()),
    },
    {
        icon: Code,
        title: 'Kode inline',
        active: () => isActive('code'),
        action: () => run((c) => c.toggleCode().run()),
    },
    {
        icon: UnderlineIcon,
        title: 'Garis bawah (Ctrl+U)',
        active: () => isActive('underline'),
        action: () => run((c) => c.toggleUnderline().run()),
    },
];

const blockTools: Tool[] = [
    {
        icon: Quote,
        title: 'Kutipan',
        active: () => isActive('blockquote'),
        action: () => run((c) => c.toggleBlockquote().run()),
    },
    {
        icon: CodeXml,
        title: 'Blok kode',
        active: () => isActive('codeBlock'),
        action: () => run((c) => c.toggleCodeBlock().run()),
    },
];

const scriptTools: Tool[] = [
    {
        icon: SuperscriptIcon,
        title: 'Superskrip',
        active: () => isActive('superscript'),
        action: () => run((c) => c.toggleSuperscript().run()),
    },
    {
        icon: SubscriptIcon,
        title: 'Subskrip',
        active: () => isActive('subscript'),
        action: () => run((c) => c.toggleSubscript().run()),
    },
];

// Perataan teks — template memakai 4 tombol terpisah.
const ALIGN_OPTIONS: {
    value: 'left' | 'center' | 'right' | 'justify';
    icon: Component;
    title: string;
}[] = [
    { value: 'left', icon: TextAlignStart, title: 'Rata kiri' },
    { value: 'center', icon: TextAlignCenter, title: 'Rata tengah' },
    { value: 'right', icon: TextAlignEnd, title: 'Rata kanan' },
    { value: 'justify', icon: TextAlignJustify, title: 'Rata kanan-kiri' },
];

const alignTools: Tool[] = ALIGN_OPTIONS.map((option) => ({
    icon: option.icon,
    title: option.title,
    active: () => isActive({ textAlign: option.value }),
    action: () => run((c) => c.setTextAlign(option.value).run()),
}));

const toolbarButton = (active: boolean, disabled: boolean): string =>
    cn(
        'flex size-8 items-center justify-center rounded-md text-sm transition-colors',
        disabled
            ? 'cursor-not-allowed text-muted-foreground/40'
            : active
              ? 'bg-primary text-primary-foreground'
              : 'text-muted-foreground hover:bg-muted hover:text-foreground',
    );

const isDisabled = (tool: Tool) => {
    if (!editor.value) {
        return true;
    }

    return tool.can ? !tool.can() : false;
};

// ── Dropdown heading (Paragraf / H1–H4) ──
const styleValue = ref('p');
const styleOptions = [
    { value: 'p', label: 'Paragraf' },
    { value: 'h1', label: 'Heading 1' },
    { value: 'h2', label: 'Heading 2' },
    { value: 'h3', label: 'Heading 3' },
    { value: 'h4', label: 'Heading 4' },
];

watch(editor, (instance) => {
    if (!instance) {
        return;
    }

    const updateStyle = () => {
        for (const level of [1, 2, 3, 4] as const) {
            if (instance.isActive('heading', { level })) {
                styleValue.value = `h${level}`;

                return;
            }
        }

        styleValue.value = 'p';
    };

    instance.on('transaction', updateStyle);
    instance.on('selectionUpdate', updateStyle);
    updateStyle();
});

watch(styleValue, (value) => {
    if (!editor.value) {
        return;
    }

    if (value === 'p') {
        editor.value.chain().focus().setParagraph().run();
    } else {
        editor.value
            .chain()
            .focus()
            .setHeading({ level: Number(value.slice(1)) } as never)
            .run();
    }
});

// ── List dropdown (poin / bernomor / checklist) ──
const anyListActive = () =>
    isActive('bulletList') || isActive('orderedList') || isActive('taskList');

// ── Warna spidol (ColorHighlightPopover) ──
const HIGHLIGHT_COLORS = [
    { name: 'Kuning', value: '#fef08a' },
    { name: 'Hijau', value: '#bbf7d0' },
    { name: 'Biru', value: '#bfdbfe' },
    { name: 'Pink', value: '#fbcfe8' },
    { name: 'Ungu', value: '#ddd6fe' },
    { name: 'Oranye', value: '#fed7aa' },
    { name: 'Merah', value: '#fecaca' },
];

function applyHighlight(color: string) {
    editor.value?.chain().focus().toggleHighlight({ color }).run();
}

function clearHighlight() {
    editor.value?.chain().focus().unsetHighlight().run();
}

// ── Link popover (bukan window.prompt) ──
const linkOpen = ref(false);
const linkUrl = ref('');

const currentLink = computed(() => {
    const href = editor.value?.getAttributes('link').href as string | undefined;

    return href ?? '';
});

watch(linkOpen, (open) => {
    if (open) {
        linkUrl.value = currentLink.value;
    }
});

function normalizeUrl(url: string): string {
    const trimmed = url.trim();

    if (trimmed === '') {
        return '';
    }

    if (!/^https?:\/\//i.test(trimmed)) {
        return `https://${trimmed}`;
    }

    return trimmed;
}

function applyLink() {
    if (!editor.value) {
        return;
    }

    const url = normalizeUrl(linkUrl.value);

    if (url === '') {
        editor.value.chain().focus().extendMarkRange('link').unsetLink().run();
    } else {
        editor.value
            .chain()
            .focus()
            .extendMarkRange('link')
            .setLink({ href: url })
            .run();
    }

    linkOpen.value = false;
}

function removeLink() {
    if (!editor.value) {
        return;
    }

    editor.value.chain().focus().extendMarkRange('link').unsetLink().run();
    linkOpen.value = false;
}

// ── Upload gambar di dalam body → R2 ──
const fileInput = ref<HTMLInputElement | null>(null);
const uploading = ref(false);

function openImagePicker() {
    fileInput.value?.click();
}

async function handleFile(event: Event) {
    const input = event.target as HTMLInputElement;
    const file = input.files?.[0];

    input.value = '';

    if (!file || !editor.value) {
        return;
    }

    uploading.value = true;

    try {
        const form = new FormData();
        form.append('image', file);

        const response = await fetch(ArticleController.uploadImage().url, {
            method: 'POST',
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: form,
        });

        if (!response.ok) {
            throw new Error('Gagal mengunggah gambar.');
        }

        const data = (await response.json()) as { url: string };

        editor.value.chain().focus().setImage({ src: data.url }).run();
    } catch {
        toast.error('Gagal mengunggah gambar.');
    } finally {
        uploading.value = false;
    }
}

// ── Bubble toolbar saat teks diseleksi ──
type BubbleTool = {
    icon: Component;
    title: string;
    active?: () => boolean;
    action: () => void;
};
const bubbleTools: BubbleTool[] = [
    {
        icon: Bold,
        title: 'Tebal',
        active: () => isActive('bold'),
        action: () => run((c) => c.toggleBold().run()),
    },
    {
        icon: Italic,
        title: 'Miring',
        active: () => isActive('italic'),
        action: () => run((c) => c.toggleItalic().run()),
    },
    {
        icon: Strikethrough,
        title: 'Coret',
        active: () => isActive('strike'),
        action: () => run((c) => c.toggleStrike().run()),
    },
    {
        icon: Code,
        title: 'Kode',
        active: () => isActive('code'),
        action: () => run((c) => c.toggleCode().run()),
    },
    {
        icon: Highlighter,
        title: 'Spidol',
        active: () => isActive('highlight'),
        action: () => run((c) => c.toggleHighlight().run()),
    },
    {
        icon: LinkIcon,
        title: 'Tautan',
        active: () => isActive('link'),
        action: () => {
            linkUrl.value = currentLink.value;
            linkOpen.value = true;
        },
    },
];
</script>

<template>
    <div
        class="overflow-hidden rounded-md border bg-background focus-within:outline-none"
    >
        <!-- Toolbar — urutan mengikuti template simple-editor -->
        <div
            class="flex flex-wrap items-center justify-center gap-x-0.5 gap-y-1 border-b bg-muted/40 px-2 py-1.5"
            role="toolbar"
            aria-label="Pemformat teks"
        >
            <!-- Undo/Redo -->
            <button
                type="button"
                title="Urungkan (Ctrl+Z)"
                aria-label="Urungkan"
                :disabled="!editor?.can().undo()"
                :class="toolbarButton(false, !editor?.can().undo())"
                @mousedown.prevent
                @click.prevent="run((c) => c.undo().run())"
            >
                <Undo2 class="size-4" />
            </button>
            <button
                type="button"
                title="Ulangi (Ctrl+Y)"
                aria-label="Ulangi"
                :disabled="!editor?.can().redo()"
                :class="toolbarButton(false, !editor?.can().redo())"
                @mousedown.prevent
                @click.prevent="run((c) => c.redo().run())"
            >
                <Redo2 class="size-4" />
            </button>

            <span class="mx-1 h-5 w-px bg-border" aria-hidden="true" />

            <!-- Heading dropdown -->
            <Select v-model="styleValue" class="w-32">
                <SelectTrigger class="h-8 w-32 text-xs">
                    <SelectValue />
                </SelectTrigger>
                <SelectContent>
                    <SelectItem
                        v-for="option in styleOptions"
                        :key="option.value"
                        :value="option.value"
                    >
                        {{ option.label }}
                    </SelectItem>
                </SelectContent>
            </Select>

            <!-- List dropdown (poin / bernomor / checklist) -->
            <DropdownMenu>
                <DropdownMenuTrigger as-child>
                    <Button
                        type="button"
                        variant="ghost"
                        size="icon"
                        class="size-8"
                        :class="
                            anyListActive()
                                ? 'bg-primary text-primary-foreground'
                                : 'text-muted-foreground hover:bg-muted hover:text-foreground'
                        "
                        title="Daftar"
                        aria-label="Daftar"
                        :disabled="!editor"
                    >
                        <List class="size-4" />
                    </Button>
                </DropdownMenuTrigger>
                <DropdownMenuContent align="start">
                    <DropdownMenuItem
                        @select="run((c) => c.toggleBulletList().run())"
                    >
                        <List class="size-4" />
                        Daftar poin
                    </DropdownMenuItem>
                    <DropdownMenuItem
                        @select="run((c) => c.toggleOrderedList().run())"
                    >
                        <ListOrdered class="size-4" />
                        Daftar bernomor
                    </DropdownMenuItem>
                    <DropdownMenuItem
                        @select="run((c) => c.toggleTaskList().run())"
                    >
                        <ListTodo class="size-4" />
                        Checklist
                    </DropdownMenuItem>
                </DropdownMenuContent>
            </DropdownMenu>

            <!-- Blockquote & Code block -->
            <button
                v-for="tool in blockTools"
                :key="tool.title"
                type="button"
                :title="tool.title"
                :aria-label="tool.title"
                :disabled="isDisabled(tool)"
                :class="
                    toolbarButton(tool.active?.() ?? false, isDisabled(tool))
                "
                @mousedown.prevent
                @click.prevent="tool.action()"
            >
                <component :is="tool.icon" class="size-4" />
            </button>

            <span class="mx-1 h-5 w-px bg-border" aria-hidden="true" />

            <!-- Mark: bold/italic/strike/code/underline -->
            <button
                v-for="tool in markTools"
                :key="tool.title"
                type="button"
                :title="tool.title"
                :aria-label="tool.title"
                :disabled="isDisabled(tool)"
                :class="
                    toolbarButton(tool.active?.() ?? false, isDisabled(tool))
                "
                @mousedown.prevent
                @click.prevent="tool.action()"
            >
                <component :is="tool.icon" class="size-4" />
            </button>

            <!-- Highlight popover (warna) -->
            <DropdownMenu>
                <DropdownMenuTrigger as-child>
                    <Button
                        type="button"
                        variant="ghost"
                        size="icon"
                        class="size-8"
                        :class="
                            isActive('highlight')
                                ? 'bg-primary text-primary-foreground'
                                : 'text-muted-foreground hover:bg-muted hover:text-foreground'
                        "
                        title="Spidol"
                        aria-label="Spidol"
                        :disabled="!editor"
                    >
                        <Highlighter class="size-4" />
                    </Button>
                </DropdownMenuTrigger>
                <DropdownMenuContent align="start" class="w-44">
                    <p
                        class="px-2 pt-1.5 pb-1 text-xs font-medium text-muted-foreground"
                    >
                        Warna spidol
                    </p>
                    <DropdownMenuItem
                        v-for="color in HIGHLIGHT_COLORS"
                        :key="color.value"
                        @select="applyHighlight(color.value)"
                    >
                        <span
                            class="size-4 rounded-full ring-1 ring-black/10 ring-inset"
                            :style="{ backgroundColor: color.value }"
                        />
                        {{ color.name }}
                    </DropdownMenuItem>
                    <DropdownMenuSeparator />
                    <DropdownMenuItem @select="clearHighlight">
                        Hapus spidol
                    </DropdownMenuItem>
                </DropdownMenuContent>
            </DropdownMenu>

            <!-- Link popover -->
            <DropdownMenu v-model:open="linkOpen">
                <DropdownMenuTrigger as-child>
                    <Button
                        type="button"
                        variant="ghost"
                        size="icon"
                        class="size-8"
                        :class="
                            isActive('link')
                                ? 'bg-primary text-primary-foreground'
                                : 'text-muted-foreground hover:bg-muted hover:text-foreground'
                        "
                        title="Tautan"
                        aria-label="Tautan"
                        :disabled="!editor"
                    >
                        <LinkIcon class="size-4" />
                    </Button>
                </DropdownMenuTrigger>
                <DropdownMenuContent align="start" class="w-64">
                    <div class="flex flex-col gap-2 p-2">
                        <p class="text-xs font-medium text-muted-foreground">
                            URL tautan
                        </p>
                        <Input
                            v-model="linkUrl"
                            class="h-8 text-xs"
                            placeholder="https://…"
                            @keydown.enter.prevent="applyLink"
                        />
                        <div class="flex gap-2">
                            <Button
                                type="button"
                                size="sm"
                                class="flex-1"
                                @click="applyLink"
                            >
                                Terapkan
                            </Button>
                            <Button
                                v-if="currentLink"
                                type="button"
                                size="sm"
                                variant="outline"
                                @click="removeLink"
                            >
                                Hapus
                            </Button>
                        </div>
                    </div>
                </DropdownMenuContent>
            </DropdownMenu>

            <span class="mx-1 h-5 w-px bg-border" aria-hidden="true" />

            <!-- Subscript / Superscript -->
            <button
                v-for="tool in scriptTools"
                :key="tool.title"
                type="button"
                :title="tool.title"
                :aria-label="tool.title"
                :disabled="isDisabled(tool)"
                :class="
                    toolbarButton(tool.active?.() ?? false, isDisabled(tool))
                "
                @mousedown.prevent
                @click.prevent="tool.action()"
            >
                <component :is="tool.icon" class="size-4" />
            </button>

            <span class="mx-1 h-5 w-px bg-border" aria-hidden="true" />

            <!-- Text align ×4 -->
            <button
                v-for="tool in alignTools"
                :key="tool.title"
                type="button"
                :title="tool.title"
                :aria-label="tool.title"
                :disabled="isDisabled(tool)"
                :class="
                    toolbarButton(tool.active?.() ?? false, isDisabled(tool))
                "
                @mousedown.prevent
                @click.prevent="tool.action()"
            >
                <component :is="tool.icon" class="size-4" />
            </button>

            <span class="mx-1 h-5 w-px bg-border" aria-hidden="true" />

            <!-- Insert image -->
            <button
                type="button"
                :title="uploading ? 'Mengunggah…' : 'Sisipkan gambar'"
                :aria-label="uploading ? 'Mengunggah…' : 'Sisipkan gambar'"
                :disabled="!editor || uploading"
                :class="toolbarButton(false, !editor || uploading)"
                @click="openImagePicker"
            >
                <Loader2 v-if="uploading" class="size-4 animate-spin" />
                <ImagePlus v-else class="size-4" />
            </button>
            <input
                ref="fileInput"
                type="file"
                accept="image/*"
                class="hidden"
                @change="handleFile"
            />
        </div>

        <!-- Area tulis -->
        <EditorContent
            :editor="editor"
            class="article-editor prose max-w-none p-4 prose-stone dark:prose-invert"
        />
    </div>

    <!-- Bubble toolbar saat teks diseleksi -->
    <BubbleMenu
        v-if="editor"
        :editor="editor"
        :options="{ placement: 'top', offset: 12, flip: false, shift: true }"
        class="z-50 flex items-center gap-0.5 rounded-lg border bg-background p-1 shadow-md"
    >
        <button
            v-for="tool in bubbleTools"
            :key="tool.title"
            type="button"
            :title="tool.title"
            :aria-label="tool.title"
            :class="toolbarButton(tool.active?.() ?? false, false)"
            @mousedown.prevent
            @click.prevent="tool.action()"
        >
            <component :is="tool.icon" class="size-4" />
        </button>
    </BubbleMenu>
</template>

<style scoped>
.article-editor :deep(.ProseMirror) {
    outline: none;
    min-height: 420px;
}

.article-editor :deep(.ProseMirror p.is-editor-empty:first-child::before) {
    content: attr(data-placeholder);
    float: left;
    height: 0;
    color: var(--color-muted-foreground);
    pointer-events: none;
}

.article-editor :deep(img.article-inline-image) {
    display: block;
    max-width: 100%;
    border-radius: 0.5rem;
    margin: 1rem auto;
}
</style>

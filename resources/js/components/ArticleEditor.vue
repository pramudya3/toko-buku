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
 * permintaan sebelumnya) dan upload gambar langsung ke public storage.
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
    Rows3,
    Strikethrough,
    Subscript as SubscriptIcon,
    Superscript as SuperscriptIcon,
    TextAlignCenter,
    TextAlignEnd,
    TextAlignJustify,
    TextAlignStart,
    Type,
    Underline as UnderlineIcon,
    Undo2,
} from '@lucide/vue';
import { Extension } from '@tiptap/core';
import { FontFamily } from '@tiptap/extension-font-family';
import Highlight from '@tiptap/extension-highlight';
import Image from '@tiptap/extension-image';
import Link from '@tiptap/extension-link';
import Placeholder from '@tiptap/extension-placeholder';
import Subscript from '@tiptap/extension-subscript';
import Superscript from '@tiptap/extension-superscript';
import TaskItem from '@tiptap/extension-task-item';
import TaskList from '@tiptap/extension-task-list';
import TextAlign from '@tiptap/extension-text-align';
import { TextStyle } from '@tiptap/extension-text-style';
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
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { cn } from '@/lib/utils';

const props = withDefaults(
    defineProps<{
        modelValue: string;
        placeholder?: string;
    }>(),
    {
        placeholder: '',
    },
);

const emit = defineEmits<{
    (e: 'update:modelValue', value: string): void;
}>();

// Font size — Word/Google Docs style
const FontSize = Extension.create({
    name: 'fontSize',
    addOptions() {
        return {
            types: ['textStyle'],
        };
    },
    addGlobalAttributes() {
        return [
            {
                types: this.options.types,
                attributes: {
                    fontSize: {
                        default: null,
                        parseHTML: (element: HTMLElement) =>
                            element.style.fontSize || null,
                        renderHTML: (attributes: Record<string, unknown>) => {
                            if (!attributes.fontSize) {
                                return {};
                            }

                            return {
                                style: `font-size: ${attributes.fontSize}`,
                            };
                        },
                    },
                },
            },
        ];
    },
    addCommands() {
        return {
            setFontSize:
                (fontSize: string) =>
                ({
                    chain,
                }: {
                    chain: () => {
                        setMark: (
                            t: string,
                            a: Record<string, unknown>,
                        ) => { run: () => boolean };
                    };
                }) =>
                    chain().setMark('textStyle', { fontSize }).run(),
            unsetFontSize:
                () =>
                ({
                    chain,
                }: {
                    chain: () => {
                        unsetMark: (t: string) => { run: () => boolean };
                    };
                }) =>
                    chain().unsetMark('textStyle').run(),
        } as never;
    },
});

// Line height — Word/Google Docs style (1.0 – 2.5)
const LineHeight = Extension.create({
    name: 'lineHeight',
    addOptions() {
        return {
            types: ['paragraph', 'heading'],
        };
    },
    addGlobalAttributes() {
        return [
            {
                types: this.options.types,
                attributes: {
                    lineHeight: {
                        default: null,
                        parseHTML: (element: HTMLElement) =>
                            element.style.lineHeight || null,
                        renderHTML: (attributes: Record<string, unknown>) => {
                            if (!attributes.lineHeight) {
                                return {};
                            }

                            return {
                                style: `line-height: ${attributes.lineHeight}`,
                            };
                        },
                    },
                },
            },
        ];
    },
    addCommands() {
        return {
            setLineHeight:
                (lineHeight: string) =>
                ({
                    commands,
                }: {
                    commands: Record<string, (...args: unknown[]) => boolean>;
                }) =>
                    (this.options.types as string[]).every((type: string) =>
                        (
                            commands.updateAttributes as (
                                t: string,
                                a: Record<string, unknown>,
                            ) => boolean
                        )(type, { lineHeight }),
                    ),
            unsetLineHeight:
                () =>
                ({
                    commands,
                }: {
                    commands: Record<string, (...args: unknown[]) => boolean>;
                }) =>
                    (this.options.types as string[]).every((type: string) =>
                        (
                            commands.resetAttributes as (
                                t: string,
                                a: string,
                            ) => boolean
                        )(type, 'lineHeight'),
                    ),
        } as never;
    },
});

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
        TextStyle,
        FontFamily.configure({
            types: ['textStyle'],
        }),
        FontSize,
        LineHeight,
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
        title: 'Bold (Ctrl+B)',
        active: () => isActive('bold'),
        action: () => run((c) => c.toggleBold().run()),
    },
    {
        icon: Italic,
        title: 'Italic (Ctrl+I)',
        active: () => isActive('italic'),
        action: () => run((c) => c.toggleItalic().run()),
    },
    {
        icon: Strikethrough,
        title: 'Strikethrough',
        active: () => isActive('strike'),
        action: () => run((c) => c.toggleStrike().run()),
    },
    {
        icon: Code,
        title: 'Inline code',
        active: () => isActive('code'),
        action: () => run((c) => c.toggleCode().run()),
    },
    {
        icon: UnderlineIcon,
        title: 'Underline (Ctrl+U)',
        active: () => isActive('underline'),
        action: () => run((c) => c.toggleUnderline().run()),
    },
];

const blockTools: Tool[] = [
    {
        icon: Quote,
        title: 'Blockquote',
        active: () => isActive('blockquote'),
        action: () => run((c) => c.toggleBlockquote().run()),
    },
    {
        icon: CodeXml,
        title: 'Code block',
        active: () => isActive('codeBlock'),
        action: () => run((c) => c.toggleCodeBlock().run()),
    },
];

const scriptTools: Tool[] = [
    {
        icon: SuperscriptIcon,
        title: 'Superscript',
        active: () => isActive('superscript'),
        action: () => run((c) => c.toggleSuperscript().run()),
    },
    {
        icon: SubscriptIcon,
        title: 'Subscript',
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
    { value: 'left', icon: TextAlignStart, title: 'Align left' },
    { value: 'center', icon: TextAlignCenter, title: 'Align center' },
    { value: 'right', icon: TextAlignEnd, title: 'Align right' },
    { value: 'justify', icon: TextAlignJustify, title: 'Justify' },
];

const alignTools: Tool[] = ALIGN_OPTIONS.map((option) => ({
    icon: option.icon,
    title: option.title,
    active: () => isActive({ textAlign: option.value }),
    action: () => run((c) => c.setTextAlign(option.value).run()),
}));

// Line spacing — Word/Google Docs style
const LINE_HEIGHTS = [
    { value: '1', label: '1.0' },
    { value: '1.15', label: '1.15' },
    { value: '1.5', label: '1.5' },
    { value: '2', label: '2.0' },
    { value: '2.5', label: '2.5' },
];
const lineHeightValue = ref('1.5');

watch(editor, (instance) => {
    if (!instance) {
        return;
    }

    const updateLineHeight = () => {
        const attrs = instance.getAttributes('paragraph');
        const headingAttrs = instance.getAttributes('heading');
        const lh =
            (attrs.lineHeight as string | undefined) ??
            (headingAttrs.lineHeight as string | undefined) ??
            '1.5';
        lineHeightValue.value = LINE_HEIGHTS.some((o) => o.value === lh)
            ? lh
            : '1.5';
    };

    instance.on('transaction', updateLineHeight);
    instance.on('selectionUpdate', updateLineHeight);
    updateLineHeight();
});

watch(lineHeightValue, (value) => {
    if (!editor.value) {
        return;
    }

    if (value === '1.5') {
        (
            editor.value.chain().focus() as unknown as {
                unsetLineHeight: () => { run: () => void };
            }
        )
            .unsetLineHeight()
            .run();
    } else {
        (
            editor.value.chain().focus() as unknown as {
                setLineHeight: (v: string) => { run: () => void };
            }
        )
            .setLineHeight(value)
            .run();
    }
});

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

// ── Font family — Indonesia + Arabic ──
const FONT_OPTIONS = [
    { value: 'default', label: 'Default', family: 'inherit' },
    // Indonesia / Latin — umum untuk naskah Indonesia
    { value: 'Inter', label: 'Inter — Latin', family: 'Inter, sans-serif' },
    {
        value: 'Merriweather',
        label: 'Merriweather',
        family: 'Merriweather, serif',
    },
    { value: 'Lora', label: 'Lora', family: 'Lora, serif' },
    { value: 'Poppins', label: 'Poppins', family: 'Poppins, sans-serif' },
    {
        value: 'Times New Roman',
        label: 'Times New Roman',
        family: '"Times New Roman", serif',
    },
    // Arabic — untuk ayat, kutipan Arab
    { value: 'Amiri', label: 'Amiri — Arabic', family: 'Amiri, serif' },
    {
        value: 'Scheherazade New',
        label: 'Scheherazade New',
        family: '"Scheherazade New", serif',
    },
    {
        value: 'Noto Naskh Arabic',
        label: 'Noto Naskh Arabic',
        family: '"Noto Naskh Arabic", serif',
    },
    { value: 'Lateef', label: 'Lateef', family: 'Lateef, serif' },
];
const fontFamilyValue = ref('default');

// Font size — default 12, tanpa kata Default (Google Docs style)
const FONT_SIZE_OPTIONS = [
    { value: '10px', label: '10' },
    { value: '12px', label: '12' },
    { value: '14px', label: '14' },
    { value: '16px', label: '16' },
    { value: '18px', label: '18' },
    { value: '20px', label: '20' },
    { value: '24px', label: '24' },
    { value: '28px', label: '28' },
    { value: '32px', label: '32' },
    { value: '36px', label: '36' },
];
const fontSizeValue = ref('12px');

watch(editor, (instance) => {
    if (!instance) {
        return;
    }

    const updateFont = () => {
        const attrs = instance.getAttributes('textStyle');
        const family = (attrs.fontFamily as string | undefined) ?? '';
        fontFamilyValue.value = FONT_OPTIONS.some((o) => o.value === family)
            ? family
            : 'default';
    };

    instance.on('transaction', updateFont);
    instance.on('selectionUpdate', updateFont);
    updateFont();
});

watch(fontFamilyValue, (value) => {
    if (!editor.value) {
        return;
    }

    if (!value || value === 'default') {
        editor.value.chain().focus().unsetFontFamily().run();
    } else {
        editor.value.chain().focus().setFontFamily(value).run();
    }
});

watch(editor, (instance) => {
    if (!instance) {
        return;
    }

    const updateSize = () => {
        const attrs = instance.getAttributes('textStyle');
        const size = (attrs.fontSize as string | undefined) ?? '12px';
        fontSizeValue.value = FONT_SIZE_OPTIONS.some((o) => o.value === size)
            ? size
            : '12px';
    };

    instance.on('transaction', updateSize);
    instance.on('selectionUpdate', updateSize);
    updateSize();
});

watch(fontSizeValue, (value) => {
    if (!editor.value) {
        return;
    }

    if (value === '12px') {
        (
            editor.value.chain().focus() as unknown as {
                unsetFontSize: () => { run: () => void };
            }
        )
            .unsetFontSize()
            .run();
    } else {
        (
            editor.value.chain().focus() as unknown as {
                setFontSize: (v: string) => { run: () => void };
            }
        )
            .setFontSize(value)
            .run();
    }
});

// ── Dropdown heading (Paragraf / H1–H4) ──
const styleValue = ref('p');
const styleOptions = [
    { value: 'p', label: 'Normal text' },
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

// ── Highlight color ──
const HIGHLIGHT_COLORS = [
    { name: 'Yellow', value: '#fef08a' },
    { name: 'Green', value: '#bbf7d0' },
    { name: 'Blue', value: '#bfdbfe' },
    { name: 'Pink', value: '#fbcfe8' },
    { name: 'Purple', value: '#ddd6fe' },
    { name: 'Orange', value: '#fed7aa' },
    { name: 'Red', value: '#fecaca' },
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

// ── Upload gambar di dalam body → public storage ──
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
        title: 'Bold',
        active: () => isActive('bold'),
        action: () => run((c) => c.toggleBold().run()),
    },
    {
        icon: Italic,
        title: 'Italic',
        active: () => isActive('italic'),
        action: () => run((c) => c.toggleItalic().run()),
    },
    {
        icon: Strikethrough,
        title: 'Strikethrough',
        active: () => isActive('strike'),
        action: () => run((c) => c.toggleStrike().run()),
    },
    {
        icon: Code,
        title: 'Code',
        active: () => isActive('code'),
        action: () => run((c) => c.toggleCode().run()),
    },
    {
        icon: Highlighter,
        title: 'Highlight',
        active: () => isActive('highlight'),
        action: () => run((c) => c.toggleHighlight().run()),
    },
    {
        icon: LinkIcon,
        title: 'Link',
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
            <Tooltip>
                <TooltipTrigger as-child>
                    <button
                        type="button"
                        aria-label="Undo"
                        :disabled="!editor?.can().undo()"
                        :class="toolbarButton(false, !editor?.can().undo())"
                        @mousedown.prevent
                        @click.prevent="run((c) => c.undo().run())"
                    >
                        <Undo2 class="size-4" />
                    </button>
                </TooltipTrigger>
                <TooltipContent>Undo (Ctrl+Z)</TooltipContent>
            </Tooltip>
            <Tooltip>
                <TooltipTrigger as-child>
                    <button
                        type="button"
                        aria-label="Redo"
                        :disabled="!editor?.can().redo()"
                        :class="toolbarButton(false, !editor?.can().redo())"
                        @mousedown.prevent
                        @click.prevent="run((c) => c.redo().run())"
                    >
                        <Redo2 class="size-4" />
                    </button>
                </TooltipTrigger>
                <TooltipContent>Redo (Ctrl+Y)</TooltipContent>
            </Tooltip>

            <span class="mx-1 h-5 w-px bg-border" aria-hidden="true" />

            <!-- Heading dropdown -->
            <Tooltip>
                <TooltipTrigger as-child>
                    <div>
                        <Select v-model="styleValue" class="w-32">
                            <SelectTrigger
                                class="h-8 w-32 text-xs"
                                aria-label="Text style"
                            >
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
                    </div>
                </TooltipTrigger>
                <TooltipContent>Text style</TooltipContent>
            </Tooltip>

            <!-- Font family — Indonesia + Arabic -->
            <Tooltip>
                <TooltipTrigger as-child>
                    <div class="flex items-center gap-1">
                        <Type class="size-3.5 text-muted-foreground" />
                        <Select v-model="fontFamilyValue" class="w-36">
                            <SelectTrigger
                                class="h-8 w-36 text-xs"
                                aria-label="Font family"
                            >
                                <SelectValue placeholder="Font" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem
                                    v-for="opt in FONT_OPTIONS"
                                    :key="opt.value || 'default'"
                                    :value="opt.value"
                                >
                                    <span :style="{ fontFamily: opt.family }">{{
                                        opt.label
                                    }}</span>
                                </SelectItem>
                            </SelectContent>
                        </Select>
                    </div>
                </TooltipTrigger>
                <TooltipContent
                    >Font family — Indonesian & Arabic</TooltipContent
                >
            </Tooltip>

            <!-- Font size -->
            <Tooltip>
                <TooltipTrigger as-child>
                    <Select v-model="fontSizeValue" class="w-20">
                        <SelectTrigger
                            class="h-8 w-20 text-xs"
                            aria-label="Font size"
                        >
                            <SelectValue placeholder="Size" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem
                                v-for="opt in FONT_SIZE_OPTIONS"
                                :key="opt.value || 'default'"
                                :value="opt.value"
                            >
                                {{ opt.label }}
                            </SelectItem>
                        </SelectContent>
                    </Select>
                </TooltipTrigger>
                <TooltipContent>Font size</TooltipContent>
            </Tooltip>

            <!-- List dropdown (poin / bernomor / checklist) -->
            <Tooltip>
                <TooltipTrigger as-child>
                    <div>
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
                                    aria-label="Bulleted list"
                                    :disabled="!editor"
                                >
                                    <List class="size-4" />
                                </Button>
                            </DropdownMenuTrigger>
                            <DropdownMenuContent align="start">
                                <DropdownMenuItem
                                    @select="
                                        run((c) => c.toggleBulletList().run())
                                    "
                                >
                                    <List class="size-4" />
                                    Bulleted list
                                </DropdownMenuItem>
                                <DropdownMenuItem
                                    @select="
                                        run((c) => c.toggleOrderedList().run())
                                    "
                                >
                                    <ListOrdered class="size-4" />
                                    Numbered list
                                </DropdownMenuItem>
                                <DropdownMenuItem
                                    @select="
                                        run((c) => c.toggleTaskList().run())
                                    "
                                >
                                    <ListTodo class="size-4" />
                                    Checklist
                                </DropdownMenuItem>
                            </DropdownMenuContent>
                        </DropdownMenu>
                    </div>
                </TooltipTrigger>
                <TooltipContent>Bulleted list</TooltipContent>
            </Tooltip>

            <!-- Blockquote & Code block -->
            <template v-for="tool in blockTools" :key="tool.title">
                <Tooltip>
                    <TooltipTrigger as-child>
                        <button
                            type="button"
                            :aria-label="tool.title"
                            :disabled="isDisabled(tool)"
                            :class="
                                toolbarButton(
                                    tool.active?.() ?? false,
                                    isDisabled(tool),
                                )
                            "
                            @mousedown.prevent
                            @click.prevent="tool.action()"
                        >
                            <component :is="tool.icon" class="size-4" />
                        </button>
                    </TooltipTrigger>
                    <TooltipContent>{{ tool.title }}</TooltipContent>
                </Tooltip>
            </template>

            <span class="mx-1 h-5 w-px bg-border" aria-hidden="true" />

            <!-- Mark: bold/italic/strike/code/underline -->
            <template v-for="tool in markTools" :key="tool.title">
                <Tooltip>
                    <TooltipTrigger as-child>
                        <button
                            type="button"
                            :aria-label="tool.title"
                            :disabled="isDisabled(tool)"
                            :class="
                                toolbarButton(
                                    tool.active?.() ?? false,
                                    isDisabled(tool),
                                )
                            "
                            @mousedown.prevent
                            @click.prevent="tool.action()"
                        >
                            <component :is="tool.icon" class="size-4" />
                        </button>
                    </TooltipTrigger>
                    <TooltipContent>{{ tool.title }}</TooltipContent>
                </Tooltip>
            </template>

            <!-- Highlight popover (warna) -->
            <Tooltip>
                <TooltipTrigger as-child>
                    <div>
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
                                    aria-label="Highlight"
                                    :disabled="!editor"
                                >
                                    <Highlighter class="size-4" />
                                </Button>
                            </DropdownMenuTrigger>
                            <DropdownMenuContent align="start" class="w-44">
                                <p
                                    class="px-2 pt-1.5 pb-1 text-xs font-medium text-muted-foreground"
                                >
                                    Highlight color
                                </p>
                                <DropdownMenuItem
                                    v-for="color in HIGHLIGHT_COLORS"
                                    :key="color.value"
                                    @select="applyHighlight(color.value)"
                                >
                                    <span
                                        class="size-4 rounded-full ring-1 ring-black/10 ring-inset"
                                        :style="{
                                            backgroundColor: color.value,
                                        }"
                                    />
                                    {{ color.name }}
                                </DropdownMenuItem>
                                <DropdownMenuSeparator />
                                <DropdownMenuItem @select="clearHighlight">
                                    Clear highlight
                                </DropdownMenuItem>
                            </DropdownMenuContent>
                        </DropdownMenu>
                    </div>
                </TooltipTrigger>
                <TooltipContent>Highlight color</TooltipContent>
            </Tooltip>

            <!-- Link popover -->
            <Tooltip>
                <TooltipTrigger as-child>
                    <div>
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
                                    aria-label="Insert link"
                                    :disabled="!editor"
                                >
                                    <LinkIcon class="size-4" />
                                </Button>
                            </DropdownMenuTrigger>
                            <DropdownMenuContent align="start" class="w-64">
                                <div class="flex flex-col gap-2 p-2">
                                    <p
                                        class="text-xs font-medium text-muted-foreground"
                                    >
                                        Link URL
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
                                            Apply
                                        </Button>
                                        <Button
                                            v-if="currentLink"
                                            type="button"
                                            size="sm"
                                            variant="outline"
                                            @click="removeLink"
                                        >
                                            Remove
                                        </Button>
                                    </div>
                                </div>
                            </DropdownMenuContent>
                        </DropdownMenu>
                    </div>
                </TooltipTrigger>
                <TooltipContent>Insert link (Ctrl+K)</TooltipContent>
            </Tooltip>

            <span class="mx-1 h-5 w-px bg-border" aria-hidden="true" />

            <!-- Line spacing — Word/Google Docs -->
            <Tooltip>
                <TooltipTrigger as-child>
                    <div class="flex items-center gap-1">
                        <Rows3 class="size-3.5 text-muted-foreground" />
                        <Select v-model="lineHeightValue" class="w-20">
                            <SelectTrigger
                                class="h-8 w-20 text-xs"
                                aria-label="Line spacing"
                            >
                                <SelectValue placeholder="Spasi" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem
                                    v-for="opt in LINE_HEIGHTS"
                                    :key="opt.value"
                                    :value="opt.value"
                                >
                                    {{ opt.label }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                    </div>
                </TooltipTrigger>
                <TooltipContent>Line spacing</TooltipContent>
            </Tooltip>

            <span class="mx-1 h-5 w-px bg-border" aria-hidden="true" />

            <!-- Subscript / Superscript -->
            <template v-for="tool in scriptTools" :key="tool.title">
                <Tooltip>
                    <TooltipTrigger as-child>
                        <button
                            type="button"
                            :aria-label="tool.title"
                            :disabled="isDisabled(tool)"
                            :class="
                                toolbarButton(
                                    tool.active?.() ?? false,
                                    isDisabled(tool),
                                )
                            "
                            @mousedown.prevent
                            @click.prevent="tool.action()"
                        >
                            <component :is="tool.icon" class="size-4" />
                        </button>
                    </TooltipTrigger>
                    <TooltipContent>{{ tool.title }}</TooltipContent>
                </Tooltip>
            </template>

            <span class="mx-1 h-5 w-px bg-border" aria-hidden="true" />

            <!-- Text align ×4 -->
            <template v-for="tool in alignTools" :key="tool.title">
                <Tooltip>
                    <TooltipTrigger as-child>
                        <button
                            type="button"
                            :aria-label="tool.title"
                            :disabled="isDisabled(tool)"
                            :class="
                                toolbarButton(
                                    tool.active?.() ?? false,
                                    isDisabled(tool),
                                )
                            "
                            @mousedown.prevent
                            @click.prevent="tool.action()"
                        >
                            <component :is="tool.icon" class="size-4" />
                        </button>
                    </TooltipTrigger>
                    <TooltipContent>{{ tool.title }}</TooltipContent>
                </Tooltip>
            </template>

            <span class="mx-1 h-5 w-px bg-border" aria-hidden="true" />

            <!-- Insert image -->
            <Tooltip>
                <TooltipTrigger as-child>
                    <button
                        type="button"
                        :aria-label="uploading ? 'Uploading…' : 'Insert image'"
                        :disabled="!editor || uploading"
                        :class="toolbarButton(false, !editor || uploading)"
                        @click="openImagePicker"
                    >
                        <Loader2 v-if="uploading" class="size-4 animate-spin" />
                        <ImagePlus v-else class="size-4" />
                    </button>
                </TooltipTrigger>
                <TooltipContent>{{
                    uploading ? 'Uploading…' : 'Insert image'
                }}</TooltipContent>
            </Tooltip>
            <input
                ref="fileInput"
                type="file"
                accept="image/*"
                class="hidden"
                @change="handleFile"
            />
        </div>

        <!-- Area tulis — kertas Word/Google Docs: margin kiri-kanan lebar, bg-muted luar, bg-white kertas -->
        <div class="bg-muted p-2 md:p-6">
            <EditorContent
                :editor="editor"
                class="article-editor mx-auto prose max-w-[720px] bg-white px-6 py-8 shadow-sm prose-stone md:px-10 md:py-10 dark:bg-card dark:prose-invert"
            />
        </div>
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
    font-size: 12px;
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

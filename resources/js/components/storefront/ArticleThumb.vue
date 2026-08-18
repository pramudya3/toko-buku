<script setup lang="ts">
/**
 * Thumbnail SVG 16:9 untuk artikel storefront.
 * Nol permintaan gambar — motif geometris dari palet tema standar.
 */
defineProps<{
    motif:
        | 'stack'
        | 'manuscript'
        | 'readers'
        | 'quote'
        | 'shelf'
        | 'pencil'
        | 'lamp';
    label: string;
}>();
</script>

<template>
    <svg
        viewBox="0 0 320 180"
        role="img"
        :aria-label="label"
        class="aspect-video w-full rounded-md bg-muted ring-1 ring-border"
    >
        <rect width="320" height="180" fill="var(--muted, #f4f4f5)" />

        <!-- Tumpukan buku -->
        <template v-if="motif === 'stack'">
            <rect x="128" y="26" width="64" height="12" rx="2" fill="#a16207" />
            <rect x="116" y="42" width="88" height="16" rx="2" fill="#b4552d" />
            <rect
                x="110"
                y="62"
                width="100"
                height="16"
                rx="2"
                fill="#8a6d3b"
            />
            <rect
                x="104"
                y="82"
                width="112"
                height="16"
                rx="2"
                fill="#3f5d4a"
            />
            <rect
                x="98"
                y="102"
                width="124"
                height="16"
                rx="2"
                fill="#4a5568"
            />
            <rect
                x="92"
                y="122"
                width="136"
                height="16"
                rx="2"
                fill="#6b4f3a"
            />
            <path
                d="M92 124h136"
                stroke="var(--muted, #f4f4f5)"
                stroke-width="4"
                opacity=".8"
            />
        </template>

        <!-- Naskah + pensil -->
        <template v-else-if="motif === 'manuscript'">
            <rect
                x="88"
                y="38"
                width="144"
                height="104"
                rx="4"
                fill="#ffffff"
                stroke="var(--border, #e4e4e7)"
                stroke-width="2"
            />
            <g
                stroke="#1c1917"
                opacity=".22"
                stroke-width="5"
                stroke-linecap="round"
            >
                <path d="M110 62h70" />
                <path d="M110 80h98" />
                <path d="M110 98h84" />
                <path d="M110 116h52" />
            </g>
            <rect x="248" y="42" width="12" height="70" rx="3" fill="#a16207" />
            <path d="M248 112l6 16 6-16Z" fill="#1c1917" opacity=".7" />
        </template>

        <!-- Dua pembaca -->
        <template v-else-if="motif === 'readers'">
            <circle cx="112" cy="62" r="16" fill="#1c1917" opacity=".14" />
            <circle cx="208" cy="56" r="13" fill="#1c1917" opacity=".11" />
            <path
                d="M150 95c-24-19-64-24-96-13v80c32-11 72-6 96 13 24-19 64-24 96-13V82c-32-11-72-6-96 13Z"
                fill="var(--border, #e4e4e7)"
            />
            <path
                d="M150 95v80"
                stroke="#a16207"
                stroke-width="3"
                opacity=".5"
            />
        </template>

        <!-- Tanda kutip -->
        <template v-else-if="motif === 'quote'">
            <text
                x="160"
                y="64"
                text-anchor="middle"
                font-family="Lora, Georgia, serif"
                font-size="56"
                font-weight="600"
                fill="#a16207"
            >
                "
            </text>
            <path
                d="M160 92c-22-17-58-21-88-11v70c30-10 66-6 88 11 22-17 58-21 88-11V81c-30-10-66-6-88 11Z"
                fill="var(--border, #e4e4e7)"
            />
            <path
                d="M160 92v70"
                stroke="#a16207"
                stroke-width="3"
                opacity=".5"
            />
        </template>

        <!-- Rak buku -->
        <template v-else-if="motif === 'shelf'">
            <path
                d="M72 62h176"
                stroke="#1c1917"
                stroke-width="6"
                opacity=".15"
            />
            <rect
                x="88"
                y="30"
                width="18"
                height="30"
                rx="2"
                fill="#1c1917"
                opacity=".45"
            />
            <rect x="112" y="24" width="14" height="36" rx="2" fill="#a16207" />
            <rect
                x="132"
                y="34"
                width="16"
                height="26"
                rx="2"
                fill="#1c1917"
                opacity=".3"
            />
            <rect
                x="154"
                y="26"
                width="13"
                height="34"
                rx="2"
                fill="#1c1917"
                opacity=".5"
            />
            <path
                d="M72 112h176"
                stroke="#1c1917"
                stroke-width="6"
                opacity=".15"
            />
            <rect
                x="90"
                y="82"
                width="20"
                height="28"
                rx="2"
                fill="#1c1917"
                opacity=".35"
            />
            <rect
                x="116"
                y="78"
                width="15"
                height="32"
                rx="2"
                fill="#1c1917"
                opacity=".5"
            />
            <rect
                x="137"
                y="88"
                width="17"
                height="22"
                rx="2"
                fill="#a16207"
                opacity=".9"
            />
            <path
                d="M72 162h176"
                stroke="#1c1917"
                stroke-width="6"
                opacity=".15"
            />
        </template>

        <!-- Baris tulisan + pensil -->
        <template v-else-if="motif === 'pencil'">
            <g
                stroke="#1c1917"
                opacity=".2"
                stroke-width="5"
                stroke-linecap="round"
            >
                <path d="M86 58h96" />
                <path d="M86 78h126" />
                <path d="M86 98h106" />
                <path d="M86 118h72" />
            </g>
            <rect x="228" y="38" width="13" height="76" rx="3" fill="#a16207" />
            <path d="M228 114l6.5 18 6.5-18Z" fill="#1c1917" opacity=".7" />
        </template>

        <!-- Lampu baca -->
        <template v-else>
            <path d="M128 52h64l-12 26h-40Z" fill="#1c1917" opacity=".5" />
            <path
                d="M160 78v34"
                stroke="#1c1917"
                stroke-width="5"
                opacity=".25"
            />
            <rect
                x="134"
                y="112"
                width="52"
                height="8"
                rx="4"
                fill="#1c1917"
                opacity=".4"
            />
            <path d="M128 78L84 140h44Z" fill="#a16207" opacity=".16" />
            <rect
                x="78"
                y="138"
                width="96"
                height="12"
                rx="2"
                fill="#1c1917"
                opacity=".35"
            />
        </template>
    </svg>
</template>

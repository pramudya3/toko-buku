<script setup lang="ts">
/**
 * Thumbnail SVG 16:9 untuk artikel contoh storefront proto-d.
 * Nol permintaan gambar — motif geometris dari palet pcd-*.
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
        class="aspect-video w-full rounded-md border-2 border-flat-border bg-white"
    >
        <rect width="320" height="180" fill="#F3F4F6" />

        <!-- Tumpukan buku (Rekomendasi) -->
        <template v-if="motif === 'stack'">
            <rect x="128" y="26" width="64" height="12" rx="2" fill="#F59E0B" />
            <rect x="116" y="42" width="88" height="16" rx="2" fill="#3B82F6" />
            <rect
                x="110"
                y="62"
                width="100"
                height="16"
                rx="2"
                fill="#10B981"
            />
            <rect
                x="104"
                y="82"
                width="112"
                height="16"
                rx="2"
                fill="#059669"
            />
            <rect
                x="98"
                y="102"
                width="124"
                height="16"
                rx="2"
                fill="#6B7280"
            />
            <rect
                x="92"
                y="122"
                width="136"
                height="16"
                rx="2"
                fill="#2563EB"
            />
            <path
                d="M92 124h136"
                stroke="#F3F4F6"
                stroke-width="4"
                opacity=".8"
            />
        </template>

        <!-- Naskah + pensil (Di Balik Penerbitan) -->
        <template v-else-if="motif === 'manuscript'">
            <rect
                x="88"
                y="38"
                width="144"
                height="104"
                rx="4"
                fill="#ffffff"
                stroke="#E5E7EB"
                stroke-width="2"
            />
            <g
                stroke="#111827"
                opacity=".22"
                stroke-width="5"
                stroke-linecap="round"
            >
                <path d="M110 62h70" />
                <path d="M110 80h98" />
                <path d="M110 98h84" />
                <path d="M110 116h52" />
            </g>
            <rect x="248" y="42" width="12" height="70" rx="3" fill="#F59E0B" />
            <path d="M248 112l6 16 6-16Z" fill="#111827" opacity=".7" />
        </template>

        <!-- Dua pembaca (Keluarga) -->
        <template v-else-if="motif === 'readers'">
            <circle cx="112" cy="62" r="16" fill="#111827" opacity=".14" />
            <circle cx="208" cy="56" r="13" fill="#111827" opacity=".11" />
            <path
                d="M150 95c-24-19-64-24-96-13v80c32-11 72-6 96 13 24-19 64-24 96-13V82c-32-11-72-6-96 13Z"
                fill="#E5E7EB"
            />
            <path
                d="M150 95v80"
                stroke="#F59E0B"
                stroke-width="3"
                opacity=".5"
            />
        </template>

        <!-- Tanda kutip di atas buku (Resensi) -->
        <template v-else-if="motif === 'quote'">
            <text
                x="160"
                y="64"
                text-anchor="middle"
                font-family="'Outfit', ui-sans-serif, sans-serif"
                font-size="56"
                font-weight="700"
                fill="#F59E0B"
            >
                “
            </text>
            <path
                d="M160 92c-22-17-58-21-88-11v70c30-10 66-6 88 11 22-17 58-21 88-11V81c-30-10-66-6-88 11Z"
                fill="#E5E7EB"
            />
            <path
                d="M160 92v70"
                stroke="#F59E0B"
                stroke-width="3"
                opacity=".5"
            />
        </template>

        <!-- Rak buku (Praktis) -->
        <template v-else-if="motif === 'shelf'">
            <path
                d="M72 62h176"
                stroke="#111827"
                stroke-width="6"
                opacity=".15"
            />
            <rect
                x="88"
                y="30"
                width="18"
                height="30"
                rx="2"
                fill="#111827"
                opacity=".45"
            />
            <rect x="112" y="24" width="14" height="36" rx="2" fill="#F59E0B" />
            <rect
                x="132"
                y="34"
                width="16"
                height="26"
                rx="2"
                fill="#111827"
                opacity=".3"
            />
            <rect
                x="154"
                y="26"
                width="13"
                height="34"
                rx="2"
                fill="#111827"
                opacity=".5"
            />
            <path
                d="M72 112h176"
                stroke="#111827"
                stroke-width="6"
                opacity=".15"
            />
            <rect
                x="90"
                y="82"
                width="20"
                height="28"
                rx="2"
                fill="#111827"
                opacity=".35"
            />
            <rect
                x="116"
                y="78"
                width="15"
                height="32"
                rx="2"
                fill="#111827"
                opacity=".5"
            />
            <rect
                x="137"
                y="88"
                width="17"
                height="22"
                rx="2"
                fill="#F59E0B"
                opacity=".9"
            />
            <path
                d="M72 162h176"
                stroke="#111827"
                stroke-width="6"
                opacity=".15"
            />
        </template>

        <!-- Baris tulisan + pensil (Tutorial) -->
        <template v-else-if="motif === 'pencil'">
            <g
                stroke="#111827"
                opacity=".2"
                stroke-width="5"
                stroke-linecap="round"
            >
                <path d="M86 58h96" />
                <path d="M86 78h126" />
                <path d="M86 98h106" />
                <path d="M86 118h72" />
            </g>
            <rect x="228" y="38" width="13" height="76" rx="3" fill="#F59E0B" />
            <path d="M228 114l6.5 18 6.5-18Z" fill="#111827" opacity=".7" />
        </template>

        <!-- Lampu baca (Esai) -->
        <template v-else>
            <path d="M128 52h64l-12 26h-40Z" fill="#111827" opacity=".5" />
            <path
                d="M160 78v34"
                stroke="#111827"
                stroke-width="5"
                opacity=".25"
            />
            <rect
                x="134"
                y="112"
                width="52"
                height="8"
                rx="4"
                fill="#111827"
                opacity=".4"
            />
            <path d="M128 78L84 140h44Z" fill="#F59E0B" opacity=".16" />
            <rect
                x="78"
                y="138"
                width="96"
                height="12"
                rx="2"
                fill="#111827"
                opacity=".35"
            />
        </template>
    </svg>
</template>

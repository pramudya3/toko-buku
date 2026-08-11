// Pembungkus lembar nota A4. Toolbar aksi (cetak/tutup) diletakkan di LUAR //
komponen ini supaya tidak ikut tercetak.
<template>
    <div
        class="mx-auto w-full max-w-3xl bg-white p-6 text-sm text-slate-900 shadow-sm md:p-10 print:max-w-none print:p-0 print:text-black print:shadow-none"
    >
        <slot />
    </div>
</template>

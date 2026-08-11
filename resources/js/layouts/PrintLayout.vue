<script setup lang="ts">
// Layout halaman print (nota/invoice) — tanpa chrome admin (sidebar/header),
// hanya konten nota di tengah layar. Dipakai lewat prefix `print/` di app.ts.
</script>

<template>
    <div class="min-h-screen bg-muted/40 print:bg-white">
        <slot />
    </div>
</template>

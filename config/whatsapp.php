<?php

return [
    /*
    |--------------------------------------------------------------------------
    | WhatsApp
    |--------------------------------------------------------------------------
    |
    | Template pesan default untuk chat wa.me. Dapat diedit admin melalui
    | Pengaturan → WA Template (disimpan di tabel settings).
    |
    | Placeholder yang didukung:
    |   {judul}      — judul buku (bisa lebih dari satu, dipisah koma)
    |   {keterangan} — OPSIONAL: bila disertakan, terisi otomatis sesuai
    |                  konteks (pesanan / waitlist); bila tidak, admin
    |                  menulis kalimat lengkapnya sendiri
    |   {lembaga}    — nama lembaga/toko (setting Lembaga)
    |
    */
    'template_ready' => "Assalamualaikum,\n\nBuku {judul} sudah tersedia kembali di toko kami dan dapat dipesan kembali.\n\nJazakumullah Khoiron\n\n{lembaga}",
];

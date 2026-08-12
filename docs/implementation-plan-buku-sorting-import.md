# Implementation Plan — Sorting Tabel Buku & Perbaikan Import CSV

| | |
|---|---|
| **Dokumen** | Implementation Plan — Sorting Tabel Buku & Perbaikan Import CSV Buku |
| **Versi** | 0.1 |
| **Tanggal** | 2026-08-12 |
| **Status** | Draft untuk review |
| **Induk** | `docs/implementation-plan.md` |
| **Keputusan** | Sort server-side per kolom (whitelist); import CSV mendukung kedua sisi file (INVOICE + PRICELIST); laporan hasil via dialog |

---

## 1. Ringkasan

Dua fitur untuk menu **Katalog Buku** (admin):

1. **Sorting asc/desc per kolom** pada tabel buku — saat ini tabel hanya `orderByDesc('created_at')` dan `DataTable.vue` tidak mendukung sort.
2. **Perbaikan import CSV buku** — import saat ini hanya memproses sisi **INVOICE** dari file `INVOICE - PRICELIST.csv` (77 buku); sisi **PRICELIST** (70 buku: judul + harga, tanpa kategori) diabaikan. Total file berisi 147 buku.

### 1.1 Kondisi Saat Ini (terverifikasi)

| Aspek | Kondisi |
|---|---|
| Import CSV buku | ✅ Berfungsi: `POST admin/books/import` → `BookController@importCsv` |
| Hasil import file asli | 77 buku, 7 kategori otomatis berkode (`PRN`, `ALQ`, `ANK`, `Bdl`, `BI`, `KSH`, `PST`), 0 tanpa kategori |
| Deteksi kolom | Berbasis header; layout **KANAN (INVOICE)** menang bila kedua header ada → sisi kiri diabaikan |
| Tabel buku | `BookController@index` fixed `orderByDesc('created_at')`; `DataTable.vue` header polos (tanpa sort) |
| SKU otomatis | `BookService::ensureSku`: kategori berkode → `{kode}{6 digit}` (PRN000008); fallback `SKU-xxxx` |

---

## 2. Fitur A — Sorting Tabel Buku (asc/desc per kolom)

### 2.1 Komponen `DataTable.vue`

- Prop baru per kolom: `sortable?: boolean` (default `false` → halaman lain tidak terpengaruh).
- Prop tabel: `sort?: string` (key kolom aktif), `sortDirection?: 'asc' | 'desc'`.
- Klik header kolom sortable → toggle `asc ⇄ desc` (kolom sama) / pindah ke kolom baru (asc).
- Ikon indikator dari `@lucide/vue`: `ChevronsUpDown` (belum di-sort), `ArrowUp`/`ArrowDown` (aktif).
- Emit: `update:sort`, `update:sortDirection`.

### 2.2 Backend — `BookController@index`

- Terima query param `sort` + `direction`.
- **Whitelist kolom sortable** (selain whitelist → fallback default):

| `sort` | Order by |
|---|---|
| `judul` | `books.judul` |
| `penulis` | `books.penulis` |
| `kode_sku` | `books.kode_sku` |
| `harga` | `books.harga` |
| `stok` | `books.stok` |
| `created_at` | `books.created_at` |
| `category` | `categories.nama` (via join/select) |

- `direction` hanya `asc`/`desc`; lainnya → `desc`.
- Default: `created_at desc` (perilaku sekarang).
- `sort`/`direction` dikembalikan ke frontend lewat `filters` (untuk menjaga state saat paginasi).

### 2.3 Frontend — `books/Index.vue`

- State `sort` + `sortDirection` dari `props.filters`.
- Klik header → `router.get(indexRoute().url, { ..., sort, direction }, { preserveState: true, replace: true })` — tanpa debounce (aksi klik langsung).
- Kolom yang di-sortable: Judul, Penulis, SKU, Harga, Stok, Kategori, (Tanggal dibuat bila ada di tabel).

### 2.4 Test (Pest)

- Sort `judul` asc & desc → urutan benar.
- Sort `harga` asc & desc.
- `sort` invalid / `direction` invalid → fallback default (tidak error).
- Sort berjalan bersamaan dengan search + filter kategori.

---

## 3. Fitur B — Perbaikan Import CSV Buku

### B1. Import kedua sisi file sekaligus *(rekomendasi)*

- Dialog import mendapat pilihan **"Bagian file"**:
  - `auto` (default) — perilaku sekarang: deteksi header, layout INVOICE menang
  - `invoice` — hanya sisi kanan (Kategori, Kode Brg, Nama Barang, Penulis, Hrg Jual)
  - `pricelist` — hanya sisi kiri (Judul Buku, Harga Normal)
  - `keduanya` — proses baris 2×: pass INVOICE lalu pass PRICELIST; baris dengan sisi kosong di-skip
- Backend: `BookImportRequest` menerima `bagian` (enum, validasi `in:auto,invoice,pricelist,keduanya`); `BookController@importCsv` meneruskan ke deteksi kolom.
- Sisi PRICELIST: `category_id null`, `penulis null`, SKU legacy (`SKU-xxxx`) atau berbasis kategori bila tersedia, harga dari `Harga Normal`.
- Duplikat antar-sisi (judul sama) → update/skip (pola existing).

### B2. Laporan hasil import via dialog *(rekomendasi)*

- Saat ini: hanya toast ringkasan.
- Setelah import sukses: **dialog hasil** — N dibuat / diperbarui / dilewati + daftar baris gagal (scrollable, dengan alasan per baris).
- Toast tetap dipakai untuk kegagalan total (file invalid, validasi).

### B3. Template download *(opsional)*

- Tombol di dialog → unduh `template-buku.csv` (header standar + 1 baris contoh).

### Test tambahan

- `bagian=keduanya` pada file 16 kolom → jumlah buku = sisi kiri + sisi kanan.
- `bagian=invoice` / `pricelist` / `auto` masing-masing benar.
- Baris PRICELIST → `category_id null`, SKU legacy, harga `Harga Normal`.
- `bagian` invalid → error validasi.

---

## 4. Berkas yang Disentuh

| Berkas | Perubahan |
|---|---|
| `resources/js/components/DataTable.vue` | Dukungan sort (props + event + ikon) |
| `app/Http/Controllers/Admin/BookController.php` | Sorting whitelist + param `bagian` di import |
| `app/Http/Requests/Admin/BookImportRequest.php` | Validasi `bagian` |
| `resources/js/pages/admin/books/Index.vue` | State sort + dialog import (pilihan bagian + dialog hasil + template) |
| `tests/Feature/Admin/BookControllerTest.php` | Test sorting |
| `tests/Feature/Admin/BookImportTest.php` | Test bagian + laporan hasil |
| `docs/` | Dokumen ini |

## 5. Keputusan yang Perlu Dikonfirmasi

1. Sorting (Fitur A) — setuju?
2. B1 (kedua sisi) + B2 (dialog hasil) — dikerjakan? B3 (template) perlu?
3. Jika semua disetujui: kerjakan A + B1 + B2 sekaligus.

## 6. Catatan

- Data produksi/dev yang sudah ter-import (77 buku + 7 kategori + 437 pelanggan) **tidak disentuh** oleh perubahan ini.
- Import ulang dengan `bagian=keduanya` bersifat idempoten untuk sisi INVOICE (match by `kode_sku`/judul); sisi PRICELIST akan menambah 70 buku baru.

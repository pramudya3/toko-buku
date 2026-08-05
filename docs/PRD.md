# PRD — TokoBuku Admin Panel

| | |
|---|---|
| **Nama Produk** | TokoBuku Admin Panel |
| **Versi Dokumen** | 1.0 |
| **Tanggal** | 2025-08-04 |
| **Status** | Draft untuk review |
| **Tech Stack** | Laravel 12 (terbaru) · Vue.js 3 · PostgreSQL |
| **Referensi** | `pcp-prd.md` (PRD sistem e-commerce), `docs/IMPLEMENTATION-PLAN.md`, `README.md` |

---

## 1. Ringkasan Eksekutif

**TokoBuku Admin Panel** adalah aplikasi web internal (admin-only) untuk mengelola
toko buku online. Aplikasi ini adalah **sisi back-office** dari ekosistem toko buku yang
terdiri dari 2 aplikasi:

| Aplikasi | Peran | URL |
|---|---|---|
| `toko-buku` | Storefront customer (katalog, cart, WhatsApp checkout) | `/` |
| `toko-buku-admin` (**aplikasi ini**) | Panel admin: kelola katalog, pesanan, promo, stok gudang, keuangan | `/admin` |

Kedua aplikasi **berbagi satu database PostgreSQL `toko_buku`** — perubahan data di
panel admin langsung terlihat di storefront.

Panel admin dibangun ulang dari implementasi Filament 3 menjadi **SPA Vue.js 3**
dengan backend API Laravel 12, agar UI lebih cepat, fleksibel, dan mudah
dikustomisasi. Seluruh aturan bisnis (harga, stok, akuntansi) tetap terpusat di
lapisan service Laravel (`PricingService`, `InventoryService`, `AccountingService`).

---

## 2. Tujuan & Kriteria Sukses

### 2.1 Tujuan Produk
1. Memberikan antarmuka admin yang cepat, responsif, dan mudah dipakai untuk operasional toko harian.
2. Mengelola seluruh domain bisnis: **katalog buku, kategori, pelanggan, pesanan, promosi, inventori multi-gudang, dan arus kas**.
3. Menjaga **konsistensi data** dengan storefront (1 database, aturan harga & stok terpusat).
4. Menyediakan **audit trail** untuk setiap mutasi stok dan pencatatan keuangan.

### 2.2 Kriteria Sukses (KPI)
- Admin dapat membuat/mengedit buku, kategori, promo, dan pelanggan dalam **< 2 menit** per transaksi.
- Proses konfirmasi pesanan (set ongkir + pilih gudang + ubah status) maksimal **3 langkah**.
- Order selesai → stok gudang asal berkurang **dan** 2 entry cash flow (revenue + shipping) tercatat **otomatis & atomik**.
- Harga final di panel admin **selalu sama** dengan yang dihitung storefront (konsistensi 100%, dijamin test suite).
- **100% test suite hijau** sebelum rilis (`php artisan test`).

---

## 3. Scope

### 3.1 In Scope (v1.0)
- Autentikasi & otorisasi admin (login, akses panel khusus `is_admin = true`).
- Dashboard dengan ringkasan statistik, pesanan terbaru, dan peringatan stok menipis.
- CRUD katalog buku (dengan detail lengkap, SKU otomatis, flag preorder, kolom Gramedia).
- CRUD kategori.
- Kelola pelanggan (status tier: reguler / bazaf / guru / reseller, diskon, WhatsApp, alamat).
- Kelola pesanan: lihat, buat manual, edit, ubah status, isi ongkir & ekspedisi, pilih gudang asal.
- Kelola promosi (3 tipe: persentase, harga tetap, bundling; global atau per-buku).
- Inventori multi-gudang: stok Malang, Sidoarjo, defect + mutasi stok (transfer, masuk, keluar, defect).
- Arus kas: entry otomatis saat order selesai, tampilan laporan + filter tanggal.
- Data dropshipper pada pesanan (end-customer: nama, WhatsApp, alamat).

### 3.2 Out of Scope (v1.0)
- Payment gateway online (pembayaran tetap via transfer/COD manual).
- Manajemen produk non-buku (produk = buku).
- Aplikasi mobile native (cukup responsive web).
- Integrasi marketplace eksternal (Shopee/Tokopedia) — cadangan roadmap v2.
- Multi-bahasa / multi-mata uang.

---

## 4. Pengguna & Peran

| Peran | Deskripsi | Akses |
|---|---|---|
| **Admin** (`is_admin = true`) | Pemilik/operator toko. Satu level akses di v1.0. | Semua modul |
| **Admin viewer** *(v2.0)* | Akses baca saja untuk laporan (roadmap). | — |

Catatan: customer (reguler/bazaf/guru/reseller) **tidak mengakses panel ini** — mereka
memakai storefront. Status tier mereka hanya dikelola dari sini (lihat §5.5).

---

## 5. Kebutuhan Fungsional

Prioritas: **M** = Must have (v1.0 wajib), **S** = Should have, **C** = Could have (v2.0).

### 5.1 Autentikasi & Otorisasi — M
| ID | Requirement |
|---|---|
| AUTH-01 | Halaman login di `/admin/login` dengan email + password. |
| AUTH-02 | Hanya user dengan `is_admin = true` yang boleh mengakses panel (`canAccessPanel`). |
| AUTH-03 | Session aman (Laravel session + CSRF), logout tersedia di sidebar. |
| AUTH-04 | Halaman login menampilkan kredensial demo + tombol **isi otomatis** (khusus mode demo). |
| AUTH-05 | Middleware `auth` + `admin` melindungi seluruh route `/admin/**` (backend memvalidasi, tidak hanya sembunyikan menu di UI). |

### 5.2 Dashboard — M
| ID | Requirement |
|---|---|
| DASH-01 | Statistik ringkas: total penjualan, jumlah order (periode), total pemasukan, jumlah buku/stok. |
| DASH-02 | Tabel **pesanan terbaru** (5–10 order) dengan status & total. |
| DASH-03 | Peringatan **stok menipis** (buku dengan total stok normal ≤ ambang batas, mis. 5). |
| DASH-04 | Grafik penjualan sederhana (7/30 hari) — *should have*. |
| DASH-05 | Semua angka dihitung ulang saat halaman dimuat (tanpa cache basi). |

### 5.3 Katalog Buku — M
| ID | Requirement |
|---|---|
| BOOK-01 | CRUD buku lengkap dengan field: `kode_sku`, `judul`, `penulis`, `penerbit`, `tahun`, `isbn`, `sinopsis`, `harga`, `stok`, `category_id`, `cover_url`, `aktif`, `is_preorder`, `po_label`. |
| BOOK-02 | Detail Gramedia (opsional): `tanggal_terbit`, `rating_umur`, `dimensi`, `kemasan`, `berat_gr`, `jumlah_halaman`, `jenis_kertas`, `cetakan`. |
| BOOK-03 | **SKU otomatis** bila kosong: `SKU-0001` (format `SKU-` + 4 digit nomor urut). |
| BOOK-04 | Aturan bisnis: buku yang memiliki **riwayat pesanan tidak boleh dihapus** — tampilkan pesan kesalahan; solusinya set `aktif = false` (soft-disable). |
| BOOK-05 | Pencarian & filter: by judul/penulis/ISBN (searchable), by kategori, by status aktif. |
| BOOK-06 | Upload cover via file (tersimpan di storage publik) atau URL eksternal. |
| BOOK-07 | List menampilkan kolom: cover thumbnail, judul, SKU, kategori, harga, stok total, status aktif, flag preorder. |
| BOOK-08 | Validasi: judul & harga wajib, harga ≥ 0, harga integer (rupiah), stok ≥ 0. |

### 5.4 Kategori — M
| ID | Requirement |
|---|---|
| CAT-01 | CRUD kategori (`nama`, `slug` unik). |
| CAT-02 | List menampilkan jumlah buku per kategori. |
| CAT-03 | Kategori yang masih dipakai buku tidak bisa dihapus (proteksi integritas). |

### 5.5 Pelanggan (Customer) — M
| ID | Requirement |
|---|---|
| CUST-01 | List semua user customer (tabel `users`), pencarian by nama/email/WhatsApp. |
| CUST-02 | Edit: `name`, `email`, `whatsapp_number`, `status_pelanggan` (reguler/bazaf/guru/reseller), `tier_discount` (%), alamat lengkap (provinsi/kabupaten/kecamatan/kode pos). |
| CUST-03 | Tampilkan badge status tier + ringkasan transaksi customer (jumlah order, total belanja) — *should have*. |
| CUST-04 | Admin tidak bisa mengubah `is_admin` dari halaman customer (dipisah). |

### 5.6 Pesanan (Orders) — M
| ID | Requirement |
|---|---|
| ORD-01 | List order: filter by status, pencarian by `no_order`/nama pembeli, sort by tanggal. |
| ORD-02 | Detail order: item (judul, qty, harga asli, diskon promo, diskon tier, harga final), subtotal, ongkir, grand total, metode bayar (transfer/COD), alamat pengiriman, ekspedisi, data dropship. |
| ORD-03 | **Buat order manual** (order masuk via WhatsApp): pilih customer/isi nama, tambah item buku + qty, isi alamat & metode bayar. |
| ORD-04 | Alur status: `menunggu_konfirmasi → diproses → dikirim → selesai` atau `batal` dari status mana pun. |
| ORD-05 | Saat memproses order: admin **mengisi ongkir final** & **memilih gudang asal** (Malang/Sidoarjo) → status `diproses`. |
| ORD-06 | **Order selesai** (`selesai`) → sistem otomatis & atomik: (a) kurangi stok gudang asal via `InventoryService`, (b) buat 2 entry cash flow (revenue + shipping) via `AccountingService`. |
| ORD-07 | Idempotensi: order yang sudah selesai tidak boleh memicu deduksi/entry ganda (guard status transition). |
| ORD-08 | Harga item dihitung ulang via `PricingService` saat order dibuat/diedit (base → promo → tier), konsisten dengan storefront. |

### 5.7 Promosi — M
| ID | Requirement |
|---|---|
| PROM-01 | CRUD promosi: `promo_name`, `promo_type`, nilai (`discount_percentage` / `promo_value` / `bundle_qty`), `start_date`, `end_date`, `is_active`. |
| PROM-02 | 3 tipe promo: **percentage** (diskon %), **fixed** (harga tetap), **bundle** (diskon % saat qty ≥ `bundle_qty`). |
| PROM-03 | Promo berlaku **global** (semua buku) atau **diikat ke buku tertentu** (pivot `promotion_book`). |
| PROM-04 | Validasi: `end_date ≥ start_date`; peringatan bila tanggal tumpang-tindih dengan promo lain — *should have*. |
| PROM-05 | Toggle aktif/nonaktif cepat dari list. |
| PROM-06 | Harga promo diterapkan otomatis oleh `PricingService` berdasarkan tanggal hari ini — admin tidak menghitung manual. |

### 5.8 Inventori Multi-Gudang — M
| ID | Requirement |
|---|---|
| INV-01 | Stok per buku per gudang: `stock_malang`, `stock_sidoarjo`, `stock_defect` (tabel `inventory_stocks`). |
| INV-02 | **Mutasi stok** (`inventory_movements`): tipe `transfer` (antar gudang), `in` (masuk), `out` (keluar), `defect` (pindah ke defect). |
| INV-03 | Setiap mutasi wajib mencatat audit trail: buku, dari/ke gudang, qty, tipe, referensi (`order_id`), `user_id`, timestamp. |
| INV-04 | Stok **tidak boleh negatif** (validasi server-side). |
| INV-05 | **Defect stock tidak pernah dijual** — hanya bisa dipindah via mutasi `defect`, tidak muncul sebagai stok tersedia. |
| INV-06 | `books.stok` tetap dipertahankan sebagai **agregat ringan** (total stok normal) dan disinkronkan otomatis oleh `InventoryService`. |
| INV-07 | List stok: tampilan per buku (3 kolom gudang + total), filter stok menipis. |
| INV-08 | Form mutasi: pilih buku, tipe, gudang asal/tujuan, qty, referensi opsional. |

### 5.9 Keuangan / Arus Kas — M
| ID | Requirement |
|---|---|
| CF-01 | Tabel `cash_flows`: `order_id`, `entry_date`, `flow_type` (revenue/shipping/refund), `amount`, `description`. |
| CF-02 | Entry **read-only** (tidak bisa diedit manual) — dibuat otomatis oleh `AccountingService`. |
| CF-03 | Order selesai → entry `revenue` (total produk) + `shipping` (ongkir), transaksi DB atomik bersama deduksi stok. |
| CF-04 | Order batal → tidak ada entry baru. |
| CF-05 | Laporan: list + filter rentang tanggal, ringkasan total masuk/keluar, referensi `order_id` tercantum (auditable). |
| CF-06 | Widget dashboard: total kas masuk bulan ini. |

### 5.10 Dropshipping — S
| ID | Requirement |
|---|---|
| DROP-01 | Detail order menampilkan data dropship: `end_customer_name`, `end_customer_whatsapp`, `end_customer_address`. |
| DROP-02 | List/filter order dropship (`is_dropship = true`). |
| DROP-03 | Laporan dropship sederhana (filter tanggal) — *should have*. |

### 5.11 Pengaturan & Lainnya — S
| ID | Requirement |
|---|---|
| SET-01 | Profil admin (ganti nama/password). |
| SET-02 | Halaman demo account (kredensial demo) — mode demo. |
| SET-03 | Ekspor CSV/Excel untuk list buku, order, dan cash flow — *should have v1.1*. |

---

## 6. Aturan Bisnis

| # | Aturan |
|---|---|
| BR-01 | **Urutan perhitungan harga**: harga dasar (`books.harga`) → promo aktif (kalender) → tier discount (reseller/bazaf). Diterapkan di `PricingService`, satu-satunya sumber kebenaran. |
| BR-02 | Promo aktif = `is_active = true` DAN `start_date ≤ hari ini ≤ end_date`. |
| BR-03 | Tier discount (`config/pricing.php`): reseller (qty ≥ 10 → 10%, qty ≥ 20 → 15%), bazaf (qty ≥ 10 → 5%, qty ≥ 20 → 8%). Reguler & guru tanpa diskon tier. |
| BR-04 | Buku dengan riwayat `order_items` **tidak dapat dihapus**; hanya bisa dinonaktifkan. |
| BR-05 | Hanya transisi status order yang valid yang diperbolehkan; `selesai`/`batal` hanya sekali (idempotent side-effect). |
| BR-06 | Order selesai → deduksi stok gudang asal + entry cash flow dalam **satu transaksi DB atomik** (rollback bersama bila gagal). |
| BR-07 | Stok gudang tidak boleh negatif; stok defect tidak pernah dijual. |
| BR-08 | SKU buku di-generate otomatis bila kosong. |

---

## 7. Arsitektur & Tech Stack

### 7.1 Stack yang Dipilih
| Komponen | Teknologi | Catatan |
|---|---|---|
| Backend | **Laravel 12** (PHP 8.2+) | API + domain logic |
| Frontend | **Vue.js 3** (Composition API, `<script setup>`) | SPA panel admin |
| Integrasi FE–BE | **Inertia.js** (mode SPA) + Pinia | Rekomendasi; alternatif: REST API + Sanctum |
| Router FE | Vue Router | Route guard per halaman admin |
| Komponen UI | Tailwind CSS 4 + Headless UI / shadcn-vue | Desain konsisten, aksesibel |
| Database | **PostgreSQL 14+** (`toko_buku`) | Shared dgn storefront |
| Asset build | Vite 7 | HMR saat dev |
| Testing | Pest/PHPUnit (backend) + Vitest (FE) | |

### 7.2 Struktur Aplikasi
```
app/
├── Http/
│   ├── Controllers/Admin/       # Controller per modul (Book, Category, Order, …)
│   ├── Middleware/Admin.php     # Guard is_admin
│   └── Requests/                # FormRequest + validasi
├── Models/                      # Book, Category, Order, OrderItem, User, Promotion,
│                                #   InventoryStock, InventoryMovement, CashFlow, Dropshipper
├── Services/                    # PricingService, InventoryService, AccountingService
│                                #   (single source of truth — DIPAKAI JUGA OLEH STOREFRONT)
└── Policies/                    # Otorisasi per resource
resources/js/
├── Pages/Admin/                 # Dashboard, Books, Categories, Customers, Orders,
│                                #   Promotions, Inventory, CashFlow (Vue SFC)
├── Components/                  # DataTable, FormField, Badge, Modal, dll (reusable)
└── Stores/                      # Pinia: auth, cart-setting, notification
routes/web.php                   # Route /admin/** (Inertia)
database/migrations/             # Skema shared (identik dgn storefront + delta)
config/pricing.php, shipping.php # Aturan harga & ekspedisi
```

### 7.3 Keputusan Arsitektur Kunci
1. **1 database, 2 aplikasi**: skema & service harga/stok **identik** antara `toko-buku` dan `toko-buku-admin`; migrasi cukup dijalankan sekali (dari salah satu app).
2. **Single source of truth harga**: `PricingService` dijamin oleh test suite lintas aplikasi (harga final panel = harga final storefront).
3. **Perubahan Filament → Vue**: resource Filament digantikan halaman Inertia+Vue; logika service (`*Service.php`) dipertahankan dan dipakai ulang.
4. **Semua harga dalam integer rupiah** (bukan float) untuk menghindari error pembulatan.
5. **Side-effect sistemik (stok, akuntansi) hanya via Service layer**, bukan dari controller langsung.

---

## 8. Data Model (PostgreSQL)

> Skema lengkap mengikuti migrasi existing. Berikut ringkasan tabel utama:

| Tabel | Kolom penting | Relasi |
|---|---|---|
| `users` | name, email, password, `is_admin`, `status_pelanggan` (reguler/bazaf/guru/reseller), `whatsapp_number`, `tier_discount`, alamat | 1—N orders |
| `categories` | nama, slug | 1—N books |
| `books` | kode_sku, judul, penulis, penerbit, isbn, sinopsis, harga, stok (agregat), cover_url, aktif, is_preorder, po_label, detail Gramedia | N—1 category, N—N promotions, 1—N order_items |
| `orders` | no_order, user_id, nama_pembeli, alamat, metode_bayar (transfer/cod), total, `shipping_cost`, `is_dropship`, `warehouse_origin`, status, ekspedisi, ongkir_estimasi | 1—N items, 1—0..1 dropshipper |
| `order_items` | order_id, book_id, qty, `price_original`, `promo_discount_amount`, `tier_discount_amount`, `price_final` | N—1 books |
| `promotions` | promo_name, `promo_type` (percentage/fixed/bundle), discount_percentage, promo_value, bundle_qty, start_date, end_date, is_active | N—N books (pivot `promotion_book`) |
| `inventory_stocks` | book_id, `stock_malang`, `stock_sidoarjo`, `stock_defect` | 1—1 books |
| `inventory_movements` | book_id, from_warehouse, to_warehouse, qty, type (transfer/in/out/defect), reference (order_id), user_id, created_at | audit trail |
| `cash_flows` | order_id, entry_date, flow_type (revenue/shipping/refund), amount, description | N—1 orders |
| `dropshippers` | order_id, user_id, end_customer_name, end_customer_whatsapp, end_customer_address | 1—1 orders |

**Enumerasi status order**: `menunggu_konfirmasi → diproses → dikirim → selesai`, plus `batal`.

---

## 9. Non-Functional Requirements

| Kategori | Requirement |
|---|---|
| **Keamanan** | Autentikasi session + CSRF; otorisasi `is_admin` di **backend** (middleware + policy), bukan hanya UI; validasi semua input via FormRequest; password di-hash. |
| **Performa** | Halaman list (buku/order/cash flow) memuat < 2 detik untuk 10.000+ baris (indexing + pagination); dashboard memakai query agregat efisien. |
| **Reliabilitas** | Side-effect order selesai (stok + cash flow) **atomik**; transisi status idempotent. |
| **Auditability** | Setiap mutasi stok & entry kas memiliki referensi (`order_id`, `user_id`, timestamp). |
| **Responsive** | UI dapat dipakai di tablet & laptop; mobile adalah bonus. |
| **Konsistensi** | Aturan harga/stok terpusat di Service layer; dijamin test lintas aplikasi. |
| **Code quality** | PSR-12 + Pint; komponen Vue reusable; test coverage untuk semua aturan bisnis (BR-01 s.d. BR-08). |

---

## 10. UI/UX & Design System

### 10.1 Prinsip Umum
- Antarmuka **konsisten, bersih, dan informatif** — prioritas kecepatan operasional admin (scan cepat, aksi 1–2 klik).
- Menggunakan **Tailwind CSS 4** sebagai satu-satunya sumber token desain (warna, spacing, radius, shadow, font) — tidak ada nilai hardcoded di komponen.
- Seluruh halaman panel admin mengikuti **design system yang sama** (komponen reusable `DataTable`, `FormField`, `Badge`, `Button`, `Modal`).

### 10.2 Default Theme: Light (Wajib)
| ID | Requirement |
|---|---|
| DSGN-01 | **Default theme = light** (`prefers-color-scheme` dan `data-theme` default ke `light`); halaman login, dashboard, dan seluruh modul tampil dalam tema terang saat pertama kali dibuka. |
| DSGN-02 | Kontras teks vs latar memenuhi WCAG AA (min. 4.5:1 untuk teks normal) pada tema light. |
| DSGN-03 | Tidak ada pemaksaan dark mode; dark mode (opsional) **hanya** sebagai fitur preferensi pengguna di fase berikutnya (v2.0) dan harus eksplisit di-toggle, bukan mengikuti sistem. |
| DSGN-04 | Token warna tema light didefinisikan terpusat di Tailwind config (mis. `primary`, `surface`, `border`, `text-muted`) sehingga konsisten di semua halaman. |
| DSGN-05 | Status/warna semantik konsisten: sukses (`selesai`, stok masuk) = hijau, peringatan (`menunggu_konfirmasi`, stok menipis) = kuning/amber, error/danger (`batal`, stok negatif) = merah, info (`diproses`, `dikirim`) = biru. |

### 10.3 Spacing Scale — Margin & Padding (Wajib)
Seluruh jarak memakai **skala 4px** Tailwind (`0, 1, 2, 3, 4, 5, 6, 8, 10, 12, 16, 20, 24`). Dilarang nilai arbitrer (mis. `p-[13px]`) kecuali kasus khusus yang disetujui.

| Konteks | Spacing yang Dipakai |
|---|---|
| Margin halaman (page container) | `p-6` (24px) di desktop, `p-4` (16px) di layar < 768px |
| Gap antar kartu/panel di dashboard | `gap-4` (16px) |
| Padding kartu/panel | `p-4` (16px) — default; `p-6` (24px) untuk kartu statistik utama |
| Padding sel tabel (DataTable) | `px-4 py-3` (16px × 12px) |
| Gap antar baris tabel | `divide-y` dengan `divide-gray-100` (1px), tanpa margin ekstra |
| Gap antar field dalam form | `space-y-4` (16px) antar blok; `grid gap-4` (16px) antar kolom |
| Padding input & select | `px-3 py-2` (12px × 8px) |
| Padding tombol | `px-4 py-2` (16px × 8px) untuk ukuran `md`; tombol ikon `p-2` (8px) |
| Margin antar section heading | `mb-4` (16px) antara judul & konten |
| Margin badge/avatar | `ml-2` (8px) dari teks |
| Radius | `rounded-lg` (8px) kartu/tabel, `rounded-md` (6px) input/tombol, `rounded-full` badge |
| Ketinggian baris | `min-h-10` (40px) untuk kontrol interaktif agar mudah diklik |

### 10.4 Aturan Tambahan
- **Overflow & layout**: tabel dan form tidak boleh meluber keluar viewport; gunakan `overflow-x-auto` pada tabel, kolom aksi `whitespace-nowrap`.
- **Empty state**: setiap daftar kosong menampilkan ilustrasi/ikon + pesan singkat + tombol aksi utama (mis. "Buat Buku Pertama").
- **Loading & feedback**: tombol submit menampilkan state loading; aksi sukses/gagal memberi notifikasi toast; error form ditampilkan inline di bawah field.
- **Tipografi**: judul halaman `text-xl font-semibold`, body `text-sm`, data angka/uang memakai `tabular-nums`.
- **QA design**: sebelum rilis, verifikasi margin/padding & tema light pada ukuran layar 375px (mobile), 768px, 1280px, 1920px.

---

## 11. User Flows Utama

### 11.1 Proses Pesanan (Order Fulfillment)
1. Customer submit checkout di storefront → order dibuat `menunggu_konfirmasi` + pesan WhatsApp ke admin.
2. Admin buka panel → **Pesanan** → filter `menunggu_konfirmasi`.
3. Admin review item & data customer → isi **ongkir final** + pilih **ekspedisi** + pilih **gudang asal** → ubah status **diproses**.
4. Admin kirim barang → status **dikirim**.
5. Customer terima → status **selesai** → sistem otomatis: kurangi stok gudang asal + catat cash flow (revenue + shipping).
6. *(Alternatif)* Order gagal → status **batal** (tanpa side-effect).

### 11.2 Buat Order Manual (dari WhatsApp)
1. Admin klik **Buat Pesanan** → pilih customer (atau isi nama pembeli manual).
2. Tambah item: cari buku → pilih qty → sistem hitung harga (promo + tier) otomatis.
3. Isi alamat, metode bayar, ekspedisi → simpan (status `menunggu_konfirmasi`).

### 11.3 Mutasi Stok
1. Admin buka **Inventori** → pilih buku.
2. Pilih tipe mutasi (transfer/in/out/defect) + gudang + qty.
3. `InventoryService` memvalidasi saldo, mencatat movement + audit trail, sinkronisasi `books.stok`.
4. Defect: stok dipindah ke `stock_defect` → tidak lagi tersedia untuk dijual.

---

## 12. Acceptance Criteria

| # | Kriteria | Cara verifikasi |
|---|---|---|
| AC-01 | Non-admin (customer) tidak dapat mengakses route `/admin/**` | Test middleware + manual login |
| AC-02 | Admin membuat buku baru → muncul di storefront dengan harga sama | Test integrasi + manual |
| AC-03 | Promo aktif (global & per-buku, 3 tipe) mengubah harga sesuai aturan | Test `PricingService` |
| AC-04 | Order selesai → stok gudang asal berkurang & 2 entry cash flow dibuat; diulang tidak menggandakan | Test `AccountingService` + `InventoryService` |
| AC-05 | Stok tidak bisa negatif; stok defect tidak dijual | Test validasi mutasi |
| AC-06 | Buku ber-riwayat order tidak bisa dihapus (error + guidance) | Test model |
| AC-07 | Cash flow read-only dengan referensi order yang dapat ditelusuri | Test akses + audit manual |
| AC-08 | Seluruh suite test hijau: backend (Pest/PHPUnit) + FE (Vitest) | `php artisan test` + `npm run test` |
| AC-09 | UI responsive & seluruh modul (dashboard, buku, kategori, pelanggan, order, promo, inventori, cash flow) dapat diakses dari sidebar | QA manual checklist |
| AC-10 | Seluruh halaman memakai tema **light sebagai default** dan mengikuti spacing scale (margin/padding) yang telah ditetapkan — tidak ada nilai arbitrer | QA manual + audit token desain (4 breakpoint: 375/768/1280/1920px) |

---

## 13. Milestone (Estimasi 6–8 Minggu, 1 Developer)

| Fase | Isi | Durasi |
|---|---|---|
| **F0 — Fondasi** | Setup Laravel 12 + Vite + Inertia + Vue 3 + Tailwind 4; layout admin (sidebar, topbar); auth + middleware admin; migrasi DB → PostgreSQL | 4 hari |
| **F1 — Katalog** | Halaman & CRUD Buku + Kategori; upload cover; SKU otomatis; proteksi hapus | 1 minggu |
| **F2 — Pelanggan & Harga** | Halaman Pelanggan; wiring `PricingService`; tampilan harga & diskon tier | 5 hari |
| **F3 — Pesanan** | List/detail/buat/ubah status order; isi ongkir & gudang asal; integrasi stok + cash flow saat selesai | 1.5 minggu |
| **F4 — Promosi** | CRUD 3 tipe promo + attach buku + toggle; validasi tanggal | 5 hari |
| **F5 — Inventori** | Stok per gudang, mutasi + audit trail, peringatan stok menipis | 1 minggu |
| **F6 — Keuangan & Dropship** | Cash flow (read-only + laporan + widget), info dropship | 5 hari |
| **F7 — Dashboard & Polish** | Statistik, grafik, pesanan terbaru, low stock; QA responsive + audit design system (margin/padding & tema light); acceptance test; deploy | 1 minggu |

**Gate per fase**: semua test hijau + demo ke stakeholder sebelum lanjut.

---

## 14. Risiko & Mitigasi

| Risiko | Dampak | Mitigasi |
|---|---|---|
| Migrasi Filament → Vue memakan waktu lebih lama dari estimasi | Delay rilis | Reuse service layer; mulai dari modul baca (list/detail), lalu form |
| Konsistensi harga antara 2 aplikasi | Konflik data harga | `PricingService` identik + test matrix status × promo × qty |
| Berbagi skema DB | Migrasi bentrok | Migrasi dijalankan sekali; dokumentasi sinkronisasi; CI menjalankan migrate:fresh di env test |
| Side-effect ganda saat order selesai | Stok & kas salah | Guard transisi status + transaksi DB atomik + test idempotensi |
| Data besar memperlambat list | UX buruk | Indexing kolom filter/sort + pagination server-side |

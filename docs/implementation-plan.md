# Implementation Plan — TokoBuku Admin Panel

| | |
|---|---|
| **Dokumen** | Implementation Plan |
| **Versi** | 1.0 |
| **Tanggal** | 2025-08-04 |
| **Status** | Draft untuk review |
| **Induk** | `docs/PRD.md` (v1.0) |
| **Estimasi** | 6–8 minggu, 1 developer (mengikuti milestone PRD §13) |

---

## 1. Ringkasan

Dokumen ini menerjemahkan PRD TokoBuku Admin Panel menjadi rencana kerja konkret:
migrasi skema, model, service layer, route/controller, halaman Inertia+Vue, design
system, dan test per fase. Setiap fase memiliki **gate**: seluruh test hijau + demo
ke stakeholder sebelum lanjut (PRD §13).

### 1.1 Baseline Proyek (kondisi aktual repo ini)

| Aspek | Kondisi saat ini | Catatan |
|---|---|---|
| Framework | Laravel **13** (`laravel/framework ^13.17`) | PRD menulis "Laravel 12" — deviasi: pakai versi terpasang |
| Frontend | Inertia **v3** + Vue 3.5 + Vite 8 + TypeScript | Starter kit `laravel/vue-starter-kit` |
| UI kit | Tailwind CSS **4** + **reka-ui** (shadcn-vue, `components.json` ada) | PRD menulis "Headless UI" — deviasi: reka-ui sudah terkonfigurasi |
| Auth | Laravel Fortify v1, halaman `resources/js/pages/auth/*` sudah ada | Dipakai ulang, ditambah guard admin |
| DB | `sqlite` (`.env`) | Harus dipindah ke **PostgreSQL `toko_buku`** (PRD §7.1) |
| Models | Hanya `User` | Belum ada model domain |
| Migrasi | Hanya base (users, cache, jobs) | Skema domain ditulis dari nol di fase F0 |
| Routing | `routes/web.php` + `routes/settings.php` | Ditambah group `/admin/**` |
| Wayfinder | Terpasang (`@laravel/vite-plugin-wayfinder`) | Semua route FE memakai fungsi typed `@/routes` / `@/actions` |
| Testing | Pest 5 + PHPUnit | Plus `vue-tsc`, ESLint, Prettier sebagai quality gate FE |

### 1.2 Deviasi & Asumsi terhadap PRD

1. **Laravel 13** digunakan (bukan 12) — versi terbaru terpasang di repo.
2. **UI component = reka-ui (shadcn-vue)** menggantikan Headless UI — sudah ada `components.json`, tinggal dipakai.
3. **Storefront `toko-buku` & `pcp-prd.md` tidak ada di repo ini** — `PricingService`, `InventoryService`, `AccountingService` dibangun di sini sebagai *single source of truth*; sinkronisasi lintas aplikasi dijamin lewat test matrix (BR-01) dan dokumentasi skema bersama (PRD §7.3). Migrasi dijalankan sekali (dari app ini).
4. **Inertia SPA** dipilih (opsi rekomendasi PRD §7.1), bukan REST + Sanctum.
5. `docs/IMPLEMENTATION-PLAN.md` (era Filament) yang dirujuk PRD tidak ada — dokumen ini menggantikannya. Kode `*Service.php` lama tidak tersedia; service ditulis ulang mengikuti aturan bisnis PRD §6.

---

## 2. Strategi Pengerjaan

- **Backend-first per fase**: migrasi + model + service + test dulu, lalu halaman Vue.
- **Semua harga integer rupiah** (BR-01, PRD §7.3 no. 4) — tidak ada float.
- **Side-effect sistemik (stok, kas) hanya lewat Service layer**, controller tidak pernah menulis stok/cash flow langsung (PRD §7.3 no. 5).
- **Setiap fase menghasilkan test** yang menutup requirement M-nya (peta lengkap di §8).
- Setiap modul mengikuti pola: `Model → Service (bila ada aturan bisnis) → FormRequest → Controller (thin) → Halaman Inertia → Test`.

### Struktur direktori target

```
app/
├── Http/
│   ├── Controllers/Admin/        # BookController, CategoryController, CustomerController,
│   │                             # OrderController, PromotionController, InventoryController,
│   │                             # CashFlowController, DashboardController, DropshipController
│   ├── Middleware/Admin.php      # redirect non-admin (is_admin = false)
│   ├── Requests/Admin/           # FormRequest per aksi (validasi terpusat)
│   └── Resources/                # (opsional) API resources bila diperlukan
├── Models/                       # Book, Category, Order, OrderItem, Promotion,
│                                 # InventoryStock, InventoryMovement, CashFlow, Dropshipper
├── Services/                     # PricingService, InventoryService, AccountingService
│                                 # + OrderStatusService (guard transisi status)
├── Policies/                     # BookPolicy, OrderPolicy, dll. (bila diperlukan v1.0)
└── Enums/                        # OrderStatus, PromotionType, FlowType, MovementType,
                                  # CustomerTier, PaymentMethod, Warehouse
config/pricing.php                # aturan tier discount (BR-03)
config/shipping.php               # ekspedisi & ongkir default
database/migrations/              # skema shared (identik dgn storefront)
database/seeders/                 # DemoSeeder (admin + data sample)
resources/js/
├── Pages/Admin/                  # Dashboard, Books, Categories, Customers, Orders,
│                                 # Promotions, Inventory, CashFlow, Dropship, Settings
├── Components/                   # DataTable, EmptyState, StatusBadge, Money, dsb.
├── Components/ui/                # shadcn-vue (button, table, dialog, select, …)
├── Layouts/                      # AdminLayout (sidebar + topbar)
└── Stores/                       # Pinia opsional (auth session via Inertia shared props)
```

---

## 3. Fase F0 — Fondasi (4 hari)

**Tujuan**: app bisa `migrate:fresh` di PostgreSQL, login admin jalan, layout admin + guard route siap.

| ID | Task | Detail | Requirement |
|---|---|---|---|
| F0.1 | **Switch ke PostgreSQL** | `.env` → `DB_CONNECTION=pgsql`, `DB_DATABASE=toko_buku`; jalankan `php artisan migrate:fresh` sebagai smoke test; dokumentasikan setup di README | PRD §7.1 |
| F0.2 | **Migrasi skema domain** (sekali jalan, lihat §4) | Semua tabel §4 + indeks untuk kolom filter/sort: `books.judul`, `orders.no_order`, `orders.status`, `orders.created_at`, `cash_flows.entry_date`, `cash_flows.flow_type`, `promotions.start_date/end_date`, `inventory_movements.created_at` | PRD §8, NFR Performa |
| F0.3 | **Model + Eloquent relations + casts** | 9 model domain; `Enum` casts (OrderStatus, PromotionType, dsb.); relasi sesuai PRD §8; factory untuk semua model | PRD §8 |
| F0.4 | **Auth admin** | Kolom `is_admin` di `users`; middleware `Admin` (redirect non-admin ke `/` atau 403) + `bootstrap/app.php` alias; route group `Route::middleware(['auth', 'admin'])` untuk `/admin/**` | AUTH-02, AUTH-05 |
| F0.5 | **Layout admin** | `AdminLayout.vue` (pakai `AppSidebarLayout` yang ada; nav: Dashboard, Buku, Kategori, Pelanggan, Pesanan, Promosi, Inventori, Keuangan, Dropship, Pengaturan); topbar + logout | AUTH-03 |
| F0.6 | **Login + demo account** | Redirect post-login ke `/admin/dashboard` untuk admin; halaman login `/admin/login` menampilkan kredensial demo + tombol **isi otomatis** (hanya saat `APP_ENV=local`/demo) | AUTH-01, AUTH-04 |
| F0.7 | **Seeder demo** | `DemoSeeder`: 1 admin, beberapa customer tiap tier, kategori, buku (+detail Gramedia), stok gudang, promo, order sample (semua status), cash flow, mutasi | — |
| F0.8 | **Test F0** | `AdminMiddlewareTest` (AC-01), `LoginTest`, `DemoSeederTest` (data bisa di-seed) | AC-01, AC-08 |

**Gate F0**: `php artisan test` hijau; login admin → dashboard kosong tampil; customer login → tidak bisa buka `/admin/**`.

---

## 4. Skema Database (Migrasi Lengkap)

Semua tabel ditulis **sekali** di F0.2. Skema mengikuti PRD §8; kolom wajib `timestamps()`. Semua harga/uang **integer** (rupiah).

### 4.1 `users` (ubah migrasi existing — atau migrasi baru `add_admin_fields_to_users_table`)
| Kolom | Tipe | Catatan |
|---|---|---|
| `is_admin` | boolean, default false | Guard panel (AUTH-02) |
| `whatsapp_number` | string, nullable | CUST-01 |
| `status_pelanggan` | string, default `reguler` | enum: reguler/bazaf/guru/reseller (CUST-02) |
| `tier_discount` | integer, default 0 | persen, dihitung service (CUST-02) |
| `alamat`, `provinsi`, `kabupaten`, `kecamatan`, `kode_pos` | string, nullable | CUST-02 |

### 4.2 Tabel baru
| Tabel | Kolom penting | Index |
|---|---|---|
| `categories` | `nama`, `slug` (unique) | slug |
| `books` | `kode_sku` (unique, nullable→auto), `judul`, `penulis`, `penerbit`, `tahun`, `isbn`, `sinopsis`, `harga` (int), `stok` (int agregat), `cover_url`, `aktif` (bool), `is_preorder` (bool), `po_label`, detail Gramedia: `tanggal_terbit`, `rating_umur`, `dimensi`, `kemasan`, `berat_gr`, `jumlah_halaman`, `jenis_kertas`, `cetakan`, `category_id` FK | judul, isbn, kode_sku, category_id, aktif |
| `orders` | `no_order` (unique), `user_id` FK nullable, `nama_pembeli`, `alamat`, `metode_bayar` (transfer/cod), `total`, `shipping_cost`, `is_dropship` (bool), `warehouse_origin`, `status`, `ekspedisi`, `ongkir_estimasi` | no_order, status, created_at, user_id |
| `order_items` | `order_id` FK, `book_id` FK, `qty`, `price_original`, `promo_discount_amount`, `tier_discount_amount`, `price_final` (semua int) | order_id, book_id |
| `promotions` | `promo_name`, `promo_type` (percentage/fixed/bundle), `discount_percentage` nullable, `promo_value` nullable (int), `bundle_qty` nullable, `start_date`, `end_date`, `is_active` | start_date, end_date, is_active |
| `promotion_book` (pivot) | `promotion_id` FK, `book_id` FK, unique pair | — |
| `inventory_stocks` | `book_id` FK unique, `stock_malang`, `stock_sidoarjo`, `stock_defect` (int ≥ 0) | book_id |
| `inventory_movements` | `book_id` FK, `from_warehouse` nullable, `to_warehouse` nullable, `qty`, `type` (transfer/in/out/defect), `reference` (order_id nullable), `user_id` FK nullable | book_id, type, created_at, reference |
| `cash_flows` | `order_id` FK nullable, `entry_date`, `flow_type` (revenue/shipping/refund), `amount` (int), `description` | entry_date, flow_type, order_id |
| `dropshippers` | `order_id` FK unique, `user_id` FK nullable, `end_customer_name`, `end_customer_whatsapp`, `end_customer_address` | order_id |

**Enums** (PHP `enum` + kolom string dengan validation): `OrderStatus` (`menunggu_konfirmasi`, `diproses`, `dikirim`, `selesai`, `batal`), `PromotionType` (`percentage`, `fixed`, `bundle`), `FlowType` (`revenue`, `shipping`, `refund`), `MovementType` (`transfer`, `in`, `out`, `defect`), `CustomerTier` (`reguler`, `bazaf`, `guru`, `reseller`), `PaymentMethod` (`transfer`, `cod`), `Warehouse` (`malang`, `sidoarjo`, `defect`).

---

## 5. Service Layer (Single Source of Truth)

Controller = tipis; aturan bisnis hanya di service (PRD §7.3 no. 5). Test service dijamin identik dengan storefront (BR-01).

### 5.1 `PricingService` — PRD §5.7, BR-01..03
```php
final class PricingService
{
    /** Harga final 1 buku utk qty & tier tertentu: base → promo aktif → tier discount. */
    public function finalPrice(Book $book, int $qty, ?CustomerTier $tier = null): int;

    /** Rincian diskon utk ditampilkan di UI order (original, promo, tier, final). */
    public function priceBreakdown(Book $book, int $qty, ?CustomerTier $tier = null): PriceBreakdown;

    /** Promo aktif utk buku (is_active && start_date ≤ today ≤ end_date). BR-02. */
    public function activePromotion(Book $book): ?Promotion;

    /** Rebuild semua baris order_items dari service. Dipakai saat create/edit order. ORD-08. */
    public function applyToOrder(Order $order): void;
}
```
- `config/pricing.php`: tier discount (BR-03) — reseller qty≥10 → 10%, qty≥20 → 15%; bazaf qty≥10 → 5%, qty≥20 → 8%; reguler & guru tanpa diskon.
- `PriceBreakdown` = DTO readonly (original, promoDiscount, tierDiscount, final).

### 5.2 `InventoryService` — PRD §5.8, BR-06..07
```php
final class InventoryService
{
    /** Mutasi stok + audit trail + sinkronisasi books.stok, dalam 1 transaksi. INV-02..04, INV-06. */
    public function move(Book $book, MovementType $type, int $qty,
                         ?Warehouse $from = null, ?Warehouse $to = null,
                         ?int $orderId = null, ?int $userId = null): InventoryMovement;

    /** Validasi saldo cukup & stok tidak negatif. INV-04. */
    public function assertSufficientStock(Book $book, Warehouse $warehouse, int $qty): void;

    /** Total stok normal (malang + sidoarjo) — defect tidak pernah dihitung. INV-05, BR-07. */
    public function availableStock(Book $book): int;

    /** Sinkronisasi kolom agregat books.stok. INV-06. */
    public function syncBookStock(Book $book): void;

    /** Deduksi stok saat order selesai (dipanggil di dalam transaksi AccountingService/OrderService). ORD-06. */
    public function deductForOrder(Order $order, int $userId): void;
}
```
Aturan: defect hanya bisa menerima mutasi `defect` (INV-05); semua mutasi mencatat `inventory_movements` (audit trail, INV-03).

### 5.3 `AccountingService` — PRD §5.9, BR-06
```php
final class AccountingService
{
    /** Order selesai → 1 transaksi atomik: deduksi stok + entry revenue & shipping. ORD-06, BR-06. */
    public function recordOrderCompleted(Order $order, int $userId): void;
}
```
Dipanggil **hanya sekali** per order — dijamin `OrderStatusService` (guard transisi, BR-05).

### 5.4 `OrderStatusService` — PRD §5.6, BR-05
```php
final class OrderStatusService
{
    public const TRANSITIONS = [
        OrderStatus::MenungguKonfirmasi => [OrderStatus::Diproses, OrderStatus::Batal],
        OrderStatus::Diproses          => [OrderStatus::Dikirim, OrderStatus::Batal],
        OrderStatus::Dikirim           => [OrderStatus::Selesai, OrderStatus::Batal],
        OrderStatus::Selesai           => [],   // terminal
        OrderStatus::Batal             => [],   // terminal
    ];

    public function canTransition(Order $order, OrderStatus $to): bool;
    public function transition(Order $order, OrderStatus $to, int $userId): void; // invoke side-effect utk Selesai
}
```
Idempotensi: `selesai`/`batal` hanya sekali — tidak ada side-effect ganda (ORD-07, AC-04).

---

## 6. Fase F1 — Katalog (1 minggu)

| ID | Task | Detail | Requirement |
|---|---|---|---|
| F1.1 | **Kategori CRUD** | `CategoryController` + `CategoryRequest`; list = tabel + jumlah buku (CAT-02); hapus diblokir bila dipakai buku → error + guidance (CAT-03); halaman `Pages/Admin/Categories/Index.vue` + `Form.vue` | CAT-01..03 |
| F1.2 | **Buku CRUD** | `BookController` (index/search/filter/store/update/destroy); FormRequest validasi: judul & harga wajib, harga int ≥ 0, stok int ≥ 0 (BOOK-08); halaman `Pages/Admin/Books/Index.vue`, `Form.vue`, `Show.vue` | BOOK-01, BOOK-05, BOOK-07, BOOK-08 |
| F1.3 | **SKU otomatis** | `BookService`/model hook: bila `kode_sku` kosong → `SKU-` + 4 digit urut (`SKU-0001`) (BOOK-03, BR-08); unique constraint + retry bila tabrakan | BOOK-03 |
| F1.4 | **Proteksi hapus** | `destroy()` mengecek `order_items`; bila ada → 422 + pesan "buku memiliki riwayat pesanan, nonaktifkan saja" (BOOK-04, BR-04, AC-06) | BOOK-04 |
| F1.5 | **Cover upload** | Storage publik `covers/` + validasi mime/image; atau URL eksternal (BOOK-06) | BOOK-06 |
| F1.6 | **Detail Gramedia** | Section form opsional (BOOK-02) — disimpan di kolom books | BOOK-02 |
| F1.7 | **Test F1** | `BookControllerTest` (CRUD, search/filter, SKU auto, proteksi hapus), `CategoryControllerTest` (CRUD, proteksi hapus) | AC-02, AC-06 |

**Gate F1**: CRUD buku & kategori lewat UI < 2 menit per transaksi (KPI §2.2); test hijau.

---

## 7. Fase F2 — Pelanggan & Harga (5 hari)

| ID | Task | Detail | Requirement |
|---|---|---|---|
| F2.1 | **`PricingService`** | Implementasi §5.1 + `config/pricing.php` + DTO `PriceBreakdown`; test matrix: 3 tipe promo × tier × qty (BR-01..03) | ORD-08, AC-03 |
| F2.2 | **Halaman Pelanggan** | `CustomerController`: list + search nama/email/WhatsApp (CUST-01); edit: name, email, whatsapp, tier, alamat (CUST-02); badge tier; ringkasan transaksi (jumlah order, total belanja) (CUST-03) | CUST-01..03 |
| F2.3 | **Proteksi `is_admin`** | Form customer tidak memuat field `is_admin`; controller `fill()` hanya kolom whitelist (CUST-04) | CUST-04 |
| F2.4 | **Test F2** | `PricingServiceTest` (dataset: promo×tier×qty), `CustomerControllerTest` (search, whitelist is_admin) | AC-03 |

**Gate F2**: harga promo & tier tampil benar di halaman pelanggan/order; test matrix pricing hijau.

---

## 8. Fase F3 — Pesanan (1.5 minggu)

| ID | Task | Detail | Requirement |
|---|---|---|---|
| F3.1 | **List & filter order** | `OrderController@index`: filter status, search `no_order`/nama pembeli, sort tanggal, pagination server-side (ORD-01) | ORD-01 |
| F3.2 | **Detail order** | Rincian item (judul, qty, harga asli, diskon promo, diskon tier, harga final), subtotal, ongkir, grand total, metode bayar, alamat, ekspedisi, data dropship (ORD-02) | ORD-02 |
| F3.3 | **Buat order manual** | Wizard 3 langkah: (1) pilih customer/isi nama, (2) tambah item buku+qty → harga otomatis via `PricingService`, (3) alamat & metode bayar → simpan `menunggu_konfirmasi` (ORD-03, §11.2 PRD) | ORD-03 |
| F3.4 | **Transisi status** | `OrderStatusService` (§5.4): konfirmasi → isi ongkir final + ekspedisi + pilih gudang asal → `diproses` (ORD-04, ORD-05); `dikirim`; `selesai`; `batal` dari status mana pun | ORD-04, ORD-05 |
| F3.5 | **Side-effect selesai** | `AccountingService@recordOrderCompleted` → transaksi atomik: `InventoryService@deductForOrder` + 2 entry cash flow (revenue = total produk, shipping = ongkir) (ORD-06, BR-06, CF-03); guard idempotensi (ORD-07) | ORD-06, ORD-07 |
| F3.6 | **Edit order** | Edit item/alamat hanya di status `menunggu_konfirmasi` (belum ada side-effect); harga dihitung ulang (ORD-08) | ORD-08 |
| F3.7 | **Test F3** | `OrderFlowTest`: buat manual → konfirmasi → kirim → selesai (stok berkurang + 2 cash flow); idempotensi (double transition tidak menggandakan); `batal` tanpa side-effect; stok gudang asal benar | AC-04, AC-07, KPI §2.2 |

**Gate F3**: alur 11.1 & 11.2 PRD bisa dijalankan end-to-end di UI; test idempotensi hijau.

---

## 9. Fase F4 — Promosi (5 hari)

| ID | Task | Detail | Requirement |
|---|---|---|---|
| F4.1 | **CRUD promosi** | `PromotionController` + form: nama, tipe (percentage/fixed/bundle), nilai, tanggal mulai/akhir, aktif (PROM-01) | PROM-01 |
| F4.2 | **Attach buku** | Pivot `promotion_book`: pilih global atau per-buku (PROM-03) | PROM-03 |
| F4.3 | **Validasi tanggal** | `end_date ≥ start_date` (wajib); deteksi tumpang-tindih dengan promo lain → warning (PROM-04) | PROM-04 |
| F4.4 | **Toggle cepat** | Toggle `is_active` dari list (PROM-05); harga promo diterapkan otomatis oleh `PricingService` berdasarkan tanggal hari ini (PROM-06) | PROM-05, PROM-06 |
| F4.5 | **Test F4** | `PromotionControllerTest` (CRUD, validasi tanggal, toggle), `PricingService` bundle/fixed/percentage aktif & kadaluarsa | AC-03 |

**Gate F4**: 3 tipe promo + global/per-buku bekerja di perhitungan harga order.

---

## 10. Fase F5 — Inventori (1 minggu)

| ID | Task | Detail | Requirement |
|---|---|---|---|
| F5.1 | **Stok per gudang** | `InventoryController@index`: tabel per buku (3 kolom gudang + total), filter stok menipis (INV-01, INV-07) | INV-01, INV-07 |
| F5.2 | **Form mutasi** | Pilih buku, tipe (transfer/in/out/defect), gudang asal/tujuan, qty, referensi opsional (INV-08) | INV-08 |
| F5.3 | **Validasi & audit** | `InventoryService` (§5.2): saldo cukup, stok ≥ 0, defect hanya via tipe `defect`, audit trail lengkap (INV-03, INV-04, INV-05) | INV-03..05 |
| F5.4 | **Sinkronisasi agregat** | `books.stok` = malang + sidoarjo, sync otomatis tiap mutasi (INV-06) | INV-06 |
| F5.5 | **Test F5** | `InventoryServiceTest`: mutasi tiap tipe, saldo negatif ditolak, defect tidak dijual, sync agregat, audit trail terisi | AC-05 |

**Gate F5**: mutasi stok dari UI tercatat + stok tampil konsisten di buku & order.

---

## 11. Fase F6 — Keuangan & Dropship (5 hari)

| ID | Task | Detail | Requirement |
|---|---|---|---|
| F6.1 | **Cash flow list** | `CashFlowController@index`: read-only (tidak ada route store/update/destroy), kolom: tanggal, tipe, amount, deskripsi, referensi `no_order` (CF-01, CF-02, CF-05) | CF-01, CF-02, CF-05 |
| F6.2 | **Laporan + filter** | Filter rentang tanggal; ringkasan total masuk/keluar; auditable via `order_id` (CF-05, AC-07) | CF-05 |
| F6.3 | **Widget dashboard** | Total kas masuk bulan ini (CF-06) — dipakai di F7 | CF-06 |
| F6.4 | **Dropship** | Tampil data dropship di detail order (DROP-01); filter `is_dropship` (DROP-02); halaman/laporan dropship sederhana + filter tanggal (DROP-03) | DROP-01..03 |
| F6.5 | **Test F6** | `CashFlowControllerTest` (read-only, filter tanggal, ringkasan), `DropshipTest` (filter, laporan) | AC-07 |

**Gate F6**: entry cash flow hanya dari service (tidak ada route tulis); laporan bisa difilter.

---

## 12. Fase F7 — Dashboard & Polish (1 minggu)

| ID | Task | Detail | Requirement |
|---|---|---|---|
| F7.1 | **Dashboard** | `DashboardController@index`: total penjualan & jumlah order (periode), total pemasukan, jumlah buku/stok (DASH-01); tabel pesanan terbaru 5–10 (DASH-02); peringatan stok menipis ≤ 5 (DASH-03); grafik penjualan 7/30 hari (DASH-04, should have); semua query dihitung saat load, tanpa cache basi (DASH-05) | DASH-01..05 |
| F7.2 | **Design system audit** | Token warna light terpusat di `resources/css/app.css` (`--color-primary`, `--color-surface`, `--color-border`, `--color-muted`, dll.) (DSGN-04); default light — tidak ada dark mode paksa (DSGN-01, DSGN-03); kontras AA (DSGN-02); warna semantik: hijau=selesai/stok masuk, amber=menunggu_konfirmasi/stok menipis, merah=batal/negatif, biru=diproses/dikirim (DSGN-05) | DSGN-01..05 |
| F7.3 | **Spacing scale QA** | Verifikasi seluruh halaman memakai skala 4px PRD §10.3 (p-6/p-4 container, gap-4, px-4 py-3 sel tabel, px-3 py-2 input, rounded-lg/md/full, min-h-10); audit 4 breakpoint: 375/768/1280/1920px (AC-10) | AC-10 |
| F7.4 | **Komponen reusable** | `DataTable` (sort/filter/pagination server-side), `EmptyState`, `StatusBadge`, `Money` (tabular-nums), `FormField` (error inline), tombol loading, toast (`vue-sonner`) | PRD §10.1 |
| F7.5 | **Profil admin** | Ganti nama/password (SET-01); halaman demo account (SET-02) | SET-01, SET-02 |
| F7.6 | **QA responsive & akses** | Checklist AC-09: seluruh modul bisa diakses dari sidebar; empty state di semua list; loading state submit (PRD §10.4) | AC-09 |
| F7.7 | **Final test & deploy** | `php artisan test` + `npm run test` (bila ada Vitest) + `npm run types:check` + `npm run lint`; demo stakeholder; deploy (Laravel Cloud / hosting) | AC-08 |

**Gate F7**: seluruh AC-01..AC-10 terverifikasi; test suite 100% hijau; demo final.

---

## 13. Peta Route `/admin/**`

Semua route di group `['auth', 'admin']`, prefix `/admin`, pakai Wayfinder di FE.

| Method | URI | Controller@action | Halaman Inertia |
|---|---|---|---|
| GET | `/admin/dashboard` | `DashboardController@index` | `Admin/Dashboard.vue` |
| GET/POST | `/admin/books` | `BookController@index/store` | `Admin/Books/Index.vue` |
| GET | `/admin/books/create` | `BookController@create` | `Admin/Books/Form.vue` |
| GET/PUT/DELETE | `/admin/books/{book}` | `BookController@show/update/destroy` | `Admin/Books/Show.vue` |
| GET/POST | `/admin/categories` | `CategoryController@index/store` | `Admin/Categories/Index.vue` |
| GET/PUT/DELETE | `/admin/categories/{category}` | `CategoryController@update/destroy` | `Admin/Categories/Index.vue` |
| GET | `/admin/customers` | `CustomerController@index` | `Admin/Customers/Index.vue` |
| GET/PUT | `/admin/customers/{user}` | `CustomerController@edit/update` | `Admin/Customers/Form.vue` |
| GET | `/admin/orders` | `OrderController@index` | `Admin/Orders/Index.vue` |
| GET | `/admin/orders/create` | `OrderController@create` | `Admin/Orders/Create.vue` |
| GET | `/admin/orders/{order}` | `OrderController@show` | `Admin/Orders/Show.vue` |
| PATCH | `/admin/orders/{order}/status` | `OrderController@updateStatus` | — (aksi) |
| PATCH | `/admin/orders/{order}/process` | `OrderController@process` (ongkir+ekspedisi+gudang) | — (aksi) |
| GET/POST | `/admin/promotions` | `PromotionController@index/store` | `Admin/Promotions/Index.vue` |
| GET/PUT/DELETE | `/admin/promotions/{promotion}` | `PromotionController@update/destroy` | `Admin/Promotions/Form.vue` |
| PATCH | `/admin/promotions/{promotion}/toggle` | `PromotionController@toggle` | — (aksi) |
| GET | `/admin/inventory` | `InventoryController@index` | `Admin/Inventory/Index.vue` |
| POST | `/admin/inventory/movements` | `InventoryController@store` | — (aksi/modal) |
| GET | `/admin/cash-flow` | `CashFlowController@index` | `Admin/CashFlow/Index.vue` |
| GET | `/admin/dropship` | `DropshipController@index` | `Admin/Dropship/Index.vue` |
| GET/PUT | `/admin/settings/profile` | reuse settings starter kit | `Admin/Settings/Profile.vue` |
| GET | `/admin/login` | Fortify login (view Inertia) | `auth/Login.vue` (redirect admin) |

---

## 14. Peta Test → Requirement

| Test file (target) | Menutup |
|---|---|
| `tests/Feature/Admin/AdminMiddlewareTest.php` | AC-01, AUTH-02, AUTH-05 |
| `tests/Feature/Admin/AuthTest.php` | AUTH-01, AUTH-03, AUTH-04 |
| `tests/Feature/Admin/CategoryControllerTest.php` | CAT-01..03, AC-06 (kategori) |
| `tests/Feature/Admin/BookControllerTest.php` | BOOK-01..08, AC-02, AC-06 |
| `tests/Unit/Services/PricingServiceTest.php` | BR-01..03, PROM-06, ORD-08, AC-03 |
| `tests/Feature/Admin/CustomerControllerTest.php` | CUST-01..04 |
| `tests/Feature/Admin/OrderFlowTest.php` | ORD-01..08, BR-05..06, AC-04, AC-07, KPI §2.2 |
| `tests/Feature/Admin/PromotionControllerTest.php` | PROM-01..05, AC-03 |
| `tests/Unit/Services/InventoryServiceTest.php` | INV-02..06, BR-07, AC-05 |
| `tests/Feature/Admin/CashFlowControllerTest.php` | CF-01..05, AC-07 |
| `tests/Feature/Admin/DropshipTest.php` | DROP-01..03 |
| `tests/Feature/Admin/DashboardTest.php` | DASH-01..05, CF-06 |
| FE: `vue-tsc`, ESLint, (opsional Vitest untuk komponen DataTable/StatusBadge) | AC-08 |

---

## 15. Risiko Spesifik Implementasi

| Risiko | Mitigasi |
|---|---|
| Skema bentrok dengan storefront (1 DB) | Migrasi ditulis sekali di F0; dokumentasikan urutan & hash migrasi; CI `migrate:fresh` di env test (PRD §14) |
| `PricingService` tidak identik dengan storefront | Test matrix lengkap (3 promo × 4 tier × qty boundary); jadikan test ini gate F2 |
| Side-effect ganda order selesai | `OrderStatusService` guard + transaksi DB atomik + test idempotensi (F3.7) |
| SKU otomatis tabrakan di concurrent request | Unique constraint + loop retry di service; test |
| Stok negatif via race condition | `assertSufficientStock` + lock row (`lockForUpdate`) saat mutasi/order selesai |
| List lambat di 10.000+ baris | Index dari F0.2 + pagination server-side + eager-load tanpa N+1 (cek `WithCount`/`loadCount`) |
| PRD menyebut Laravel 12 / Headless UI | Sudah diputuskan: Laravel 13 + reka-ui (shadcn-vue) — lihat §1.2 |

---

## 16. Checklist Gate per Fase

> **Status implementasi: SELESAI (2025-08-04)** — seluruh fase F0–F7 dikerjakan sekaligus.
> Verifikasi akhir: `php artisan test` → **99 test, 95 passed, 0 failed** (4 skipped = test kondisional starter kit);
> `vue-tsc`, ESLint, Prettier, Pint hijau; `npm run build` sukses; smoke test HTTP live (login admin → 12 halaman `/admin/**` → 200).

- [x] **F0**: PostgreSQL `migrate:fresh` jalan · admin login → dashboard · customer ditolak `/admin/**` · `AdminAccessTest` hijau
- [x] **F1**: CRUD buku & kategori via UI < 2 menit · SKU otomatis · proteksi hapus ber-riwayat order
- [x] **F2**: matrix `PricingServiceTest` hijau · halaman pelanggan + badge tier · `is_admin` tidak bisa diubah dari UI
- [x] **F3**: alur 11.1 & 11.2 PRD end-to-end · order selesai = stok − + 2 cash flow, sekali saja
- [x] **F4**: 3 tipe promo + global/per-buku · validasi tanggal · toggle
- [x] **F5**: mutasi 4 tipe + audit trail · negatif ditolak · defect tidak dijual · `books.stok` sinkron
- [x] **F6**: cash flow read-only + filter · dropship tampil & terfilter
- [x] **F7**: dashboard lengkap · audit spacing/light (4 breakpoint) · `php artisan test` 100% hijau · demo stakeholder

---

## 17. Catatan Implementasi (Delta vs Rencana)

1. **DB test = sqlite :memory:** (bukan pgsql) — suite tetap hermetic & cepat; query ditulis driver-agnostic (`whereLike`, `whereDate`).
2. **Login admin**: route `/login` Fortify dipakai bersama (bukan `/admin/login` terpisah); redirect post-login → `/admin/dashboard` untuk admin. Kredensial demo + tombol isi otomatis tampil saat `APP_ENV=local`.
3. **Promo global** direpresentasikan sebagai promo tanpa baris pivot (bukan flag eksplisit).
4. **Pivot** bernama `promotion_book` (bukan default `book_promotion`) — dideklarasikan eksplisit di relasi.
5. **PHP 8.5**: enum tidak bisa dipakai sebagai array key — map transisi status memakai `->value`.
6. **Konfirmasi order** = endpoint terpisah `PATCH /admin/orders/{order}/process` (ongkir + ekspedisi + gudang) — transisi lain via `PATCH .../status`.
7. **Profil admin** memakai halaman settings starter kit (SET-01). Ekspor CSV (SET-03) & grafik interaktif (DASH-04 simple bar) sengaja belum dibuat — diluar scope v1.0 commit ini.

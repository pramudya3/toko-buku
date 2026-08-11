# Implementation Plan — Migrasi Primary Key ke UUID v7

| | |
|---|---|
| **Dokumen** | Implementation Plan — UUID Migration |
| **Versi** | 1.1 |
| **Tanggal** | 2026-08-10 |
| **Status** | Disetujui untuk eksekusi (belum production — disclaimer) |
| **Induk** | `docs/implementation-plan.md` |
| **Keputusan** | UUID **v7** (bawaan Laravel), semua tabel aplikasi, index dipastikan |

---

## 0. Disclaimer

Aplikasi ini **belum production**. Migrasi dilakukan berani (data dev dapat diubah),
tetapi tetap **data-preserving** dan memiliki **rollback path penuh** agar pola
migrasi ini valid bila nanti diterapkan ke data production.

---

## 1. Tujuan & Keputusan

1. Semua tabel aplikasi memakai **UUID v7** sebagai primary key — anti-enumerasi,
   aman untuk sinkronisasi/distribusi, tanpa fragmentasi index (v7 terurut waktu).
2. UUID dihasilkan oleh **fitur bawaan Laravel**: trait `HasUuids` →
   `newUniqueId()` → `Str::uuid7()` (terverifikasi di `vendor/laravel/framework` —
   tidak perlu override).
3. **Semua kolom foreign key terindex eksplisit** (Postgres tidak meng-index FK
   secara otomatis, berbeda dengan MySQL InnoDB).
4. Kode human-readable (`no_order`, `ref_code`, `kode_sku`, kode gudang) **tetap** —
   UUID hanya identitas internal.

---

## 2. Cakupan Tabel

### 2.1 Tabel aplikasi yang diubah (30 tabel)

| Grup | Tabel |
|---|---|
| Identitas | `users` |
| Katalog | `categories`, `books`, `book_editions`, `book_edition_stocks`, `promotions`, `promotion_book` |
| Transaksi | `orders`, `order_items`, `dropshippers`, `cash_flows` |
| Piutang & Retur | `receivables`, `receivable_payments`, `sales_returns`, `sales_return_items` |
| Inventori | `warehouses`, `inventory_stocks`, `inventory_movements` |
| Supplier | `suppliers`, `supplier_purchases`, `supplier_purchase_items`, `supplier_returns`, `supplier_return_items`, `supplier_payments` |
| Harga | `tier_discounts` |
| Referensi | `provinces`, `cities`, `districts`, `villages`, `settings` |

### 2.2 Tabel yang TIDAK diubah (framework-managed)

| Tabel | Alasan |
|---|---|
| `cache`, `cache_locks` | Framework, key string |
| `jobs`, `job_batches`, `failed_jobs` | Queue worker berbasis bigint — bukan data bisnis |
| `sessions` | PK `id` string (session id) |
| `password_reset_tokens` | PK `email` |
| `personal_access_tokens` | `tokenable_id` sudah kolom morph **string** → otomatis menyimpan uuid users tanpa perubahan |

---

## 3. Strategi Migration (Data-Preserving)

Satu migration utama: `2026_08_10_000004_convert_primary_keys_to_uuid.php`

### 3.1 Algoritma per tabel

```php
// 1. Tambah kolom uuid (nullable dulu) + unique index sementara
Schema::table('books', fn (Blueprint $t) => $t->uuid('uuid')->nullable()->unique());

// 2. Backfill semua baris existing (pgcrypto bawaan PostgreSQL 13+)
DB::table('books')->whereNull('uuid')->update(['uuid' => DB::raw('gen_random_uuid()')]);

// 3. Update FK di tabel ANAK (join via id int lama) — urutan induk dulu
// 4. Drop FK int lama → drop PK int → jadikan uuid sebagai PK
// 5. Buat ulang seluruh index & FK dengan tipe uuid
```

### 3.2 Urutan migrasi (dependensi)

```
Fase A — tabel induk:
  users → categories → warehouses → suppliers → promotions → books → provinces/cities/districts/villages → settings

Fase B — tabel anak level 1:
  orders (user_id, book_id?) → book_editions (book_id) → inventory_stocks (book_id, warehouse_id)
  → inventory_movements (book_id, from_warehouse_id, to_warehouse_id, user_id)
  → tier_discounts → promotion_book (promotion_id, book_id)

Fase C — tabel anak level 2:
  order_items (order_id, book_id, book_edition_id) → cash_flows (order_id)
  → dropshippers (order_id, user_id) → book_edition_stocks (book_edition_id, warehouse_id)
  → receivables (customer_id, order_id) → receivable_payments (receivable_id)
  → sales_returns (order_id, user_id) → sales_return_items (sales_return_id, order_item_id, book_id, book_edition_id)
  → supplier_purchases (supplier_id, user_id) → supplier_returns (supplier_id, supplier_purchase_id, user_id)
  → supplier_purchase_items (supplier_purchase_id, book_id)
  → supplier_return_items (supplier_return_id, book_id)
  → supplier_payments (supplier_id, supplier_purchase_id, user_id)
```

### 3.3 Rollback (`down()`)

- Buat tabel mapping `uuid_migration_map` (entity, id_int_lama, uuid_baru) selama `up()`
- `down()`: kembalikan id int dari mapping, restore FK/PK/index lama
- **Gate**: setelah `up()`, jalankan `migrate:rollback` di DB cadangan → `migrate` lagi
  (uji idempoten & rollback penuh)

### 3.4 Verifikasi data

- `migrate:fresh --seed` → seluruh test hijau
- Migrasi **DB dev yang berisi data** (buku, order, supplier, mutasi hasil testing)
  → jumlah baris per tabel sama sebelum/sesudah, relasi utuh

---

## 4. Index (permintaan: "pastikan menggunakan index")

Audit 43 kolom FK `->constrained()` (30 tabel aplikasi, dihitung dari seluruh migration) — semua wajib `->index()` eksplisit:

| Tabel | Index |
|---|---|
| `order_items` | `order_id`, `book_id`, `book_edition_id` + komposit `(order_id, book_id)` |
| `orders` | `user_id`, `created_at` |
| `cash_flows` | `order_id` + komposit `(entry_date, flow_type)` (dashboard) |
| `inventory_stocks` | unique `(book_id, warehouse_id)` |
| `inventory_movements` | `book_id`, `from_warehouse_id`, `to_warehouse_id`, `user_id` + komposit `(book_id, created_at)` (laporan mutasi) |
| `book_editions` | `book_id` + unique `(book_id, cetakan_ke)` |
| `book_edition_stocks` | unique `(book_edition_id, warehouse_id)` |
| `promotion_book` | `promotion_id`, `book_id` |
| `supplier_purchases/returns/payments` | `supplier_id`, `user_id`, `supplier_purchase_id` |
| `supplier_purchase_items` | `supplier_purchase_id`, `book_id` |
| `supplier_return_items` | `supplier_return_id`, `book_id` |
| `dropshippers` | unique `order_id`, `user_id` |
| `receivables` | `customer_id`, `order_id` |
| `receivable_payments` | `receivable_id` |
| `sales_returns` | `order_id`, `user_id` |
| `sales_return_items` | `sales_return_id`, `order_item_id`, `book_id`, `book_edition_id` |
| `tier_discounts` | (tidak ada FK — unique tier) |
| `sessions` | `user_id` (sudah ada — verifikasi) |
| Unique existing | `no_order`, `ref_code`, `kode` (warehouse/supplier/category/village dll), `isbn`, `email` — tetap |

---

## 5. Perubahan Model Eloquent

```php
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class Book extends Model
{
    use HasUuids;
}
```

- Semua model aplikasi (24 model bisnis + 4 wilayah + settings — total 29 model) ditambah `HasUuids`
- `$keyType = 'string'` & `incrementing = false` otomatis dari trait
- `newUniqueId()` bawaan framework → `Str::uuid7()` — **tanpa override**
- Route model binding, factory, relasi: berfungsi tanpa perubahan lain

---

## 6. Perubahan Frontend & Route

| Item | Detail |
|---|---|
| **Wayfinder** | Regenerate setelah route berubah → tipe param berubah `number` → `string` di 8 route: `{book}`, `{category}`, `{order}`, `{promotion}`, `{receivable}`, `{supplier}`, `{user}`, `{warehouse}` |
| **`Number(id)` di JS** | `supplier-debts/Index.vue` (5 tempat) — `Number('uuid')` = `NaN` → ganti perbandingan string |
| **Cart session** | Key `book_id` jadi string — audit `CheckoutController` (`array_key`, `min()`, payload) |
| **URL buku publik** | `/buku/{uuid}` → tambah **slug** unik (`judul` slugified) + `whereSlug` route; redirect pattern lama bila perlu |
| **`String(id)`** | Select value, `:key`, payload — sudah string-safe, tanpa perubahan |
| **Payload Inertia** | Semua `id` menjadi string — audit perbandingan `===` dengan number di komponen |

---

## 7. Test & Seeder

- `DemoSeeder`: referensi index array `$books[0]` dsb — disesuaikan agar memakai instance model
- ~240 test: assertion `id` int → string pada yang membandingkan; factory FK otomatis konsisten
- **Test baru**:
  - PK setiap model berupa UUID v7 valid (`Str::isUuid`)
  - Kolom FK terindex (cek schema/`Schema::hasIndex` per tabel)
  - Rollback migration mengembalikan struktur int

---

## 8. Fase Eksekusi

| Fase | Isi | Gate |
|---|---|---|
| **A** | Migration UUID + backfill + index + rollback path | `migrate` di DB dev ber-data sukses, rollback sukses |
| **B** | Trait `HasUuids` di semua model | `php artisan tinker`: model baru dapat uuid v7 |
| **C** | Fix frontend (`Number()`, slug buku, wayfinder regenerate) + seeder | `vue-tsc` + build hijau |
| **D** | Update seluruh test + tambah test UUID/index | Seluruh suite hijau |
| **E** | Verifikasi akhir | `migrate:fresh --seed` + smoke test halaman admin & storefront |

---

## 9. Risiko & Mitigasi

| Risiko | Mitigasi |
|---|---|
| Migration gagal di tengah | Semua dalam `DB::transaction` per grup; mapping tersimpan; rollback teruji |
| Data relasi putus saat backfill | Update FK via JOIN dengan mapping; verifikasi count per tabel sebelum/sesudah |
| URL/bookmark lama rusak | Belum production (disclaimer); slug buku untuk URL publik |
| Performa index UUID | v7 terurut waktu — fragmentasi minimal; skala toko buku tidak signifikan |
| Test assertion int | Audit terpusat di Fase D; `Str::isUuid` helper test |

---

## 10. Definisi Selesai (DoD)

1. Semua 30 tabel aplikasi ber-PK UUID v7, semua FK terindex.
2. `migrate:fresh --seed` + seluruh test suite hijau.
3. Rollback migration teruji (up → down → up).
4. `vue-tsc`, ESLint, Prettier, build hijau.
5. Smoke test: admin (buku, order, inventori, supplier, laporan) + storefront (katalog, checkout).

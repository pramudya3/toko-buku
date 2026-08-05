# Toko Buku — Admin Panel & Storefront

Aplikasi toko buku berbasis **Laravel 13 + Inertia v3 + Vue 3 + Tailwind CSS 4**.
Terdiri dari **panel admin** (kelola katalog, pesanan, promo, inventori, keuangan)
dan **storefront** (katalog publik, detail buku, keranjang, checkout).

## ✨ Fitur

### Panel Admin
- **Katalog** — CRUD buku (cover, spesifikasi, SKU otomatis, preorder), kategori
- **Pelanggan** — CRUD, status tier (reguler / bazaf / guru / reseller), status aktif, alamat lengkap
- **Pesanan** — buat manual, proses status (menunggu → diproses → dikirim → selesai), dropship
- **Promosi & Harga** — promo persentase/fixed/bundle, **tier discount** berbasis qty (min–max)
- **Inventori** — stok multi-gudang (Malang, Sidoarjo, defect) + audit mutasi
- **Keuangan** — arus kas otomatis dari order selesai
- **Hapus dengan Undo** — soft delete + toast undo di semua modul

### Storefront
- **Katalog buku** — search, filter kategori, load-more pagination, SEO meta
- **Detail buku** — sinopsis, spesifikasi, qty, beli sekarang
- **Checkout** — guest atau login, alamat cascading (provinsi → kota → kecamatan → kode pos auto-fill), cek stok, rate-limit
- **Pesanan Saya** — riwayat order customer login
- **RBAC 2 role** — admin (sidebar panel) vs customer (navbar simpel, tanpa sidebar)

## 🛠 Tech Stack

| Layer | Teknologi |
|---|---|
| Backend | Laravel 13, PostgreSQL, Fortify, Pest |
| Frontend | Inertia v3, Vue 3, TypeScript, Tailwind CSS 4, reka-ui (shadcn-vue) |
| Lainnya | Laravel Wayfinder (typed routes), Pint, ESLint, Vite |

## 🚀 Setup Lokal

```bash
# 1. Dependency
composer install
npm install

# 2. Environment
cp .env.example .env
php artisan key:generate
# atur kredensial DB di .env (default: pgsql / toko_buku)

# 3. Database + seed
php artisan migrate
php artisan db:seed                    # demo data + data wilayah (34 provinsi)
php artisan storage:link

# 4. Jalankan
composer run dev                       # laravel + vite (hot reload)
```

Akses: `http://localhost:8000` (storefront) · `http://localhost:8000/admin` (panel)

### Akun Demo (local)

| Role | Email | Password |
|---|---|---|
| Admin | `admin@tokobuku.test` | `password` |
| Customer | daftar via `/register` | — |

## 🧪 Testing

```bash
php artisan test --compact
```

## 📁 Struktur Penting

```
app/
├── Enums/            # OrderStatus, CustomerTier, UserRole, PaymentMethod, dll
├── Http/Controllers/
│   ├── Admin/        # Panel admin (Book, Order, Customer, Promotion, ...)
│   ├── Storefront/   # Catalog, Checkout, MyOrder, wilayah publik
│   └── (root)        # CheckoutController, StorefrontController, ...
├── Models/
├── Services/         # PricingService (promo+tier), InventoryService, ...
└── Mail/             # (rencana) Mailable email — lihat docs/email-plan.md
database/
├── data/             # Data wilayah Kemendigri + mapping kode pos (CSV)
├── migrations/
└── seeders/          # DemoSeeder, WilayahSeeder
docs/
├── PRD.md
├── implementation-plan.md
└── email-plan.md     # (draft) fitur email — menunggu konfirmasi client
resources/js/
├── layouts/
│   ├── app/          # Sidebar admin
│   └── customer/     # Navbar simpel storefront
├── pages/
│   ├── admin/        # Halaman panel
│   └── storefront/   # Catalog, BookDetail, Checkout, MyOrders, About
└── components/       # DataTableActions, AddressFields, CurrencyInput, ...
```

## 📄 Lisensi

Proyek internal — hubungi pemilik repository untuk detail lisensi.

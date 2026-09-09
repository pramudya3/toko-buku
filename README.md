# Toko Buku — Bookstore E-Commerce

Bookstore application built with **Laravel 13 + Inertia v3 + Vue 3 + Tailwind CSS 4**, consisting of an **admin panel** (catalog, orders, promotions, inventory, purchasing, finance, content, settings) and a **storefront** (public catalog, cart, checkout, order tracking). Includes a dedicated **POS API** for offline-first point-of-sale clients.

## Features

### Storefront

- **Home** — editorial article feed (featured hero, recent articles, load-more), featured book picks, unified search across articles and books, category/date facets
- **Book catalog** (`/buku`) — search by title/author, category filter, stock filter (ready / pre-order / empty), sorting, bundle & unit promo sections, load-more pagination, SEO meta
- **Book detail** — synopsis, specifications (weight, dimensions, pages, paper, cover, language), multiple editions (cetakan) with per-edition pricing and stock, gallery, price breakdown (base → promo → tier discount), pre-order support with ETA, back-in-stock request
- **Promo page** — active bundle promos, unit promos (percentage/fixed), vouchers with terms
- **Cart** — session-based, multiple editions per book, automatic bundle grouping, partial group selection
- **Checkout** — voucher application (item price or shipping cost), real-time shipping cost calculator (RajaOngkir), delivery or self-pickup, payment methods (transfer / COD / cash), server-side stock validation with row locking, voucher quota validation (global and per-user), rate-limited submission
- **Checkout success** — order confirmation with bank account details for transfer
- **My Orders** — order history with status filter, order detail with invoice, payment-proof upload (replaceable)
- **Notifications** — bell menu, mark individual/all as read
- **Profile** — update profile, cascading address (province → city → district → village, auto postal code), account deletion
- **Settings** — account, address, password security, appearance (light/dark/system)
- **Auth** — login, registration, password reset, Google OAuth, password confirmation

### Admin Panel

- **Dashboard** — monthly statistics (revenue, refunds, orders, cash-in, book count, stock), daily net-revenue sales chart, low-stock and empty-stock alerts, recent orders
- **Books** — full CRUD (cover, specs, gallery with ordering, auto SKU from category code, pre-order flag with ETA), multiple editions per book with buy/sell prices and guru pricing, active toggle, soft delete with restore, CSV import (pricelist/invoice layouts, Indonesian price format)
- **Categories** — CRUD with code (used for SKU generation), CSV import
- **Articles & Article Categories** — CRUD with WYSIWYG (TipTap) editor, inline image upload, auto slug, featured hero article, publish toggle, featured toggle
- **Customers** — CRUD, tiers (Reguler / Bazaf / Guru / Reseller), active status, full address, order/spending summary, CSV import with region resolution
- **Users** — admin/kasir account management, active toggle
- **Orders** — manual order creation (walk-in, phone), book/customer pickers, shipping-cost check, fulfillment (deliver/self-pickup), payment methods, sales channels (website, store, Shopee, Tokopedia, TikTok Shop, Instagram), dropship support, warehouse origin, custom pricing, status workflow (Menunggu Konfirmasi → Diproses → Dikirim → Selesai / Batal), payment confirmation, print-ready invoice, pre-order management center
- **Shipping (Biteship)** — booking creation, one-click process & ship, pickup scheduling, status refresh, label PDF, cancellation
- **Promotions** — percentage / fixed / bundle (paket hemat) types, date-range activation, active toggle, CSV import for bundles
- **Vouchers** — percentage/fixed, item-price or shipping-cost scope, min-order amount, global and per-user quota, auto code generation, usage protection
- **Tier discounts** — qty-based (min–max) discounts per tier, CSV import
- **Inventory** — multi-warehouse stock (per-edition breakdown), movement types (in, out, transfer, defect, return, adjustment), validation rules (defect warehouse never sells), audit trail, automatic aggregate sync, stock opname adjustments, low-stock threshold
- **Warehouses** — CRUD, defect and default warehouse concepts
- **Purchasing** — supplier purchases with multi-warehouse allocation, weighted-average HPP (landed cost) tracking, shipping cost, partial/full payments, print nota
- **Suppliers** — CRUD, debt tracking, purchase/return history
- **Supplier returns** — return stock to supplier (moves to defect warehouse), HPP tracking
- **Supplier debts** — payable tracking with payments
- **Sales returns** — returns from completed orders, stock restoration (origin or defect), automatic refund cash flow entry, condition tracking (good/damaged)
- **Consignment (konsinyasi)** — partner deliveries (stock out), sales reports creating receivables, unsold returns (stock in), remaining-stock tracking, tier discount pricing, filterable reports with XLSX export, print invoices
- **Receivables** — customer debt tracking from consignment or manual entry, installment payments, paid/unpaid filter
- **Cash flow (kas)** — manual income/expense entries, monthly closing/reopening, configurable categories and sub-categories, order-linked automatic revenue/refund entries, monthly report
- **Stock requests** — waitlist of customers requesting back-in-stock notifications
- **Dropship** — dropship order report with filters
- **Reports** — daily recap (XLSX export), sales reports, supplier reports, inventory movement reports (all with XLSX export)
- **Activity log** — full audit trail (50+ action types), filterable by action/date/user
- **Guide** — built-in admin documentation page (`/admin/panduan`)
- **Settings** — store identity (institution profile, logo, vision/mission, terms), bank accounts, couriers, payment methods, sales channels, WhatsApp templates, return reasons, Biteship API key (encrypted)
- **Undo** — soft delete with toast undo in every module

### POS API (`/api/pos`)

Sanctum-token authentication with `pos` ability for offline-first point-of-sale clients: token login/logout, health check, master-data pull sync (books, editions, customers), order submission with client UUID deduplication, order voiding, daily sales reports. Book changes broadcast real-time `PosMasterUpdated` events.

## Tech Stack

| Layer | Technology |
|---|---|
| Backend | Laravel 13, PostgreSQL, Fortify, Sanctum, Pest |
| Frontend | Inertia v3, Vue 3, TypeScript, Tailwind CSS 4, reka-ui (shadcn-vue), TipTap |
| Tooling | Laravel Wayfinder (typed routes), Pint, ESLint, Vite, Reverb (broadcasting) |

## Local Setup

```bash
# 1. Dependencies
composer install
npm install

# 2. Environment
cp .env.example .env
php artisan key:generate
# configure DB credentials in .env (default: pgsql / toko_buku)

# 3. Database + seed
php artisan migrate
php artisan db:seed        # demo data + Indonesian region data (34 provinces)
php artisan storage:link

# 4. Run
composer run dev           # laravel + vite (hot reload)
```

Storefront: `http://localhost:8000` · Admin panel: `http://localhost:8000/admin`

### Demo Account (local)

| Role | Email | Password |
|---|---|---|
| Admin | `admin@tokobuku.test` | `password` |
| Customer | register via `/register` | — |

## Endpoints

Route URIs use Indonesian naming, matching the application's locale.

### Storefront (public)

| Method | URI | Purpose |
|---|---|---|
| GET | `/` | Editorial home (articles + book picks) |
| GET | `/tentang-kami` | About page (institution profile, bank accounts) |
| GET | `/promo` | Active bundle promos, unit promos, vouchers |
| GET | `/buku` | Book catalog (search, filters, sorting) |
| GET | `/buku/lainnya` | Catalog load-more pagination |
| GET | `/buku/{bookUrl}` | Book detail |
| GET | `/artikel/lainnya` | Article feed load-more pagination |
| GET | `/artikel/{article:slug}` | Article detail |
| POST | `/keranjang` | Add book to cart |
| POST | `/keranjang/bulk` | Bulk add to cart |
| POST | `/keranjang/{book}/remove` | Remove book from cart |
| POST | `/keranjang/grup/toggle` | Toggle cart group selection |
| POST | `/keranjang/grup/hapus` | Remove cart group |
| POST | `/keranjang/{book}/qty` | Update cart quantity |
| POST | `/keranjang/{book}/edition` | Update cart edition |
| GET | `/wilayah/provinces` | Region API — provinces |
| GET | `/wilayah/cities` | Region API — cities |
| GET | `/wilayah/districts` | Region API — districts |
| GET | `/wilayah/villages` | Region API — villages (auto postal code) |

### Auth (Fortify + Google OAuth)

| Method | URI | Purpose |
|---|---|---|
| GET / POST | `/login` | Login |
| GET / POST | `/register` | Registration |
| POST | `/logout` | Logout |
| GET / POST | `/password/reset` | Request password reset |
| GET / POST | `/password/reset/{token}` | Reset password |
| GET / POST | `/user/confirm-password` | Password confirmation |
| GET | `/auth/google/redirect` | Google OAuth redirect |
| GET | `/auth/google/callback` | Google OAuth callback |

### Customer (authenticated)

| Method | URI | Purpose |
|---|---|---|
| GET / POST | `/checkout` | Checkout page / submit order (throttled) |
| POST | `/checkout/ongkir` | Shipping cost calculation |
| GET | `/checkout/sukses` | Checkout success |
| GET | `/pesanan-saya` | My orders |
| GET | `/pesanan-saya/{order}` | Order detail |
| GET | `/pesanan-saya/{order}/invoice` | Order invoice |
| POST | `/pesanan-saya/{order}/bukti` | Upload payment proof |
| POST | `/stok/ajukan/{book}` | Request back-in-stock notification |
| GET / PATCH / DELETE | `/profil` | Storefront profile edit / update / delete |
| PATCH | `/profil/alamat` | Update address |
| POST | `/notifikasi/read-all` | Mark all notifications read |
| POST | `/notifikasi/{notification}/read` | Mark notification read |
| GET / PATCH | `/settings/akun` | Account settings |
| GET / PATCH | `/settings/alamat` | Address settings |
| DELETE | `/settings/akun` | Delete account |
| GET | `/settings/security` | Security settings (password-confirmed) |
| PUT | `/settings/password` | Update password |
| GET | `/settings/appearance` | Appearance settings |

### Admin (`/admin`, requires `auth` + `admin`)

| Method | URI | Purpose |
|---|---|---|
| GET | `/admin/dashboard` | Dashboard statistics and charts |
| GET | `/admin/imports/templates/{type}` | Download CSV import template |
| GET / POST | `/admin/books` | Book list / create |
| GET / PUT | `/admin/books/{book}/edit` | Book edit form / update |
| DELETE | `/admin/books/{book}` | Delete book (soft) |
| POST | `/admin/books/import` | Import books from CSV |
| PATCH | `/admin/books/{book}/toggle-active` | Toggle book active |
| POST | `/admin/books/{book}/restore` | Restore book |
| Resource | `/admin/categories` | Category CRUD |
| POST | `/admin/categories/import` | Import categories from CSV |
| Resource | `/admin/articles` | Article CRUD |
| POST | `/admin/articles/upload-image` | Upload inline article image |
| PATCH | `/admin/articles/{article}/toggle-active` | Toggle publish |
| PATCH | `/admin/articles/{article}/toggle-featured` | Toggle featured |
| Resource | `/admin/article-categories` | Article category CRUD |
| GET / POST / PUT / DELETE | `/admin/customers` | Customer CRUD |
| GET | `/admin/customers/{user}/summary` | Customer order/spending summary |
| POST | `/admin/customers/import` | Import customers from CSV |
| Resource | `/admin/users` | User (admin/kasir) management |
| PATCH | `/admin/users/{user}/toggle-active` | Toggle user active |
| GET / POST | `/admin/orders` | Order list / manual order creation |
| GET | `/admin/orders/create` | Manual order form |
| GET | `/admin/orders/options/books` | Book picker options |
| GET | `/admin/orders/options/customers` | Customer picker options |
| POST | `/admin/orders/cek-ongkir` | Shipping cost check |
| GET | `/admin/orders/preorder` | Pre-order management center |
| GET | `/admin/orders/{order}` | Order detail |
| GET | `/admin/orders/{order}/invoice` | Print invoice |
| PATCH | `/admin/orders/{order}/process` | Process order (confirm stock, final shipping cost) |
| POST | `/admin/orders/{order}/process-ship` | One-click process + ship (Biteship) |
| PATCH | `/admin/orders/{order}/status` | Update order status |
| PATCH | `/admin/orders/{order}/payment` | Confirm payment |
| POST | `/admin/orders/{order}/shipping` | Create Biteship booking |
| POST | `/admin/orders/{order}/shipping/cancel` | Cancel Biteship booking |
| POST | `/admin/orders/{order}/shipping/pickup` | Request pickup |
| POST | `/admin/orders/{order}/shipping/refresh` | Refresh shipping status |
| GET | `/admin/orders/{order}/shipping/label` | Shipping label PDF |
| GET / POST / PUT / DELETE | `/admin/promotions` | Promotion CRUD |
| POST | `/admin/promotions/import` | Import bundle promotions |
| PATCH | `/admin/promotions/{promotion}/toggle` | Toggle promotion |
| Resource | `/admin/vouchers` | Voucher CRUD |
| PATCH | `/admin/vouchers/{voucher}/toggle` | Toggle voucher |
| GET / POST | `/admin/inventory` | Stock list / record movement |
| GET / POST | `/admin/inventory-adjustments` | Stock opname adjustments |
| Resource | `/admin/warehouses` | Warehouse CRUD |
| GET / POST / PUT / DELETE | `/admin/tier-discounts` | Tier discount management |
| POST | `/admin/tier-discounts/import` | Import tier discounts |
| GET | `/admin/kas` | Cash flow recording |
| POST / PUT / DELETE | `/admin/kas` | Cash flow entry create / update / delete |
| GET | `/admin/kas/laporan` | Cash flow report |
| GET | `/admin/kas/kategori` | Cash categories |
| POST / PUT / DELETE | `/admin/kas/kategori` | Category create / update / delete |
| POST | `/admin/kas/sub-kategori` | Sub-category create |
| PUT / DELETE | `/admin/kas/sub-kategori/{kasSubCategory}` | Sub-category update / delete |
| POST | `/admin/kas/months` | Open new cash month |
| GET | `/admin/kas/{bulan}` | Cash month detail |
| POST | `/admin/kas/{bulan}/close` | Close cash month |
| POST | `/admin/kas/{bulan}/reopen` | Reopen cash month |
| GET / POST | `/admin/receivables` | Receivable list / create |
| POST | `/admin/receivables/{receivable}/payments` | Record payment |
| GET | `/admin/konsinyasi` | Consignment management |
| GET | `/admin/konsinyasi/laporan` | Consignment report |
| GET | `/admin/konsinyasi/laporan/export` | Export report (XLSX) |
| GET | `/admin/konsinyasi/options/books` | Book picker options |
| GET | `/admin/konsinyasi/options/customers/{user}/titipan` | Partner consignment options |
| POST | `/admin/konsinyasi/deliveries` | Record delivery (stock out) |
| DELETE | `/admin/konsinyasi/deliveries/{delivery}` | Delete delivery |
| GET | `/admin/konsinyasi/deliveries/{delivery}/invoice` | Delivery invoice |
| POST | `/admin/konsinyasi/sales` | Record consignment sale (receivable) |
| DELETE | `/admin/konsinyasi/sales/{sale}` | Delete sale |
| GET | `/admin/konsinyasi/sales/{sale}/invoice` | Sale invoice |
| POST | `/admin/konsinyasi/returns` | Record consignment return (stock in) |
| DELETE | `/admin/konsinyasi/returns/{retur}` | Delete return |
| GET | `/admin/sales-returns/options/orders` | Order picker options |
| GET | `/admin/sales-returns/orders/{order}` | Order detail for return |
| Resource (index, store) | `/admin/sales-returns` | Sales returns |
| GET | `/admin/sales-returns/{salesReturn}/invoice` | Return invoice |
| GET | `/admin/stock-requests` | Stock request waitlist |
| GET | `/admin/stock-requests/{book}` | Requests per book |
| GET | `/admin/daily-recap` | Daily sales recap |
| GET | `/admin/daily-recap/export` | Export recap (XLSX) |
| Resource | `/admin/suppliers` | Supplier CRUD |
| GET / POST | `/admin/purchases` | Purchase list / create |
| GET | `/admin/purchases/{supplierPurchase}` | Purchase detail |
| GET | `/admin/purchases/{supplierPurchase}/invoice` | Purchase nota |
| GET | `/admin/purchases/options/books` | Book picker options |
| GET / POST | `/admin/supplier-returns` | Supplier returns |
| GET | `/admin/supplier-returns/options/books` | Book picker options |
| GET | `/admin/supplier-returns/options/purchases` | Purchase picker options |
| GET | `/admin/supplier-returns/options/purchases/{supplierPurchase}` | Purchase detail for return |
| GET | `/admin/supplier-debts` | Supplier payable list |
| POST | `/admin/supplier-debts/payments` | Record supplier payment |
| GET | `/admin/supplier-reports` | Supplier reports |
| GET | `/admin/supplier-reports/export` | Export (XLSX) |
| GET | `/admin/sales-reports` | Sales reports |
| GET | `/admin/sales-reports/export` | Export (XLSX) |
| GET | `/admin/inventory-reports` | Inventory movement reports |
| GET | `/admin/inventory-reports/export` | Export (XLSX) |
| GET | `/admin/address/provinces` | Region API — provinces |
| GET | `/admin/address/cities` | Region API — cities |
| GET | `/admin/address/districts` | Region API — districts |
| GET | `/admin/address/villages` | Region API — villages |
| GET | `/admin/dropship` | Dropship order report |
| GET | `/admin/aktivitas` | Activity log |
| GET | `/admin/panduan` | Built-in admin guide |
| GET / PUT | `/admin/settings/lembaga` | Institution settings |
| Resource | `/admin/settings/rekening` | Bank account management |
| GET | `/admin/settings/ekspedisi` | Courier settings |
| POST / PUT / DELETE | `/admin/settings/ekspedisi` | Courier create / update / delete |
| PUT | `/admin/settings/ekspedisi/bulk` | Bulk courier update |
| GET | `/admin/settings/pembayaran` | Payment method settings |
| POST / PUT / DELETE | `/admin/settings/pembayaran` | Payment method create / update / delete |
| PUT | `/admin/settings/pembayaran/bulk` | Bulk payment method update |
| GET | `/admin/settings/sumber-penjualan` | Sales channel settings |
| POST / PUT / DELETE | `/admin/settings/sumber-penjualan` | Sales channel create / update / delete |
| PUT | `/admin/settings/sumber-penjualan/bulk` | Bulk sales channel update |
| GET / PUT | `/admin/settings/wa-template` | WhatsApp template settings |
| GET / PUT | `/admin/settings/api-key` | Biteship API key settings |
| GET / POST / PUT / DELETE | `/admin/settings/alasan-retur` | Return reason management |
| PUT | `/admin/settings/alasan-retur/{alasanRetur}/toggle` | Toggle return reason |

### POS API (`/api/pos`, Sanctum + `pos` ability)

| Method | URI | Purpose |
|---|---|---|
| POST | `/api/pos/login` | Token login (throttled) |
| POST | `/api/pos/logout` | Token logout |
| GET | `/api/pos/health` | Health check |
| GET | `/api/pos/sync/pull` | Pull master data sync |
| GET | `/api/pos/books` | Book list |
| GET | `/api/pos/books/{book}/editions` | Book editions |
| GET / POST | `/api/pos/customers` | Customer list / create |
| GET / POST | `/api/pos/orders` | Order list / create (client UUID dedup) |
| GET | `/api/pos/orders/{order:client_uuid}` | Order detail |
| POST | `/api/pos/orders/{order:client_uuid}/void` | Void order |
| GET | `/api/pos/reports/daily` | Daily sales report |

### Webhooks

| Method | URI | Purpose |
|---|---|---|
| POST | `/webhooks/biteship` | Biteship shipping status webhook (signature-verified) |

## Scheduled Tasks

| Command | Schedule | Purpose |
|---|---|---|
| `orders:cancel-unpaid` | Hourly | Auto-cancel unpaid orders after 24 hours |
| `backup:run` | Daily 01:30 | Database backup |
| `backup:clean` | Daily 01:00 | Prune backups older than 7 days |

## Testing

```bash
php artisan test --compact
```

## Project Structure

```
app/
├── Enums/               # OrderStatus, CustomerTier, UserRole, PaymentMethod, etc.
├── Http/Controllers/
│   ├── Admin/           # Admin panel controllers (Book, Order, Customer, Promotion, ...)
│   ├── Api/Pos/         # POS API controllers
│   └── (root)           # StorefrontController, CheckoutController, MyOrderController, ...
├── Models/
├── Services/            # PricingService, InventoryService, OrderStatusService, VoucherService, ...
├── Observers/           # Audit logging + POS broadcast triggers
└── Mail/, Notifications/
database/
├── data/                # Indonesian region data + postal code mapping (CSV)
├── migrations/
└── seeders/             # DemoSeeder, WilayahSeeder, CashFlowCategorySeeder
resources/js/
├── layouts/
│   ├── app/             # Admin sidebar layout
│   └── customer/        # EditorialLayout (storefront)
├── pages/
│   ├── admin/           # Admin panel pages
│   ├── storefront/      # Home, Catalog, BookDetail, Checkout, MyOrders, Profile, ...
│   ├── settings/        # Account, Address, Security, Appearance
│   ├── print/           # Printable invoices
│   └── auth/            # Login, Register, password reset (Fortify)
└── components/          # DataTable, AddressFields, CashFlowEntryDialog, invoice suite, ...
```

## Deployment

- Wayfinder files (`resources/js/{routes,actions,wayfinder}`) are generated automatically during the Docker build.
- Build production assets: `npm run build`

## License

Internal project — contact the repository owner for licensing details.

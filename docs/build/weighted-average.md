# Weighted Average HPP (Harga Pokok Penjualan) — Landed Cost per Cetakan

> Berlaku untuk `Barang Masuk` (Supplier Purchase) → `book_editions.harga_beli` → `order_items.harga_beli_snapshot` → laporan `laba = price_final - HPP`.

## 1. Ringkasan Rekomendasi

* **Metode:** `Weighted Average (Average Cost)` **global per `book_edition_id` (Cetakan)**, bukan per buku induk, bukan per gudang.
* **Ongkir:** Masuk HPP sebagai **landed cost** `per pcs` (`shipping_per_pcs = total_ongkir / total_qty`), `landed = price + shipping_per_pcs`.
* **Harga Jual:** Tetap **manual** per cetakan (`harga_jual`), tidak auto `HPP * margin`. Tampilkan hint `HPP: Rp X` di samping `Harga Beli` saat input pembelian.

Dipilih karena sesuai **PSAK ritel** dan praktik penerbit/distributor buku: harga cetakan berbeda, stok tidak kadaluarsa, FIFO terlalu berat, `replace` (harga terakhir) bikin laba naik-turun tajam.

## 2. Kenapa Bukan Harga Terakhir / FIFO

| Metode | Cara | Kelebihan | Kekurangan untuk Toko Buku |
|---|---|---|---|
| **Harga Terakhir (Replace)** | `harga_beli = price` faktur terakhir | Simple | Stok lama `10@30k` + beli `5@40k` → HPP langsung `40k`, laba undervalued `10k/pcs` |
| **FIFO per Batch** | kartu stok per faktur, jual pakai batch tertua dulu | Paling akurat untuk kadaluarsa | Butuh `batch_id` per `qty`, `Transfer/Retur` ribet, overkill untuk buku |
| **Weighted Average (dipakai)** | rata-rata tertimbang | Halus, audit mudah, 1 angka HPP per cetakan | HPP tidak jejak per faktur (tapi bisa di-audit via `supplier_purchase_items`) |

## 3. Rumus

```
oldStock = SUM(stocks.qty) per edition sebelum masuk
oldHpp   = edition.harga_beli

totalQty   = Σ qty baru
shippingPerPcs = intdiv(shipping_cost, totalQty)  // sisa bagi (remainder) dibagi 1/pcs awal
landedPerPcs   = price + shippingPerPcs

newHpp = (oldStock * oldHpp + qtyBaru * landedPerPcs) / (oldStock + qtyBaru)
```

*Jika `oldStock = 0` → `newHpp = landedPerPcs`.*
*Jika `shipping_cost = 0` → `landed = price`.*

### Contoh

* Stok lama `Cetakan 2`: `10 pcs @30.000` → `HPP 30.000`
* Beli baru `5 pcs @40.000` + `Ongkir 10.000` (total `5 pcs`)
  * `shipping/pcs = 2.000`, `landed = 42.000`
  * `newHpp = (10*30.000 + 5*42.000)/15 = 510.000/15 = 34.000`
* Hasil: `edition.harga_beli` jadi `34.000`. Order berikutnya `harga_beli_snapshot = 34.000`.

Tanpa ongkir: `(10*30k + 5*40k)/15 = 33.333`.

## 4. Per Cetakan, Bukan Per Buku / Per Gudang

* `Cetakan 1 @28k` & `Cetakan 2 @33k` punya `HPP` terpisah. Beli `Cetakan 2` tidak ubah `Cetakan 1`.
* Stok dihitung **global per edition** (semua gudang `sum(qty)`), bukan per gudang. Kalau `Malang` ongkir `2k` & `Surabaya` `5k`, tetap dirata-rata global — supaya `Harga Jual` 1 angka per cetakan. HPP per gudang per cetakan hanya perlu jika jual beda harga per wilayah (tidak dipakai sekarang).

## 5. Implementasi di Codebase

**Migration**
* `supplier_purchases.shipping_cost` `int unsigned default 0`
* `supplier_purchase_items.book_edition_id` `uuid nullable FK → book_editions`
* `supplier_purchase_item_allocations` (untuk multi-gudang lama, sekarang single tapi tetap support backward)

**Model**
* `SupplierPurchase` `fillable: shipping_cost, warehouse_kode, ...` `casts: shipping_cost:int`
* `SupplierPurchaseItem` `fillable: book_edition_id` + `belongsTo edition`
* `BookEdition` `harga_beli` di-update

**Request**
* `SupplierPurchaseRequest`: `warehouse_kode required`, `shipping_cost nullable|min:0`, `items.*.book_edition_id nullable|exists`, `items.*.qty required|min:1`

**Service `SupplierService::recordPurchase`**
1. Resolve `warehouse` (single, backward `warehouse_kodes` array)
2. Normalisasi `items` → `qty, price, book_edition_id, allocations`
3. Hitung `totalBarang`, `totalQty`, `shippingPerPcs`, `remainder`
4. `DB::transaction`:
   * `create SupplierPurchase` (`total`, `shipping_cost`, `warehouse_kode`)
   * Loop `items` → `create SupplierPurchaseItem` → `allocations` → `inventory->move(book, qty, to: warehouse, edition: edition)` per alokasi
   * Hitung `landedPerPcs` + `newHpp` weighted average → `edition->update(['harga_beli' => $newHpp])` (hanya sekali per `edition` per transaksi)

**Frontend `purchases/Create.vue`**
* `Gudang Tujuan` single `Select` (bukan multi), `Ongkos Kirim (Rp)` `CurrencyInput` `FieldHint: mempengaruhi HPP` (boleh `0`)
* `Item Barang`: `Buku w-64 | Cetakan w-28 | Qty w-20 | Harga Beli w-36 (input + HPP hint) | Subtotal w-28`
  ```html
  <CurrencyInput v-model="item.price" />
  <span class="text-xs muted">HPP: Rp {{ edition.harga_beli }}</span> <!-- read-only di samping Harga -->
  ```
* Footer `Total Barang / Ongkir / Grand Total` sejajar `Subtotal` (`w-28 px-4`)

**Invoice `print/purchases/Invoice.vue`**
* Tampil `Ongkir`, `Grand Total = total + shipping_cost`, `warehouse` single.

## 6. Cara Baca di Laporan

* `order_items.harga_beli_snapshot` = `HPP` saat order dibuat (sudah landed). Laporan `SalesReport` & `DailyRecap` hitung `hpp = harga_beli_snapshot * qty`, `laba = (price_final - hpp) * qty`.
* `HPP` di `Edit Buku → Cetakan` masih **editable manual** untuk koreksi awal, tapi akan tertimpa lagi saat ada `Barang Masuk` cetakan itu (weighted average). Beri hint: *“Akan ter-update otomatis saat Barang Masuk (rata-rata)”*.

## 7. Kapan Tidak Pakai Ongkir

Jika penerbit **franco** (free ongkir), isi `Ongkos Kirim = 0` → `landed = price`, rumus tetap jalan.

## 8. Alternatif Jika Mau FIFO

Butuh tabel `purchase_batches` per `qty` per faktur dan `order_items` pilih `batch_id` saat `deduct`. Tidak disarankan untuk sekarang karena `Transfer` & `Retur` jadi kompleks.

---
*Rekomendasi tetap: **Weighted Average Global per Cetakan + landed per pcs** seperti di atas.*

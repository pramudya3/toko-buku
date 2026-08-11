# Plan: Cache Perhitungan Ongkir (api.co.id)

> **Status:** Planning · **Provider uji:** api.co.id (key sudah aktif) · **Cache driver:** `file` (default Laravel, tanpa Redis)

## 1. Latar Belakang & Tujuan

Perhitungan ongkir dipanggil dari 2 halaman (checkout storefront & admin transaksi). Setiap panggilan = 1 hit API berbayar/berkuota. Tanpa strategi, hit terbuang besar:

- Checkout auto-fetch tiap alamat/qty berubah + pengunjung gagal checkout (konversi 1–5%) → 20–100× hit terbuang per order.
- Admin: auto-fetch di list order → N order = N hit tiap halaman dibuka.

**Tujuan:** mengurangi hit API seminimal mungkin (target ±1–2 hit unik per order) tanpa mengorbankan akurasi, menggunakan cache bawaan Laravel (file) — **tanpa Redis**.

## 2. Arsitektur

```
[Storefront checkout] ─┐
                       ├─→ POST /checkout/ongkir ─→ ShippingCostService ─→ [CACHE file TTL 24 jam] ─→ api.co.id
[Admin (order lama)] ──┘                                                     │ (hit)              │ (miss)
                                                                             └── balas dari cache ←┘
Order yang sudah punya shipping_cost → admin TIDAK memanggil API (pakai data tersimpan)
```

Prinsip utama: **1 cek ongkir unik = 1 hit API**, semua pengguna & halaman berbagi cache yang sama.

## 3. Desain Cache

| Aspek | Keputusan | Alasan |
|---|---|---|
| Driver | `file` (`CACHE_STORE=file`, default) | Skala kecil–menengah, nol konfigurasi; migrasi Redis nanti cukup ganti env |
| Key | `ongkir:v1:{md5(origin:destination:weight_bucket)}` | Deterministik & kompak |
| Bucket berat | dibulatkan ke **0,5 kg** (`ceil(kg*2)/2`) | Berat cart jarang beda tipis → bucket memperbesar reuse |
| TTL | **24 jam** | Harga kurir jarang berubah harian; tetap segar |
| Nilai cache | Array penuh respons `costs` + `weight_kg` + timestamp | Dikembalikan langsung tanpa hit API |
| Cache miss API error | **Tidak di-cache** → error dilempar ke UI | Hindari menyimpan data salah |
| Hasil kosong (0 kurir) | Cache TTL pendek **1 jam** | Cegah hammering ke alamat tanpa kurir |

Contoh key: `ongkir:v1:9f2c…` (md5 dari `3573051002:3573051002:1.0`).

## 4. Perubahan Kode

### 4.1 `ShippingCostService` — cache wrapper (inti)
```php
public function costs(string $destinationVillageCode, float $weightKg): array
{
    $bucket = ceil($weightKg * 2) / 2;
    $key = 'ongkir:v1:'.md5(config('api-co.origin_village_code').':'.$destinationVillageCode.':'.$bucket);

    return Cache::remember($key, now()->addHours(24), fn () => $this->fetchFromApi(...));
}
```
- `fetchFromApi()` = logika HTTP yang ada sekarang (dipindah private).
- Resolve kode desa (`resolveVillageCode`) juga di-cache shared (ganti session cache saat ini → `Cache::remember`, TTL 7 hari — data wilayah jarang berubah).

### 4.2 Checkout storefront — tombol eksplisit
- Hapus **auto-fetch** saat alamat berubah (watch saat ini).
- Ganti dengan tombol **"Cek Ongkir"** di samping dropdown ekspedisi (enable saat kecamatan+kelurahan terpilih).
- Setelah hasil tampil, dropdown ekspedisi aktif seperti sekarang.
- (Opsional, iterasi lanjut: auto-fetch hanya jika user sudah memilih ekspedisi sebelumnya & alamat berubah — tetap 1 hit per (tujuan, berat).)

### 4.3 Admin transaksi — pakai data tersimpan
- Order yang `shipping_cost` sudah terisi (dari checkout) → tampilkan ongkir/ekspedisi/estimasi langsung, **tanpa hit API**.
- Tombol **"Cek Ongkir"** hanya muncul & hanya memanggil API untuk order lama / order yang belum punya data ongkir (mis. dibuat manual sebelum fitur ongkir).
- **Tidak ada** auto-fetch di halaman list order.

### 4.4 Endpoint bersama
- Kedua halaman memakai `POST /checkout/ongkir` yang sama → cache otomatis terpakai bersama.

## 5. Kasus Khusus

| Kasus | Penanganan |
|---|---|
| API error/timeout | Tidak di-cache; UI tampilkan pesan error; user bisa coba lagi |
| Kelurahan tidak didukung `is_courier_support` | Resolve gagal → pesan jelas; resolve hasil negatif di-cache pendek (1 jam) agar tidak berulang |
| Berat cart berubah (tambah/hapus item) | Bucket baru → hit baru (wajar; hanya saat user benar-benar cek) |
| Harga kurir naik di api.co.id | TTL 24 jam → maksimal 1 hari data lama |
| Banyak user ke kelurahan sama | 1 hit untuk semua dalam 24 jam (inti penghematan) |

## 6. Estimasi Penghematan

Asumsi 300 order/bulan, ±100 kelurahan tujuan unik, ±2 cek/order:

| Skenario | Hit/bulan | Biaya api.co.id |
|---|---|---|
| Tanpa cache + auto-fetch (sekarang) | ±3.000 | Melebihi gratis 50 → Starter Rp 50.000 |
| Dengan cache + tombol eksplisit | **±300–500** | Masih > 50 → Starter Rp 50.000* |
| Cache + tombol + **Biteship** (opsional nanti) | ±300–500 | **±Rp 1.500–2.500** |

\* Catatan: api.co.id ongkir = subscription bulanan; cache menekan hit tapi kuota gratis 50/bulan tetap batas. Jika volume nyata > 50/bulan, opsi: Starter Rp 50.000 atau pindah Biteship pay-per-use (lihat plan terpisah).

## 7. Rencana Testing

1. **Unit test `ShippingCostService`** (Http::fake):
   - Hit pertama → panggil API (1 request terverifikasi via `Http::assertSentCount`).
   - Hit kedua (key sama) → **0 request API** (`assertSentCount(0)`), hasil dari cache.
   - Berat beda bucket (0,4 kg vs 0,6 kg) → hit API baru.
   - API error → tidak di-cache; exception dilempar.
2. **Feature test checkout**: tombol cek ongkir → response JSON; submit order menyimpan shipping_cost (existing test tetap hijau).
3. **Test admin**: order dengan shipping_cost → halaman tidak memanggil API (Http::assertNothingSent); order lama + klik cek → 1 hit.
4. **Verifikasi manual**: 2 user berbeda ke kelurahan sama → cek log (1 hit API), kuota dashboard api.co.id tidak bertambah pada cek kedua.

## 8. Tahapan Implementasi

| # | Langkah | Output | Status |
|---|---|---|---|
| 1 | Cache wrapper di `ShippingCostService` + cache resolve desa | Hit API turun drastis (teruji unit) | ✅ Selesai (5 unit test) |
| 2 | Checkout: tombol "Cek Ongkir" eksplisit | Auto-fetch dihapus | ✅ Selesai |
| 3 | Admin: pakai data tersimpan + tombol cek untuk order lama | List aman dari hit API | ✅ Selesai (cek-ongkir admin + re-verify di store) |
| 3b | **Optimasi: resolve kode desa dari DB** (sync 1×/kecamatan) | Hit regional: dari 1×/kelurahan → 1×/kecamatan | ✅ Selesai |
| 4 | Test lengkap + verifikasi kuota dashboard | Metrik terukur |
| 5 | (Opsional) Evaluasi Biteship pay-per-use | Biaya minimal |

## 9. Metrik Keberhasilan

- Hit API/bulan < 2× jumlah order.
- Cek ongkir kedua untuk (tujuan, berat) sama = **0 hit API**.
- Tidak ada error ongkir di checkout & admin.
- Total biaya ongkir API ≤ Rp 50.000/bulan (atau ≤ Rp 5.000 jika pindah Biteship).

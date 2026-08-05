# Plan — Fitur Email (Notifikasi & Konfirmasi)

| | |
|---|---|
| **Dokumen** | Plan Fitur Email |
| **Versi** | 0.1 |
| **Tanggal** | 2026-08-05 |
| **Status** | **Draft — menunggu konfirmasi client** |
| **Induk** | `docs/PRD.md` · `docs/implementation-plan.md` |
| **Estimasi** | 3–4 hari kerja (1 developer) |

---

## 1. Ringkasan

Menambahkan notifikasi email ke alur jual-beli:

1. **Email konfirmasi ke pembeli** — setelah checkout storefront berhasil (berisi
   no. order, ringkasan item, total, instruksi pembayaran).
2. **Email notifikasi ke admin** — saat ada pesanan baru dari storefront
   (supaya admin tidak perlu mengecek panel secara manual).

> **Status: DRAFT.** Fitur ini sengaja tidak dikerjakan sebelumnya karena
> membutuhkan konfirmasi client (lihat §5). Jangan mulai implementasi sebelum
> keputusan di §5 disepakati.

---

## 2. Fitur

### 2.1 Email Konfirmasi ke Pembeli

| Aspek | Detail |
|---|---|
| **Trigger** | `CheckoutController@store` — setelah order berhasil dibuat |
| **Penerima** | `email_pembeli` dari form checkout (opsional saat ini → **wajib diisi** bila fitur email aktif) |
| **Isi** | No. order · daftar item (judul, qty, harga) · total · metode bayar · instruksi transfer |
| **Waktu kirim** | Segera (queue) setelah order dibuat |

### 2.2 Email Notifikasi ke Admin

| Aspek | Detail |
|---|---|
| **Trigger** | Order storefront baru (bukan order manual dari panel) |
| **Penerima** | Alamat admin (konfigurasi, lihat §5) |
| **Isi** | No. order · nama pembeli · total · link ke halaman detail order di panel admin |
| **Waktu kirim** | Segera (queue) |

### 2.3 (Opsional, masa depan)

- Email update status: pesanan dikirim / selesai (trigger `OrderStatusService@transition`)
- Email pengingat pembayaran

---

## 3. Rancangan Teknis

### 3.1 File yang Dibuat

| File | Keterangan |
|---|---|
| `app/Mail/OrderConfirmation.php` | Mailable konfirmasi pembeli |
| `app/Mail/NewOrderAdminNotification.php` | Mailable notifikasi admin |
| `app/Mail/OrderConfirmationMail.php` (view) | `resources/views/mail/order-confirmation.blade.php` |
| `app/Mail/NewOrderAdminMail.php` (view) | `resources/views/mail/new-order-admin.blade.php` |
| `config/notification.php` | Konfigurasi: `admin_email`, `admin_name`, `from_address`, `from_name` |
| `app/Notifications/...` (opsional) | Alternatif via Laravel Notifications (pilih salah satu pola) |

### 3.2 Pola Pengiriman

**Rekomendasi: Laravel Mailable + Queue.**

```php
// CheckoutController@store — setelah order dibuat
Mail::to($data['email_pembeli'])
    ->queue(new OrderConfirmation($order));

Mail::to(config('notification.admin_email'))
    ->queue(new NewOrderAdminNotification($order));
```

- `MAIL_QUEUE` via `ShouldQueue` di Mailable → dikirim async
- Butuh **queue worker**: `php artisan queue:work` (atau Horizon) — proses runtime
- Database queue (`QUEUE_CONNECTION=database`) — tidak butuh dependency eksternal

### 3.3 Konfigurasi Env

```
MAIL_MAILER=smtp          # atau log (dev), mailpit, ses, resend, dll
MAIL_HOST=...
MAIL_PORT=...
MAIL_USERNAME=...
MAIL_PASSWORD=...
MAIL_FROM_ADDRESS=noreply@tokobuku.test
MAIL_FROM_NAME="Toko Buku Online"
QUEUE_CONNECTION=database
```

- **Dev**: `MAIL_MAILER=log` cukup untuk verifikasi isi email di `storage/logs/laravel.log`
  (atau Mailpit/Heilov — tidak perlu SMTP asli)

### 3.4 Validasi Email Pembeli

- `CheckoutRequest`: `email_pembeli` berubah dari `nullable` → `required_if`/`required`
  (keputusan client, §5)
- Jika pembeli login, email bisa auto-fill dari akun (sudah ada di `Checkout.vue`)

---

## 4. Testing

| Test | Deskripsi |
|---|---|
| `OrderConfirmation` di-queue saat checkout sukses | `Mail::fake()` + assert queued |
| Email berisi no_order & total | `assertQueued` + cek `Mailable::assertHasTo` |
| Notifikasi admin di-queue saat order storefront | `Mail::fake()` |
| **Tidak** ada email untuk order manual admin | guard di trigger |
| Email pembeli kosong → tidak crash (skip kirim) | kasus guest tanpa email |

---

## 5. ⚠️ Keputusan yang Perlu Konfirmasi Client

| # | Pertanyaan | Opsi | Rekomendasi |
|---|---|---|---|
| 1 | **Alamat email admin** untuk notifikasi order baru | — | Wajib diisi client |
| 2 | **Penyedia email (SMTP)** | Mailtrap (dev) / Brevo / Resend / Gmail SMTP / lainnya | Sesuai infra client |
| 3 | **Email pembeli jadi wajib?** | A. Wajib diisi saat checkout · B. Tetap opsional (email hanya terkirim jika diisi) | **A** — kalau fitur email aktif, lebih baik wajib |
| 4 | **Template bahasa** | Bahasa Indonesia | Indonesia |
| 5 | **Lampiran invoice PDF?** | Ya / Tidak | **Tidak** dulu (butuh lib PDF) |
| 6 | **Notifikasi admin: per order atau ringkasan harian?** | A. Per order (realtime) · B. Ringkasan 1×/hari | **A** — konsisten dengan badge panel |
| 7 | **Ganti logo/nama toko di email** | Nama + warna sesuai brand | Sesuai data yang diberikan client |

---

## 6. Langkah Implementasi (setelah konfirmasi)

1. `config/notification.php` + env (dari jawaban §5)
2. Mailable `OrderConfirmation` + template Blade
3. Mailable `NewOrderAdminNotification` + template Blade
4. Trigger di `CheckoutController@store` (queue)
5. Validasi `email_pembeli` sesuai keputusan §5.3
6. Queue worker setup (`QUEUE_CONNECTION=database` + instruksi run)
7. Test (`Mail::fake()`)
8. Demo ke client

---

## 7. Risiko & Catatan

- **Queue worker harus jalan** di production — tanpa worker email tidak terkirim
  (fallback: `Mail::send` sinkron jika tidak pakai queue)
- Email gagal kirim → jangan sampai menggagalkan order (try/catch + log)
- Alamat `MAIL_FROM_ADDRESS` harus domain toko (bukan `@example.com`) agar tidak
  masuk spam
- Fitur ini **tidak** mengubah alur pembayaran (tetap transfer manual + konfirmasi admin)

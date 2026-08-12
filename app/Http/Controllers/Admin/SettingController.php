<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ActivityAction;
use App\Http\Controllers\Controller;
use App\Models\Courier;
use App\Models\PaymentMethod as PaymentMethodModel;
use App\Models\SalesChannel;
use App\Models\Setting;
use App\Support\ActivityLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;

class SettingController extends Controller
{
    /**
     * Halaman pengaturan toko — identitas lembaga + alamat lembaga.
     */
    public function lembaga(): Response
    {
        return Inertia::render('admin/settings/Lembaga', [
            'nama_lembaga' => Setting::get('store_nama_lembaga', ''),
            'logo_url' => Setting::get('store_logo_url', ''),
            'tagline' => Setting::get('store_tagline', ''),
            'alamat_jalan' => Setting::get('store_alamat_jalan', ''),
            'origin_postal_code' => Setting::get('origin_postal_code', config('biteship.origin_postal_code')),
            'provinsi' => Setting::get('store_provinsi', ''),
            'kabupaten_kota' => Setting::get('store_kabupaten_kota', ''),
            'kecamatan' => Setting::get('store_kecamatan', ''),
            'kelurahan' => Setting::get('store_kelurahan', ''),
            'telepon' => Setting::get('store_telepon', ''),
            'email' => Setting::get('store_email', ''),
            'jam_operasional' => Setting::get('store_jam_operasional', ''),
            'deskripsi' => Setting::get('store_deskripsi', ''),
            'visi' => Setting::get('store_visi', ''),
            'misi' => Setting::get('store_misi', ''),
            'keamanan' => Setting::get('store_keamanan', ''),
            'syarat' => Setting::get('store_syarat', ''),
        ]);
    }

    /**
     * Simpan identitas lembaga + alamat (termasuk logo).
     */
    public function updateLembaga(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'nama_lembaga' => ['required', 'string', 'max:150'],
            'tagline' => ['nullable', 'string', 'max:255'],
            'telepon' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:150'],
            'jam_operasional' => ['nullable', 'string', 'max:100'],
            'deskripsi' => ['nullable', 'string', 'max:2000'],
            'visi' => ['nullable', 'string', 'max:2000'],
            'misi' => ['nullable', 'string', 'max:2000'],
            'keamanan' => ['nullable', 'string', 'max:2000'],
            'syarat' => ['nullable', 'string', 'max:5000'],
            // Alamat gudang — titik asal ongkir & alamat invoice.
            'alamat_jalan' => ['nullable', 'string', 'max:255'],
            'origin_postal_code' => ['required', 'string', 'max:10'],
            'provinsi' => ['nullable', 'string', 'max:100'],
            'kabupaten_kota' => ['nullable', 'string', 'max:100'],
            'kecamatan' => ['nullable', 'string', 'max:100'],
            'kelurahan' => ['nullable', 'string', 'max:100'],
            'logo' => ['nullable', 'image', 'max:2048'],
            'hapus_logo' => ['nullable', 'boolean'],
        ]);

        $postalCode = $validated['origin_postal_code'];
        unset($validated['logo'], $validated['hapus_logo'], $validated['origin_postal_code']);

        foreach ($validated as $key => $value) {
            Setting::set("store_{$key}", $value);
        }

        Setting::set('origin_postal_code', $postalCode);

        // Komposisi alamat lengkap (satu sumber untuk invoice & About).
        Setting::set('store_alamat', collect([
            $validated['alamat_jalan'] ?? '',
            $validated['kelurahan'] ?? '',
            $validated['kecamatan'] ?? '',
            $validated['kabupaten_kota'] ?? '',
            $validated['provinsi'] ?? '',
            $postalCode,
        ])->filter()->implode(', '));

        $this->saveLogo($request);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Identitas lembaga berhasil disimpan.',
        ]);

        ActivityLogger::log(ActivityAction::SettingsUpdate, 'Identitas lembaga diperbarui');

        return back();
    }

    /**
     * Halaman pengaturan toko — daftar ekspedisi (bawaan + custom).
     */
    public function ekspedisi(): Response
    {
        return Inertia::render('admin/settings/Ekspedisi', [
            'couriers' => Courier::query()
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get(['id', 'code', 'name', 'is_active']),
        ]);
    }

    /**
     * Halaman pengaturan toko — daftar metode pembayaran (bawaan + custom).
     */
    public function pembayaran(): Response
    {
        return Inertia::render('admin/settings/Pembayaran', [
            'paymentMethods' => PaymentMethodModel::query()
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get(['id', 'code', 'name', 'is_active']),
        ]);
    }

    /**
     * Halaman pengaturan toko — daftar sumber penjualan (channel).
     */
    public function sumberPenjualan(): Response
    {
        return Inertia::render('admin/settings/SumberPenjualan', [
            'salesChannels' => SalesChannel::query()
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get(['id', 'code', 'name', 'is_active']),
        ]);
    }

    /**
     * Simpan logo lembaga ke R2 (atau hapus bila diminta).
     */
    private function saveLogo(Request $request): void
    {
        $current = Setting::get('store_logo_url', '');

        if ($request->hasFile('logo')) {
            if ($current !== '') {
                $this->deleteStoredFile($current);
            }

            $path = $request->file('logo')->store('logos', 'r2');

            if ($path === false) {
                throw new RuntimeException('Logo gagal disimpan.');
            }

            Setting::set('store_logo_url', Storage::disk('r2')->url($path));
        } elseif ($request->boolean('hapus_logo') && $current !== '') {
            $this->deleteStoredFile($current);
            Setting::set('store_logo_url', null);
        }
    }

    /**
     * Hapus file dari storage (R2 atau lokal).
     */
    private function deleteStoredFile(string $url): void
    {
        $r2Url = rtrim((string) config('filesystems.disks.r2.url'), '/');

        if ($r2Url !== '' && str_starts_with($url, $r2Url)) {
            $path = ltrim(str_replace($r2Url, '', $url), '/');
        } elseif (str_starts_with($url, '/storage/')) {
            // URL relatif (local dev / test) — path langsung di disk r2.
            $path = ltrim(substr($url, strlen('/storage/')), '/');
        } else {
            return;
        }

        Storage::disk('r2')->delete($path);
    }

    /**
     * Halaman pengaturan API key — hanya status ter-mask, nilai asli tidak pernah dikirim ke browser.
     */
    public function apiKey(): Response
    {
        $key = Setting::getSecret('biteship_api_key') ?: config('biteship.key');

        return Inertia::render('admin/settings/ApiKey', [
            'has_api_key' => filled($key),
            'key_masked' => filled($key) ? '••••••'.substr($key, -4) : null,
            'key_source' => Setting::where('key', 'biteship_api_key')->exists() ? 'database' : 'environment',
        ]);
    }

    /**
     * Simpan API key Biteship (tersimpan terenkripsi).
     *
     * Key tidak pernah di-echo ke response; input kosong = pertahankan key
     * yang sudah ada; hapus hanya lewat aksi eksplisit `clear_key`.
     */
    public function updateApiKey(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'api_key' => ['nullable', 'string', 'min:10', 'max:500'],
            'clear_key' => ['nullable', 'boolean'],
        ]);

        if (filled($validated['api_key'] ?? null)) {
            Setting::setSecret('biteship_api_key', $validated['api_key']);
        } elseif ($validated['clear_key'] ?? false) {
            Setting::setSecret('biteship_api_key', null);
        }

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'API key berhasil disimpan.',
        ]);

        // Nilai key TIDAK pernah dicatat — hanya aksinya.
        ActivityLogger::log(
            ActivityAction::SettingsUpdate,
            ($validated['clear_key'] ?? false) && ! filled($validated['api_key'] ?? null)
                ? 'API key Biteship dihapus'
                : 'API key Biteship diperbarui',
        );

        return back();
    }
}

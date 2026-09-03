<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ActivityAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateApiKeyRequest;
use App\Http\Requests\Admin\UpdateLembagaRequest;
use App\Http\Requests\Admin\UpdateWaTemplateRequest;
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
            'hari_buka' => Setting::get('store_hari_buka', ''),
            'jam_buka' => Setting::get('store_jam_buka', ''),
            'jam_tutup' => Setting::get('store_jam_tutup', ''),
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
    public function updateLembaga(UpdateLembagaRequest $request): RedirectResponse
    {
        $validated = $request->validated();

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
            'salesChannels' => SalesChannel::query()->orderBy('sort_order')
                ->orderBy('name')
                ->get(['id', 'code', 'name', 'is_active']),
        ]);
    }

    /**
     * Halaman pengaturan — template pesan WhatsApp (wa.me) untuk info
     * stok pre-order tersedia.
     */
    public function waTemplate(): Response
    {
        return Inertia::render('admin/settings/WaTemplate', [
            'wa_template_ready' => Setting::get('wa_template_ready', config('whatsapp.template_ready')),
        ]);
    }

    /**
     * Simpan template pesan WhatsApp.
     */
    public function updateWaTemplate(UpdateWaTemplateRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        Setting::set('wa_template_ready', $validated['wa_template_ready']);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Template WhatsApp berhasil disimpan.',
        ]);

        ActivityLogger::log(ActivityAction::SettingsUpdate, 'Template WhatsApp diperbarui');

        return back();
    }

    /**
     * Simpan logo lembaga ke public storage (atau hapus bila diminta).
     */
    private function saveLogo(Request $request): void
    {
        $current = Setting::get('store_logo_url', '');

        if ($request->hasFile('logo')) {
            if ($current !== '') {
                $this->deleteStoredFile($current);
            }

            $path = $request->file('logo')->store('logos', 'public');

            if ($path === false) {
                throw new RuntimeException('Logo gagal disimpan.');
            }

            Setting::set('store_logo_url', Storage::disk('public')->url($path));
        } elseif ($request->boolean('hapus_logo') && $current !== '') {
            $this->deleteStoredFile($current);
            Setting::set('store_logo_url', '/logo-pcp.png');
        }
    }

    /**
     * Hapus file dari storage.
     *
     * Mendukung URL lokal (/storage/...) dan URL R2 lama (https://cdn.miniapps.id/...).
     * Hapus dari kedua disk untuk kompatibilitas fake & legacy.
     */
    private function deleteStoredFile(string $url): void
    {
        $r2Url = rtrim((string) config('filesystems.disks.r2.url', ''), '/');
        $path = null;

        if ($r2Url !== '' && str_starts_with($url, $r2Url)) {
            $path = ltrim(str_replace($r2Url, '', $url), '/');
        } elseif (str_contains($url, '/storage/')) {
            $path = ltrim(substr($url, (int) strpos($url, '/storage/') + strlen('/storage/')), '/');
        } elseif (preg_match('#(logos|article-images|covers)/.+#', $url, $m)) {
            $path = $m[0];
        } else {
            return;
        }

        Storage::disk('public')->delete($path);

        try {
            Storage::disk('r2')->delete($path);
        } catch (\Throwable) {
            // r2 disk mungkin belum dikonfigurasi
        }
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
    public function updateApiKey(UpdateApiKeyRequest $request): RedirectResponse
    {
        $validated = $request->validated();

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

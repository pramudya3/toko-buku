<?php

namespace Database\Seeders;

use App\Models\Book;
use App\Models\Category;
use App\Models\City;
use App\Models\District;
use App\Models\Province;
use App\Models\User;
use App\Models\Village;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Master data dari dump db_penerbit (MariaDB → PostgreSQL):
 * kategori (mst_tipebarang), buku (mst_item), dan customer yang tercatat
 * pada penjualan 1–14 Agustus (mst_customer).
 *
 * Catatan bundling: Kategori Bdl (Bundling) dipertahankan HANYA untuk historis
 * Agustus 2026 (40 baris order_items Bdl). Untuk bundling baru JANGAN buat
 * Book Bdl lagi — pakai promotions.promo_type='bundle' (promotion_book ke
 * komponen ANK/PRN). Bdl dianggap deprecated (@deprecated).
 *
 * - Buku: kode_sku = kditem lama, penulis = kolom merk, harga = hrgjual.
 * - Customer: tanpa email di sumber → dibuat null (tidak bisa login);
 *   tier dipetakan dari kdstatus (Bz→bazaf, GR/grosir→reseller, lainnya reguler).
 *
 * Perbaikan:
 * - SKU harus sesuai singkatan kategori (kode + 6 digit, urut tanpa jeda).
 *   Contoh: PRN000001, BI000020, Bdl000007. SKU anomali (001, BAI, RMP,
 *   "Bundling ...") otomatis dialokasikan ke nomor urut terkecil yang
 *   kosong untuk kategorinya.
 * - Buku didedup berdasarkan judul (case-insensitive, trim). Jika judul
 *   sudah ada, update field yang masih kosong (penulis, harga, kategori)
 *   dan perbaiki SKU yang tidak valid — jangan insert duplikat.
 *
 * Idempotent — aman dipanggil ulang.
 */
class PenerbitMasterSeeder extends Seeder
{
    public function run(): void
    {
        $data = $this->readData();

        // ---------- Kategori ----------
        $categories = collect($data['categories'])->mapWithKeys(
            function (array $row): array {
                // Cocokkan berdasarkan kode ATAU nama (case-insensitive) agar
                // tidak dobel dengan kategori bawaan seperti "Kisah".
                $category = Category::withTrashed()
                    ->where(fn ($query) => $query
                        ->where('kode', $row['kode'])
                        ->orWhereRaw('lower(nama) = ?', [strtolower($row['nama'])]))
                    ->first();

                if ($category === null) {
                    $category = Category::create([
                        'nama' => $row['nama'],
                        'kode' => $row['kode'],
                    ]);
                } elseif ($category->kode === null) {
                    $category->update(['kode' => $row['kode']]);
                }

                return [$row['kode'] => $category];
            },
        );

        // ---------- Siapkan alokasi SKU ----------
        // Kumpulkan SKU yang sudah terpakai (termasuk soft-deleted) dan
        // nomor urut per kategori agar alokasi baru berurutan tanpa jeda.
        $usedSkus = Book::withTrashed()
            ->whereNotNull('kode_sku')
            ->pluck('kode_sku')
            ->flip()
            ->toArray();

        $usedNumbersByCat = [];
        foreach ($categories as $kode => $category) {
            $usedNumbersByCat[$kode] = [];
        }

        foreach (Book::withTrashed()->whereNotNull('kode_sku')->get(['kode_sku']) as $book) {
            foreach ($categories as $kode => $category) {
                if ($this->isValidSku((string) $book->kode_sku, $kode)) {
                    $num = (int) substr((string) $book->kode_sku, strlen($kode));
                    $usedNumbersByCat[$kode][$num] = true;
                    break;
                }
            }
        }

        // Pre-mark SKU valid dari JSON yang belum ada di DB — supaya
        // alokasi untuk anomali tidak menabrak nomor yang akan dipakai
        // baris valid berikutnya (mis. PRN000001 sudah dipesan JSON).
        foreach ($data['books'] as $row) {
            $catKode = $row['kategori_kode'];
            $sku = (string) $row['kode_sku'];
            if ($catKode !== null && isset($categories[$catKode]) && $this->isValidSku($sku, $catKode)) {
                $num = (int) substr($sku, strlen($catKode));
                $usedNumbersByCat[$catKode][$num] = true;
                $usedSkus[$sku] = true;
            }
        }

        // ---------- Buku ----------
        foreach ($data['books'] as $row) {
            $catKode = $row['kategori_kode'];
            $category = $catKode !== null ? $categories[$catKode] ?? null : null;
            $originalSku = (string) $row['kode_sku'];
            $judul = trim((string) $row['judul']);

            // Tentukan SKU yang seharusnya (valid & urut).
            $correctedSku = $originalSku;
            if ($category !== null && ! $this->isValidSku($originalSku, $category->kode)) {
                $correctedSku = $this->generateSku($category, $usedNumbersByCat[$category->kode], $usedSkus);
            }

            // 1) Cek duplikat berdasarkan judul (case-insensitive, trim).
            $existingByJudul = Book::withTrashed()
                ->whereRaw('LOWER(TRIM(judul)) = ?', [strtolower($judul)])
                ->first();

            if ($existingByJudul !== null) {
                $updates = [];

                // Penulis: isi jika masih kosong dan data baru ada.
                if ((empty($existingByJudul->penulis) || trim((string) $existingByJudul->penulis) === '') && ! empty($row['penulis'])) {
                    $updates['penulis'] = $row['penulis'];
                }

                // Harga: update jika masih 0 / null.
                if ((int) ($existingByJudul->harga ?? 0) === 0 && (int) $row['harga'] !== 0) {
                    $updates['harga'] = $row['harga'];
                }

                // Kategori: isi jika masih kosong.
                if (empty($existingByJudul->category_id) && $category !== null) {
                    $updates['category_id'] = $category->id;
                } elseif ($category !== null && (string) $existingByJudul->category_id !== (string) $category->id) {
                    // Jika kategori berbeda, tetap update ke kategori dari dump (sumber kebenaran).
                    // Hanya jika existing belum punya kategori atau kategori lama tidak valid?
                    // Untuk PCP, kita samakan dengan dump.
                    $updates['category_id'] = $category->id;
                }

                // SKU: perbaiki jika saat ini tidak valid untuk kategorinya.
                $currentSku = (string) $existingByJudul->kode_sku;
                $needsSkuFix = false;
                if ($category !== null) {
                    if (empty($currentSku) || ! $this->isValidSku($currentSku, $category->kode)) {
                        $needsSkuFix = true;
                    }
                }

                if ($needsSkuFix) {
                    // Pastikan correctedSku belum dipakai buku lain
                    if (! isset($usedSkus[$correctedSku]) || $existingByJudul->kode_sku === $correctedSku) {
                        $updates['kode_sku'] = $correctedSku;
                        // Tandai SKU baru sebagai terpakai
                        $usedSkus[$correctedSku] = true;
                        if ($category !== null) {
                            $num = (int) substr($correctedSku, strlen($category->kode));
                            $usedNumbersByCat[$category->kode][$num] = true;
                        }
                    }
                } else {
                    // SKU sudah valid, tandai sebagai terpakai
                    if ($currentSku !== '') {
                        $usedSkus[$currentSku] = true;
                        if ($category !== null && $this->isValidSku($currentSku, $category->kode)) {
                            $num = (int) substr($currentSku, strlen($category->kode));
                            $usedNumbersByCat[$category->kode][$num] = true;
                        }
                    }
                }

                if ($updates !== []) {
                    $existingByJudul->update($updates);
                }

                // Tandai SKU yang dipakai (baik lama maupun baru) agar tidak dialokasikan ulang
                if ($category !== null && $this->isValidSku($correctedSku, $category->kode)) {
                    $usedSkus[$correctedSku] = true;
                }

                continue;
            }

            // 2) Tidak ada duplikat judul — cek duplikat SKU (idempotent).
            $existingBySku = Book::withTrashed()->where('kode_sku', $correctedSku)->first();
            if ($existingBySku !== null) {
                // SKU sudah ada (judul berbeda? sudah ditangani di atas untuk kolisi)
                // Update field kosong jika ada
                $updates = [];
                if (empty($existingBySku->penulis) && ! empty($row['penulis'])) {
                    $updates['penulis'] = $row['penulis'];
                }
                if ((int) ($existingBySku->harga ?? 0) === 0 && (int) $row['harga'] !== 0) {
                    $updates['harga'] = $row['harga'];
                }
                if ($updates !== []) {
                    $existingBySku->update($updates);
                }
                // Tandai
                $usedSkus[$correctedSku] = true;
                if ($category !== null) {
                    $num = (int) substr($correctedSku, strlen($category->kode));
                    $usedNumbersByCat[$category->kode][$num] = true;
                }

                continue;
            }

            // 3) Buat buku baru dengan SKU yang sudah terkoreksi & urut.
            Book::create([
                'kode_sku' => $correctedSku,
                'judul' => $judul,
                'penulis' => $row['penulis'],
                'harga' => $row['harga'],
                'category_id' => $category?->id,
            ]);

            $usedSkus[$correctedSku] = true;
            if ($category !== null) {
                $num = (int) substr($correctedSku, strlen($category->kode));
                $usedNumbersByCat[$category->kode][$num] = true;
            }
        }

        // ---------- Customer ---------- (pecah alamat per kolom wilayah)
        foreach ($data['customers'] as $row) {
            $existing = User::withTrashed()
                ->where('name', $row['nama'])
                ->whereNull('email')
                ->first();

            // Normalisasi HP: field `kota` di dump sering berisi nomor HP, bukan kota.
            $rawKota = trim((string) ($row['kota'] ?? ''));
            $hp = $row['hp'] ?? null;
            if ($rawKota !== '' && $this->isPhone($rawKota)) {
                $hp = $hp ? $hp.'/'.$rawKota : $rawKota;
                $rawKota = '';
            }
            if ($hp !== null) {
                $hp = trim((string) $hp);
                $hp = $hp !== '' ? $hp : null;
            }

            $resolved = $this->resolveWilayah((string) ($row['alamat'] ?? ''), $rawKota);

            if ($existing !== null) {
                // Backfill jika kolom wilayah masih kosong atau berisi nomor HP (data lama salah simpan kota=HP).
                $updates = [];
                if (empty($existing->alamat) && $resolved['alamat'] !== '') {
                    $updates['alamat'] = $resolved['alamat'];
                }
                if ((empty($existing->provinsi) || $this->isPhone((string) $existing->provinsi)) && $resolved['provinsi'] !== null) {
                    $updates['provinsi'] = $resolved['provinsi'];
                }
                if ((empty($existing->kabupaten_kota) || $this->isPhone((string) $existing->kabupaten_kota)) && $resolved['kabupaten_kota'] !== null) {
                    $updates['kabupaten_kota'] = $resolved['kabupaten_kota'];
                }
                if ((empty($existing->kecamatan) || $this->isPhone((string) $existing->kecamatan)) && $resolved['kecamatan'] !== null) {
                    $updates['kecamatan'] = $resolved['kecamatan'];
                }
                if ((empty($existing->kelurahan) || $this->isPhone((string) $existing->kelurahan)) && $resolved['kelurahan'] !== null) {
                    $updates['kelurahan'] = $resolved['kelurahan'];
                }
                if (empty($existing->village_code) && $resolved['village_code'] !== null) {
                    $updates['village_code'] = $resolved['village_code'];
                }
                if ((empty($existing->kode_pos) || $this->isPhone((string) $existing->kode_pos)) && $resolved['kode_pos'] !== null) {
                    $updates['kode_pos'] = $resolved['kode_pos'];
                }
                if (empty($existing->whatsapp_number) && $hp !== null) {
                    $updates['whatsapp_number'] = $hp;
                } elseif (! empty($existing->kabupaten_kota) && $this->isPhone((string) $existing->kabupaten_kota) && ! empty($existing->whatsapp_number) && $hp !== null && $existing->whatsapp_number !== $hp) {
                    // Jika kabupaten masih HP tapi whatsapp sudah terisi sebagian, gabungkan jika beda
                    if (! str_contains((string) $existing->whatsapp_number, (string) $hp)) {
                        $updates['whatsapp_number'] = $existing->whatsapp_number.'/'.$hp;
                    }
                }
                if ($updates !== []) {
                    $existing->update($updates);
                }

                continue;
            }

            User::create([
                'name' => $row['nama'],
                'email' => null,
                'password' => Hash::make(Str::random(32)),
                'whatsapp_number' => $hp,
                'status_pelanggan' => $row['tier'],
                'alamat' => $resolved['alamat'],
                'provinsi' => $resolved['provinsi'],
                'kabupaten_kota' => $resolved['kabupaten_kota'],
                'kecamatan' => $resolved['kecamatan'],
                'kelurahan' => $resolved['kelurahan'],
                'village_code' => $resolved['village_code'],
                'kode_pos' => $resolved['kode_pos'],
            ]);
        }
    }

    /**
     * Validasi SKU harus persis "{kode}{6 digit}".
     */
    private function isValidSku(string $sku, string $kode): bool
    {
        return preg_match('/^'.preg_quote($kode, '/').'\d{6}$/', $sku) === 1;
    }

    /**
     * Alokasikan SKU berikutnya yang belum terpakai untuk kategori
     * (cari celah terkecil, bukan max+1, agar urut tanpa jeda).
     *
     * @param  array<int, true>  $usedNumbers
     * @param  array<string, true>  $usedSkus
     */
    private function generateSku(Category $category, array &$usedNumbers, array &$usedSkus): string
    {
        $kode = $category->kode;

        for ($n = 1; $n <= 999999; $n++) {
            if (isset($usedNumbers[$n])) {
                continue;
            }

            $candidate = $kode.str_pad((string) $n, 6, '0', STR_PAD_LEFT);

            if (isset($usedSkus[$candidate])) {
                $usedNumbers[$n] = true;

                continue;
            }

            $usedNumbers[$n] = true;
            $usedSkus[$candidate] = true;

            return $candidate;
        }

        throw new \RuntimeException("SKU untuk kategori {$kode} sudah habis.");
    }

    /**
     * Data hasil parsing dump db_penerbit.sql.
     *
     * @return array{meta: array<string, mixed>, categories: array<int, array<string, mixed>>, books: array<int, array<string, mixed>>, customers: array<int, array<string, mixed>>, orders: array<int, array<string, mixed>>}
     */
    private function readData(): array
    {
        $path = database_path('data/db_penerbit_penjualan_agustus.json');

        $decoded = json_decode((string) file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);

        assert(is_array($decoded));

        return $decoded;
    }

    private function isPhone(string $value): bool
    {
        $v = trim($value);
        if ($v === '') {
            return false;
        }
        if (! preg_match('/^[0-9\s\/\+\-\.]+$/', $v)) {
            return false;
        }
        $digits = (string) preg_replace('/\D/', '', $v);

        // Izinkan 2 nomor digabung dengan "/" (24 digit) — cukup >=8 digit
        return $digits !== '' && strlen($digits) >= 8 && strlen($digits) <= 30;
    }

    private function normalizeCityName(string $name): string
    {
        $name = strtoupper(trim($name));
        $name = (string) preg_replace('/^KAB\.?\s+/', 'KABUPATEN ', $name);

        return (string) preg_replace('/^KOTA\s+/', 'KOTA ', $name);
    }

    /**
     * Pecah alamat bebas menjadi kolom wilayah sesuai skema users.
     * Prioritas: kode_pos (jika ada) → name matching provinsi/kota/kecamatan/desa.
     *
     * @return array{alamat: string|null, provinsi: string|null, kabupaten_kota: string|null, kecamatan: string|null, kelurahan: string|null, village_code: string|null, kode_pos: string|null}
     */
    private function resolveWilayah(string $alamat, string $rawKota): array
    {
        $alamat = trim($alamat);
        $upper = strtoupper($alamat);
        // Normalisasi KAB. → KABUPATEN untuk matching
        $upperNorm = (string) preg_replace('/\bKAB\.?\s+/', 'KABUPATEN ', $upper);
        $upperNorm = str_replace('  ', ' ', $upperNorm);

        $result = [
            'alamat' => $alamat !== '' ? $alamat : null,
            'provinsi' => null,
            'kabupaten_kota' => null,
            'kecamatan' => null,
            'kelurahan' => null,
            'village_code' => null,
            'kode_pos' => null,
        ];

        if ($alamat === '' && $rawKota === '') {
            return $result;
        }

        // 1. Ekstrak kode pos 5 digit terakhir
        $kodePos = null;
        if (preg_match_all('/\b(\d{5})\b/', $upperNorm, $m)) {
            $kodePos = end($m[1]);
            $result['kode_pos'] = $kodePos;
        }

        // 2. Cari provinsi (longest match dulu agar DKI JAKARTA > JAKARTA)
        $provinces = Province::all()->sortByDesc(fn ($p) => strlen($p->name));
        $province = null;
        foreach ($provinces as $p) {
            $pName = strtoupper($p->name);
            if ($pName !== '' && str_contains($upperNorm, $pName)) {
                $province = $p;
                $result['provinsi'] = $p->name;
                break;
            }
        }
        // Fallback dari rawKota jika provinsi masih kosong dan rawKota adalah nama kota valid
        $rawKotaNorm = $this->normalizeCityName($rawKota);
        if ($province === null && $rawKotaNorm !== '') {
            $cityByRaw = City::whereRaw('UPPER(name) = ?', [$rawKotaNorm])->first()
                ?? City::whereRaw('UPPER(name) LIKE ?', ['%'.strtoupper($rawKota).'%'])->first();
            if ($cityByRaw !== null) {
                $province = Province::where('code', $cityByRaw->province_code)->first();
                if ($province !== null) {
                    $result['provinsi'] = $province->name;
                }
            }
        }

        // 3. Cari kabupaten/kota (filter by provinsi jika ketemu)
        $city = null;
        $cityCandidates = $province !== null
            ? City::where('province_code', $province->code)->get()
            : City::all();
        $cityCandidates = $cityCandidates->sortByDesc(fn ($c) => strlen($c->name));
        foreach ($cityCandidates as $c) {
            $cName = strtoupper($c->name);
            if ($cName !== '' && str_contains($upperNorm, $cName)) {
                $city = $c;
                $result['kabupaten_kota'] = $c->name;
                break;
            }
        }
        // Fallback suffix: "Bekasi" di alamat harus match "KOTA BEKASI"
        if ($city === null) {
            foreach ($cityCandidates as $c) {
                $suffix = (string) preg_replace('/^(KOTA|KABUPATEN)\s+/', '', strtoupper($c->name));
                if ($suffix !== '' && $suffix !== strtoupper($c->name) && str_contains($upperNorm, $suffix)) {
                    if (preg_match('/\b'.preg_quote($suffix, '/').'\b/', $upperNorm) === 1) {
                        $city = $c;
                        $result['kabupaten_kota'] = $c->name;
                        if ($result['provinsi'] === null) {
                            $prov = Province::where('code', $c->province_code)->first();
                            $result['provinsi'] = $prov?->name;
                            $province = $prov;
                        }
                        break;
                    }
                }
            }
        }
        // Fallback untuk alamat single-word seperti "Malang" (tanpa KAB/KOTA) → cari LIKE %MALANG%
        if ($city === null && $upperNorm !== '' && ! str_contains($upperNorm, ',') && strlen($upperNorm) < 30 && ! $this->isPhone($upperNorm)) {
            $city = City::whereRaw('UPPER(name) LIKE ?', ['%'.$upperNorm.'%'])->orderByRaw('LENGTH(name)')->first();
            if ($city !== null) {
                $result['kabupaten_kota'] = $city->name;
                if ($result['provinsi'] === null) {
                    $prov = Province::where('code', $city->province_code)->first();
                    $result['provinsi'] = $prov?->name;
                    $province = $prov;
                }
            }
        }
        // Fallback: rawKota eksplisit (mis. "Malang" tanpa prefix)
        if ($city === null && $rawKotaNorm !== '') {
            $city = $cityCandidates->first(fn (City $c) => strtoupper($c->name) === $rawKotaNorm)
                ?? $cityCandidates->first(fn (City $c) => str_contains(strtoupper($c->name), strtoupper($rawKota)))
                ?? City::whereRaw('UPPER(name) LIKE ?', ['%'.strtoupper($rawKota).'%'])->first();
            if ($city !== null) {
                $result['kabupaten_kota'] = $city->name;
                if ($result['provinsi'] === null) {
                    $prov = Province::where('code', $city->province_code)->first();
                    $result['provinsi'] = $prov?->name;
                    $province = $prov;
                }
            } elseif ($rawKota !== '') {
                // Tidak ketemu di master, simpan apa adanya biar tidak hilang
                $result['kabupaten_kota'] = trim($rawKota);
            }
        }

        // 4. Cari kecamatan — prioritas: eksplisit "Kec. X" (paling akurat)
        $district = null;
        $explicitKec = null;
        if (preg_match('/\bkec(?:\.|amatan)?\s*\.?\s*([a-zA-Z\s\.]+?)(?:,|\s+Kel|\s+Desa|\s+Kota|\s+Kabupaten|\s+Jawa|\s+Banten|\s+DKI|\s+DI\s|\s+Kalimantan|\s+Sulawesi|\s+Sumatera|\s+Bali|\s+Nusa|$)/i', $alamat, $mm)) {
            $explicitKecRaw = trim($mm[1]);
            $explicitKecRaw = (string) preg_replace('/\bSel\.?\b/i', 'Selatan', $explicitKecRaw);
            $explicitKecRaw = (string) preg_replace('/\bUt\.?\b/i', 'Utara', $explicitKecRaw);
            $explicitKecRaw = trim($explicitKecRaw, ' .,');
            if ($explicitKecRaw !== '') {
                // Coba full dulu (mis. "Bekasi Selatan"), fallback ke kata pertama ("Jatiasih" dari "Jatiasih Bekasi")
                $candidatesToTry = [$explicitKecRaw];
                $firstWord = trim(explode(' ', $explicitKecRaw)[0] ?? '');
                if ($firstWord !== '' && strtoupper($firstWord) !== strtoupper($explicitKecRaw)) {
                    $candidatesToTry[] = $firstWord;
                }
                $cand = null;
                foreach ($candidatesToTry as $tryName) {
                    $cand = District::whereRaw('UPPER(name) = ?', [strtoupper($tryName)])->first()
                        ?? District::whereRaw('UPPER(name) LIKE ?', ['%'.strtoupper($tryName).'%'])->first();
                    if ($cand !== null) {
                        break;
                    }
                }
                if ($cand !== null) {
                    $district = $cand;
                    $result['kecamatan'] = $cand->name;
                    if ($result['kode_pos'] === null && ! empty($cand->kode_pos)) {
                        $result['kode_pos'] = $cand->kode_pos;
                    }
                    // Explicit Kec paling akurat → override kota/provinsi dari kecamatan
                    $cityFromDist = City::where('code', $cand->city_code)->first();
                    if ($cityFromDist !== null) {
                        $city = $cityFromDist;
                        $result['kabupaten_kota'] = $cityFromDist->name;
                        if ($result['provinsi'] === null) {
                            $prov = Province::where('code', $cityFromDist->province_code)->first();
                            $result['provinsi'] = $prov?->name;
                            $province = $prov;
                        } else {
                            // Pastikan provinsi konsisten dengan kota dari kecamatan
                            $prov = Province::where('code', $cityFromDist->province_code)->first();
                            if ($prov !== null && strtoupper($prov->name) !== strtoupper((string) $result['provinsi'])) {
                                $result['provinsi'] = $prov->name;
                                $province = $prov;
                            }
                        }
                    }
                }
            }
        }
        if ($district === null) {
            $districtCandidates = $city !== null
                ? District::where('city_code', $city->code)->get()
                : District::all();
            // Untuk performa 7k rows, batasi jika tidak ada city: hanya cek yang namanya muncul di alamat (jangan load all 7k? sudah load)
            // Sort longest first
            $districtCandidates = $districtCandidates->sortByDesc(fn ($d) => strlen($d->name));
            foreach ($districtCandidates as $d) {
                $dName = strtoupper($d->name);
                if ($dName !== '' && str_contains($upperNorm, $dName)) {
                    // Pastikan tidak salah match substring pendek (mis. "BALI" di dalam alamat). District minimal 3 huruf & cek word boundary
                    if (preg_match('/\b'.preg_quote($dName, '/').'\b/', $upperNorm) === 1) {
                        $district = $d;
                        $result['kecamatan'] = $d->name;
                        // Kode pos dari kecamatan jika belum ada
                        if ($result['kode_pos'] === null && ! empty($d->kode_pos)) {
                            $result['kode_pos'] = $d->kode_pos;
                        }
                        break;
                    }
                }
            }
        }

        // 5. Cari kelurahan/desa (filter by kecamatan jika ketemu)
        $village = null;
        if ($district !== null) {
            $villageCandidates = Village::where('district_code', $district->code)->get()->sortByDesc(fn ($v) => strlen($v->name));
            foreach ($villageCandidates as $v) {
                $vName = strtoupper($v->name);
                if ($vName !== '' && str_contains($upperNorm, $vName)) {
                    if (preg_match('/\b'.preg_quote($vName, '/').'\b/', $upperNorm) === 1) {
                        $village = $v;
                        $result['kelurahan'] = $v->name;
                        $result['village_code'] = $v->code;
                        if (! empty($v->kode_pos)) {
                            $result['kode_pos'] = $v->kode_pos;
                        }
                        break;
                    }
                }
            }
        } elseif ($city === null) {
            // Tanpa kecamatan & tanpa kota, coba cari desa yang namanya muncul di alamat (batasi untuk performa)
            // Kita skip jika alamat single-word kota (sudah handle di atas).
            // Coba ekstrak kandidat kelurahan dari split alamat (2 segmen pertama)
            $parts = array_map('trim', explode(',', $alamat));
            $partsUpper = array_map('strtoupper', $parts);
            foreach (array_slice($partsUpper, 0, 3) as $partUpper) {
                $partUpper = trim($partUpper);
                if ($partUpper === '' || strlen($partUpper) < 3) {
                    continue;
                }
                // Hapus RT/RW, nomor, dll: ambil kata pertama yang kapital panjang
                $village = Village::whereRaw('UPPER(name) = ?', [$partUpper])->first();
                if ($village === null) {
                    $village = Village::whereRaw('UPPER(name) LIKE ?', ['%'.$partUpper.'%'])->first();
                }
                if ($village !== null) {
                    $result['kelurahan'] = $village->name;
                    $result['village_code'] = $village->code;
                    if (! empty($village->kode_pos)) {
                        $result['kode_pos'] = $village->kode_pos;
                    }
                    // Hydrate kecamatan/kabupaten/provinsi dari desa ini jika masih kosong
                    if ($result['kecamatan'] === null) {
                        $d = District::where('code', $village->district_code)->first();
                        if ($d !== null) {
                            $result['kecamatan'] = $d->name;
                            $district = $d;
                            if ($result['kabupaten_kota'] === null) {
                                $c = City::where('code', $d->city_code)->first();
                                if ($c !== null) {
                                    $result['kabupaten_kota'] = $c->name;
                                    $city = $c;
                                    if ($result['provinsi'] === null) {
                                        $p = Province::where('code', $c->province_code)->first();
                                        $result['provinsi'] = $p?->name;
                                    }
                                }
                            }
                        }
                    }
                    break;
                }
            }
        }

        // 6. Fallback kecamatan dari split alamat jika masih kosong: ambil segmen ke-n yang belum terpakai
        if ($result['kecamatan'] === null && $district === null) {
            $parts = array_map('trim', explode(',', $alamat));
            // Cari segmen yang mengandung kata KEC. / KECAMATAN
            foreach ($parts as $part) {
                if (preg_match('/\bkec\.?\s+([a-zA-Z\s]+)/i', $part, $mm)) {
                    $candidate = trim($mm[1]);
                    if ($candidate !== '') {
                        $result['kecamatan'] = strtoupper($candidate);
                        break;
                    }
                }
            }
        }

        // 7. Fallback kelurahan dari pola KEL. / DESA
        if ($result['kelurahan'] === null) {
            $parts = array_map('trim', explode(',', $alamat));
            foreach ($parts as $part) {
                if (preg_match('/\b(kel|desa)\.?\s+([a-zA-Z\s]+)/i', $part, $mm)) {
                    $candidate = trim($mm[2]);
                    if ($candidate !== '' && strlen($candidate) > 2) {
                        $result['kelurahan'] = strtoupper($candidate);
                        break;
                    }
                }
            }
        }

        // 8. RawKota fallback terakhir jika kabupaten masih kosong
        if ($result['kabupaten_kota'] === null && $rawKota !== '' && ! $this->isPhone($rawKota)) {
            $result['kabupaten_kota'] = $this->normalizeCityName($rawKota);
        }

        // Normalisasi hasil ke Title Case sesuai master (biar konsisten dengan AddressFields)
        // DB menyimpan UPPERCASE, tapi kita simpan apa adanya dari master (sudah uppercase). Biarkan.
        return $result;
    }
}

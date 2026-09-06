<?php

namespace Database\Seeders;

use App\Models\City;
use App\Models\District;
use App\Models\Province;
use App\Models\User;
use App\Models\Village;
use Illuminate\Database\Seeder;

/**
 * Backfill khusus pelanggan: pecah alamat → kolom wilayah tanpa sentuh kategori/buku.
 * Idempotent & aman di-rerun — hanya update kolom yang kosong atau berisi HP.
 *
 * Jalankan: php artisan db:seed --class=CustomerWilayahBackfillSeeder
 */
class CustomerWilayahBackfillSeeder extends Seeder
{
    public function run(): void
    {
        $users = User::where('is_admin', false)
            ->withTrashed()
            ->get();

        $updated = 0;
        $skipped = 0;

        foreach ($users as $user) {
            // Lewati jika alamat kosong total (mis. Jono) — tidak bisa di-resolve
            if (empty($user->alamat) && empty($user->kabupaten_kota) && empty($user->provinsi)) {
                // Coba tetap resolve jika ada alamat tersimpan di JSON? Tapi untuk Jono alamat memang kosong.
                // Kita skip.
                if (empty($user->alamat)) {
                    $skipped++;

                    continue;
                }
            }

            $rawKota = (string) ($user->kabupaten_kota ?? '');
            // Jika kabupaten berisi HP, anggap kosong untuk resolve (HP akan dipindah ke whatsapp)
            if ($rawKota !== '' && $this->isPhone($rawKota)) {
                $rawKota = '';
            }

            $resolved = $this->resolveWilayah((string) ($user->alamat ?? ''), $rawKota);

            $updates = [];

            if (empty($user->provinsi) && $resolved['provinsi'] !== null) {
                $updates['provinsi'] = $resolved['provinsi'];
            }
            // Kabupaten: kosong atau berisi HP → replace
            if ((empty($user->kabupaten_kota) || $this->isPhone((string) $user->kabupaten_kota)) && $resolved['kabupaten_kota'] !== null) {
                $updates['kabupaten_kota'] = $resolved['kabupaten_kota'];
            }
            if ((empty($user->kecamatan) || $this->isPhone((string) $user->kecamatan)) && $resolved['kecamatan'] !== null) {
                $updates['kecamatan'] = $resolved['kecamatan'];
            }
            if ((empty($user->kelurahan) || $this->isPhone((string) $user->kelurahan)) && $resolved['kelurahan'] !== null) {
                $updates['kelurahan'] = $resolved['kelurahan'];
            }
            if (empty($user->village_code) && $resolved['village_code'] !== null) {
                $updates['village_code'] = $resolved['village_code'];
            }
            if ((empty($user->kode_pos) || $this->isPhone((string) $user->kode_pos)) && $resolved['kode_pos'] !== null) {
                $updates['kode_pos'] = $resolved['kode_pos'];
            }

            if ($updates !== []) {
                $user->update($updates);
                $updated++;
            } else {
                $skipped++;
            }
        }

        $this->command?->info("CustomerWilayahBackfill: {$updated} updated, {$skipped} skipped (total ".count($users).').');
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

        return $digits !== '' && strlen($digits) >= 8 && strlen($digits) <= 30;
    }

    private function normalizeCityName(string $name): string
    {
        $name = strtoupper(trim($name));
        $name = (string) preg_replace('/^KAB\.?\s+/', 'KABUPATEN ', $name);

        return (string) preg_replace('/^KOTA\s+/', 'KOTA ', $name);
    }

    /**
     * Pecah alamat bebas menjadi kolom wilayah — copy dari PenerbitMasterSeeder::resolveWilayah
     * agar seeder ini standalone tanpa dependensi cross-seeder.
     *
     * @return array{alamat: string|null, provinsi: string|null, kabupaten_kota: string|null, kecamatan: string|null, kelurahan: string|null, village_code: string|null, kode_pos: string|null}
     */
    private function resolveWilayah(string $alamat, string $rawKota): array
    {
        $alamat = trim($alamat);
        $upper = strtoupper($alamat);
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

        if (preg_match_all('/\b(\d{5})\b/', $upperNorm, $m)) {
            $kodePos = end($m[1]);
            $result['kode_pos'] = $kodePos;
        }

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
                $result['kabupaten_kota'] = trim($rawKota);
            }
        }

        $district = null;
        if (preg_match('/\bkec(?:\.|amatan)?\s*\.?\s*([a-zA-Z\s\.]+?)(?:,|\s+Kel|\s+Desa|\s+Kota|\s+Kabupaten|\s+Jawa|\s+Banten|\s+DKI|\s+DI\s|\s+Kalimantan|\s+Sulawesi|\s+Sumatera|\s+Bali|\s+Nusa|$)/i', $alamat, $mm)) {
            $explicitKecRaw = trim($mm[1]);
            $explicitKecRaw = (string) preg_replace('/\bSel\.?\b/i', 'Selatan', $explicitKecRaw);
            $explicitKecRaw = (string) preg_replace('/\bUt\.?\b/i', 'Utara', $explicitKecRaw);
            $explicitKecRaw = trim($explicitKecRaw, ' .,');
            if ($explicitKecRaw !== '') {
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
                    $cityFromDist = City::where('code', $cand->city_code)->first();
                    if ($cityFromDist !== null) {
                        $city = $cityFromDist;
                        $result['kabupaten_kota'] = $cityFromDist->name;
                        if ($result['provinsi'] === null) {
                            $prov = Province::where('code', $cityFromDist->province_code)->first();
                            $result['provinsi'] = $prov?->name;
                            $province = $prov;
                        } else {
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
            $districtCandidates = $districtCandidates->sortByDesc(fn ($d) => strlen($d->name));
            foreach ($districtCandidates as $d) {
                $dName = strtoupper($d->name);
                if ($dName !== '' && str_contains($upperNorm, $dName)) {
                    if (preg_match('/\b'.preg_quote($dName, '/').'\b/', $upperNorm) === 1) {
                        $district = $d;
                        $result['kecamatan'] = $d->name;
                        if ($result['kode_pos'] === null && ! empty($d->kode_pos)) {
                            $result['kode_pos'] = $d->kode_pos;
                        }
                        break;
                    }
                }
            }
        }

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
            $parts = array_map('trim', explode(',', $alamat));
            $partsUpper = array_map('strtoupper', $parts);
            foreach (array_slice($partsUpper, 0, 3) as $partUpper) {
                $partUpper = trim($partUpper);
                if ($partUpper === '' || strlen($partUpper) < 3) {
                    continue;
                }
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

        if ($result['kecamatan'] === null && $district === null) {
            $parts = array_map('trim', explode(',', $alamat));
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

        if ($result['kabupaten_kota'] === null && $rawKota !== '' && ! $this->isPhone($rawKota)) {
            $result['kabupaten_kota'] = $this->normalizeCityName($rawKota);
        }

        return $result;
    }
}

<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Seed tabel wilayah (provinces, cities, districts) dari data Kemendagri.
 *
 * Sumber: https://github.com/emsifa/api-wilayah-indonesia (data CSV di database/data).
 * Catatan: dataset berisi 34 provinsi (Kemendagri 2015) — kode pos tidak tersedia
 * di dataset ini, diinput manual oleh admin.
 */
class WilayahSeeder extends Seeder
{
    public function run(): void
    {
        // Idempoten: data wilayah statis referensi, aman di-truncate (tanpa FK dari tabel lain).
        DB::table('villages')->delete();
        DB::table('districts')->delete();
        DB::table('cities')->delete();
        DB::table('provinces')->delete();

        $this->seedProvinces();
        $this->seedCities();
        $this->seedDistricts();
        $this->seedKodePos();
        $this->seedVillages();
        $this->seedVillageKodePos();
    }

    /**
     * Seed kelurahan/desa (80rb+ baris, kode 10 digit) — dipakai untuk
     * perhitungan ongkir api.co.id (origin/destination village code).
     */
    private function seedVillages(): void
    {
        $rows = $this->readCsv(database_path('data/villages.csv'));

        // Beberapa baris duplikat di dataset — sisakan yang pertama.
        $seen = [];
        $rows = array_values(array_filter($rows, function (array $row) use (&$seen): bool {
            $code = trim($row[0]);

            if (isset($seen[$code])) {
                return false;
            }

            $seen[$code] = true;

            return true;
        }));

        $chunks = array_chunk($rows, 2000);

        foreach ($chunks as $chunk) {
            DB::table('villages')->insert(
                array_map(fn (array $row) => [
                    'id' => (string) Str::uuid7(),
                    'code' => trim($row[0]),
                    'district_code' => trim($row[1]),
                    'name' => trim($row[2]),
                ], $chunk),
            );
        }
    }

    private function seedProvinces(): void
    {
        $rows = $this->readCsv(database_path('data/provinces.csv'));

        DB::table('provinces')->insert(
            array_map(fn (array $row) => [
                'id' => (string) Str::uuid7(),
                'code' => trim($row[0]),
                'name' => trim($row[1]),
            ], $rows),
        );
    }

    private function seedCities(): void
    {
        $rows = $this->readCsv(database_path('data/regencies.csv'));

        DB::table('cities')->insert(
            array_map(fn (array $row) => [
                'id' => (string) Str::uuid7(),
                'code' => trim($row[0]),
                'province_code' => trim($row[1]),
                'name' => trim($row[2]),
            ], $rows),
        );
    }

    private function seedDistricts(): void
    {
        $rows = $this->readCsv(database_path('data/districts.csv'));

        foreach (array_chunk($rows, 1000) as $chunk) {
            DB::table('districts')->insert(
                array_map(fn (array $row) => [
                    'id' => (string) Str::uuid7(),
                    'code' => trim($row[0]),
                    'city_code' => trim($row[1]),
                    'name' => trim($row[2]),
                ], $chunk),
            );
        }
    }

    /**
     * Isi kode_pos per kecamatan dari CSV mapping (sumber: sooluh/kodepos,
     * diagregasi 1 kode pos per kecamatan — kode pos bisa berbeda per kelurahan).
     */
    private function seedKodePos(): void
    {
        $path = database_path('data/districts_kodepos.csv');

        if (! file_exists($path)) {
            return;
        }

        $map = [];

        foreach ($this->readCsv($path) as $row) {
            $map[$row[0]] = trim($row[1]);
        }

        $districts = DB::table('districts')
            ->join('cities', 'cities.code', '=', 'districts.city_code')
            ->join('provinces', 'provinces.code', '=', 'cities.province_code')
            ->select('districts.code', 'provinces.name as province', 'cities.name as city', 'districts.name as district')
            ->get();

        $updates = [];

        foreach ($districts as $district) {
            $key = $this->normalize($district->province).'|'.$this->normalize($district->city).'|'.$this->normalize($district->district);

            if (isset($map[$key])) {
                $updates[] = ['code' => $district->code, 'kode_pos' => $map[$key]];
            }
        }

        foreach (array_chunk($updates, 500) as $chunk) {
            foreach ($chunk as $row) {
                DB::table('districts')
                    ->where('code', $row['code'])
                    ->update(['kode_pos' => $row['kode_pos']]);
            }
        }
    }

    /**
     * Isi kode_pos per kelurahan/desa dari CSV mapping (sumber: sooluh/kodepos).
     * Kode pos resmi Indonesia per desa/kelurahan, bukan per kecamatan.
     * Kelurahan yang tidak cocok tetap memakai kode pos kecamatan (fallback).
     */
    private function seedVillageKodePos(): void
    {
        $path = database_path('data/villages_kodepos.csv');

        if (! file_exists($path)) {
            return;
        }

        $map = [];

        foreach ($this->readCsv($path) as $row) {
            $map[$row[0]] = trim($row[1]);
        }

        $villages = DB::table('villages')
            ->join('districts', 'districts.code', '=', 'villages.district_code')
            ->join('cities', 'cities.code', '=', 'districts.city_code')
            ->join('provinces', 'provinces.code', '=', 'cities.province_code')
            ->select('villages.code', 'villages.name', 'districts.name as district', 'cities.name as city', 'provinces.name as province')
            ->get();

        $updates = [];

        foreach ($villages as $village) {
            $key = $this->normalize($village->province).'|'.$this->normalize($village->city).'|'.$this->normalize($village->district).'|'.$this->normalize($village->name);

            if (isset($map[$key])) {
                $updates[] = ['code' => $village->code, 'kode_pos' => $map[$key]];
            }
        }

        foreach (array_chunk($updates, 500) as $chunk) {
            foreach ($chunk as $row) {
                DB::table('villages')
                    ->where('code', $row['code'])
                    ->update(['kode_pos' => $row['kode_pos']]);
            }
        }
    }

    private function normalize(string $name): string
    {
        $name = strtolower(trim($name));
        $name = preg_replace('/^(kabupaten|kota)\s+/', '', $name);

        return preg_replace('/[^a-z0-9]/', '', $name) ?? '';
    }

    /**
     * @return array<int, array<int, string>>
     */
    private function readCsv(string $path): array
    {
        $rows = [];

        foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
            $rows[] = str_getcsv($line);
        }

        return $rows;
    }
}

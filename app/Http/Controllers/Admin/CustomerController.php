<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ActivityAction;
use App\Enums\CustomerTier;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CustomerImportRequest;
use App\Http\Requests\Admin\CustomerStoreRequest;
use App\Http\Requests\Admin\CustomerUpdateRequest;
use App\Models\City;
use App\Models\District;
use App\Models\Order;
use App\Models\Province;
use App\Models\User;
use App\Models\Village;
use App\Support\ActivityLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;

class CustomerController extends Controller
{
    /**
     * List customer + pencarian (CUST-01).
     */
    public function index(Request $request): Response
    {
        $customers = User::query()
            ->where('is_admin', false)
            ->withCount('orders')
            ->when($request->filled('search'), function ($query) use ($request): void {
                $search = $request->string('search')->toString();

                $query->where(function ($query) use ($search): void {
                    $query->whereLike('name', "%{$search}%")
                        ->orWhereLike('email', "%{$search}%")
                        ->orWhereLike('whatsapp_number', "%{$search}%");
                });
            })
            ->orderByDesc('created_at')
            ->paginate(10)
            ->withQueryString();

        return Inertia::render('admin/customers/Index', [
            'customers' => $customers,
            'filters' => $request->only(['search']),
        ]);
    }

    /**
     * Form buat pelanggan baru.
     */
    public function create(): Response
    {
        return Inertia::render('admin/customers/Form', [
            'customer' => null,
            'tierOptions' => CustomerTier::options(),
        ]);
    }

    /**
     * Simpan pelanggan baru.
     */
    public function store(CustomerStoreRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $data['is_admin'] = false;
        $data['email_verified_at'] = now();

        $user = User::create($data);

        Inertia::flash('toast', ['type' => 'success', 'message' => "Pelanggan {$user->name} berhasil dibuat."]);

        return to_route('admin.customers.index');
    }

    /**
     * Form edit customer (CUST-02).
     */
    public function edit(User $user): Response
    {
        abort_if($user->is_admin, 403, 'Akun admin tidak dapat diedit dari halaman customer.');

        return Inertia::render('admin/customers/Form', [
            'customer' => $user,
            'tierOptions' => CustomerTier::options(),
        ]);
    }

    /**
     * Update customer — whitelist field, `is_admin` tidak pernah bisa diubah (CUST-04).
     */
    public function update(CustomerUpdateRequest $request, User $user): RedirectResponse
    {
        abort_if($user->is_admin, 403, 'Akun admin tidak dapat diedit dari halaman customer.');

        $data = $request->validated();

        $user->update($data);

        Inertia::flash('toast', ['type' => 'success', 'message' => "Data pelanggan {$user->name} berhasil diperbarui."]);

        return to_route('admin.customers.index');
    }

    /**
     * Import pelanggan dari file CSV (IMPORT-01).
     *
     * Format kolom: PENERIMA, TUJUAN (provinsi), Kota/Kabupaten, Kecamatan, Kelurahan.
     * - Email dikosongkan (CSV tidak memuat email) — bisa diisi manual via form edit.
     * - Nama wilayah dicocokkan case-insensitive ke tabel wilayah; nama baku (kapital) disimpan.
     * - Duplikat nama: alamat identik dilewati, alamat berbeda diperbarui.
     */
    public function importCsv(CustomerImportRequest $request): RedirectResponse
    {
        $result = DB::transaction(function () use ($request): array {
            $rows = $this->parseCsvRows($request->file('file')->getRealPath());

            return User::withoutEvents(fn (): array => $this->processRows($rows));
        });

        ActivityLogger::log(
            ActivityAction::CustomerImport,
            "Import CSV pelanggan: {$result['created']} dibuat, {$result['updated']} diperbarui, {$result['skipped']} dilewati.",
            null,
            ['summary' => $result],
        );

        $message = "Import CSV selesai: {$result['created']} pelanggan baru, {$result['updated']} diperbarui, {$result['skipped']} dilewati.";

        if ($result['errors'] !== []) {
            $message .= ' '.count($result['errors']).' baris gagal ('.implode('; ', array_slice($result['errors'], 0, 3)).').';
        }

        if ($result['missing_postal'] > 0) {
            $message .= " {$result['missing_postal']} pelanggan tanpa kode pos (isi manual via edit).";
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => $message]);

        return back();
    }

    /**
     * Parse file CSV: strip BOM, lewati header, buang baris kosong.
     *
     * @return array<int, array{line: int, cells: array<int, string>}>
     */
    private function parseCsvRows(string $path): array
    {
        $handle = fopen($path, 'r');

        if ($handle === false) {
            throw new RuntimeException('Tidak dapat membaca file CSV.');
        }

        $rows = [];
        $lineNumber = 0;
        $isFirstRow = true;

        while (($cells = fgetcsv($handle, null, ',', '"', '\\')) !== false) {
            $lineNumber++;
            $cells = array_map(fn ($cell): string => trim((string) $cell), $cells);

            if ($isFirstRow) {
                $isFirstRow = false;
                $cells[0] = (string) preg_replace('/^\xEF\xBB\xBF/', '', $cells[0] ?? '');

                if ($this->isHeaderRow($cells)) {
                    continue;
                }
            }

            if (count(array_filter($cells, fn ($cell): bool => $cell !== '')) === 0) {
                continue;
            }

            $rows[] = ['line' => $lineNumber, 'cells' => $cells];
        }

        fclose($handle);

        return $rows;
    }

    /**
     * Deteksi baris header CSV pelanggan.
     *
     * @param  array<int, string>  $cells
     */
    private function isHeaderRow(array $cells): bool
    {
        $first = strtolower($cells[0] ?? '');
        $second = strtolower($cells[1] ?? '');

        return in_array($first, ['penerima', 'nama', 'name'], true) || $second === 'tujuan';
    }

    /**
     * Normalisasi nama kota/kabupaten dari CSV ke bentuk baku DB wilayah:
     * "Kab. Kediri" → "KABUPATEN KEDIRI", "Kota Depok" → "KOTA DEPOK".
     */
    private function normalizeCityName(string $name): string
    {
        $name = strtoupper(trim($name));
        $name = (string) preg_replace('/^KAB\.?\s+/', 'KABUPATEN ', $name);

        return (string) preg_replace('/^KOTA\s+/', 'KOTA ', $name);
    }

    /**
     * Proses semua baris: resolve wilayah, buat/update/skip pelanggan.
     *
     * @param  array<int, array{line: int, cells: array<int, string>}>  $rows
     * @return array{created: int, updated: int, skipped: int, missing_postal: int, errors: list<string>}
     */
    private function processRows(array $rows): array
    {
        $provinces = Province::all()->keyBy(fn (Province $p): string => strtoupper(trim($p->name)));
        $citiesByProvince = City::all()->groupBy('province_code');
        $districtsByCity = District::all()->groupBy('city_code');

        // Pass 1: resolve provinsi → kota → kecamatan, kumpulkan kode kecamatan untuk lookup desa.
        $resolved = [];
        $districtCodes = [];

        foreach ($rows as $index => $row) {
            $cells = $row['cells'];
            $province = $provinces->get(strtoupper(trim($cells[1] ?? '')));
            $city = $province !== null
                ? $citiesByProvince->get($province->code)?->first(
                    fn (City $c): bool => strtoupper(trim($c->name)) === $this->normalizeCityName($cells[2] ?? ''),
                )
                : null;
            $district = $city !== null
                ? $districtsByCity->get($city->code)?->first(
                    fn (District $d): bool => strtoupper(trim($d->name)) === strtoupper(trim($cells[3] ?? '')),
                )
                : null;

            $resolved[$index] = [
                'province' => $province,
                'city' => $city,
                'district' => $district,
            ];

            if ($district !== null) {
                $districtCodes[] = $district->code;
            }
        }

        $villagesByDistrict = Village::query()
            ->whereIn('district_code', array_values(array_unique($districtCodes)))
            ->get()
            ->groupBy('district_code');

        // Pass 2: proses tiap baris.
        // Password acak di-hash SEKALI di luar loop — 580× bcrypt hash melebihi
        // time limit (tiap hash ~200ms). Pelanggan import tak punya email,
        // jadi tidak bisa login; password bisa di-reset bila email diisi nanti.
        $importedPassword = Hash::make(Str::random(16));
        $created = 0;
        $updated = 0;
        $skipped = 0;
        $missingPostal = 0;
        $errors = [];
        $seen = [];

        foreach ($rows as $index => $row) {
            $line = $row['line'];
            $cells = $row['cells'];
            $name = trim($cells[0] ?? '');

            if ($name === '') {
                $errors[] = "Baris {$line}: nama penerima kosong";

                continue;
            }

            $province = $resolved[$index]['province'];
            $city = $resolved[$index]['city'];
            $district = $resolved[$index]['district'];

            // Nama baku dari tabel wilayah; fallback ke nilai CSV yang dinormalisasi.
            $provinsi = $province?->name ?? strtoupper(trim($cells[1] ?? ''));
            $kabupatenKota = $city?->name ?? $this->normalizeCityName($cells[2] ?? '');
            $kecamatan = $district?->name ?? strtoupper(trim($cells[3] ?? ''));
            $kelurahan = strtoupper(trim($cells[4] ?? ''));

            $village = $district !== null
                ? $villagesByDistrict->get($district->code)?->first(
                    fn (Village $v): bool => strtoupper(trim($v->name)) === $kelurahan,
                )
                : null;

            $kodePos = $village?->kode_pos ?? $district?->kode_pos;

            if ($kodePos === null) {
                $missingPostal++;
            }

            $existing = $seen[$name] ??= User::query()
                ->where('name', $name)
                ->where('is_admin', false)
                ->whereNull('deleted_at')
                ->first();

            if ($existing !== null) {
                $sameAddress = $existing->provinsi === $provinsi
                    && $existing->kabupaten_kota === $kabupatenKota
                    && $existing->kecamatan === $kecamatan
                    && $existing->kelurahan === $kelurahan;

                if ($sameAddress) {
                    $skipped++;
                } else {
                    $existing->forceFill([
                        'provinsi' => $provinsi,
                        'kabupaten_kota' => $kabupatenKota,
                        'kecamatan' => $kecamatan,
                        'kelurahan' => $kelurahan,
                        'village_code' => $village?->code,
                        'kode_pos' => $kodePos,
                    ])->save();

                    $updated++;
                }

                continue;
            }

            $user = User::create([
                'name' => $name,
                'email' => null,
                'password' => $importedPassword,
                'is_admin' => false,
                'is_active' => true,
                'status_pelanggan' => CustomerTier::Reguler,
                'provinsi' => $provinsi,
                'kabupaten_kota' => $kabupatenKota,
                'kecamatan' => $kecamatan,
                'kelurahan' => $kelurahan,
                'village_code' => $village?->code,
                'kode_pos' => $kodePos,
            ]);

            $seen[$name] = $user;

            $created++;
        }

        return [
            'created' => $created,
            'updated' => $updated,
            'skipped' => $skipped,
            'missing_postal' => $missingPostal,
            'errors' => $errors,
        ];
    }

    /**
     * Ringkasan transaksi customer (CUST-03).
     */
    public function summary(User $user): JsonResponse
    {
        abort_if($user->is_admin, 403);

        $totals = Order::where('user_id', $user->id)
            ->where('status', 'selesai')
            ->selectRaw('COUNT(*) as order_count, COALESCE(SUM(total), 0) as total_spent')
            ->first();

        return response()->json([
            'order_count' => (int) $totals->getAttribute('order_count'),
            'total_spent' => (int) $totals->getAttribute('total_spent'),
        ]);
    }
}

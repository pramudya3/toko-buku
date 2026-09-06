<?php

namespace Database\Seeders;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\Book;
use App\Models\Category;
use App\Models\City;
use App\Models\ConsignmentDelivery;
use App\Models\ConsignmentDeliveryItem;
use App\Models\ConsignmentSale;
use App\Models\ConsignmentSaleItem;
use App\Models\District;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\PaymentMethod;
use App\Models\Province;
use App\Models\Receivable;
use App\Models\TierDiscount;
use App\Models\User;
use App\Models\Village;
use App\Models\Warehouse;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Penjualan Agustus 2026 dari penjualan-agustus.csv (full 01–31).
 *
 * CSV kolom: Tanggal,Nm. Pelanggan,Dropshiped,No. Tlp,Alamat,Nama Buku,Qty Jual,Harga,Diskon,Sub - Total,Ongkir,Jns Bayar
 *
 * Routing 4 mode (sesuai best practice konsinyasi):
 * - Order biasa: non-konsinyasi, dropship kosong
 * - Order dropship: non-konsinyasi, dropship terisi -> orders.is_dropship + dropshippers
 * - Konsinyasi biasa: Jns Bayar mengandung "konsinyasi", dropship kosong -> consignment_deliveries + consignment_sales + receivables
 * - Konsinyasi dropship: konsinyasi + dropship terisi (Pak Nuris -> Namira) -> consignment dengan customer_id = Dropshiped, notes "via Pak Nuris"
 *
 * Harga historis: price_original = Harga CSV, price_final = Sub / Qty (Sub sudah neto diskon).
 * Konsinyasi tidak masuk orders, penjualan diakui saat lapor laku (consignment_sales), bayar hanya lunasi piutang.
 *
 * Idempotent via firstOrCreate (no_order ORD-YYYYMMDD-XXXX, consignment notes Import CSV).
 * Bersihkan data lama FK26-08-XXXX (dump 01–14) agar tidak duplikat.
 */
class PenerbitSalesSeeder extends Seeder
{
    public function run(): void
    {
        // Bersihkan order lama dari dump (FK26-08) agar CSV full menjadi source of truth.
        // Aman: hanya prefix FK, tidak menghapus ORD demo.
        Order::where('no_order', 'like', 'FK26-08-%')->delete();

        // Payment methods custom
        foreach ([
            ['bsi', 'Transfer BSI'],
            ['qris', 'QRIS'],
            ['shopee', 'Shopee'],
            ['konsinyasi', 'Konsinyasi'],
            ['hutang', 'Hutang'],
        ] as $i => [$code, $name]) {
            PaymentMethod::firstOrCreate(['code' => $code], [
                'name' => $name,
                'is_active' => true,
                'sort_order' => 100 + $i,
            ]);
        }

        $groups = $this->readCsvGroups();
        // Sort by date then pelanggan for deterministic no_order
        $groups = collect($groups)->sortBy(fn ($g) => $g['tgl'].'|'.mb_strtolower($g['pelanggan']).'|'.mb_strtolower($g['dropship']))->values()->all();

        $counters = [];
        foreach ($groups as $group) {
            $isKonsinyasi = $this->isKonsinyasi($group['jnsBayar'], $group['items']);
            $isDropship = trim((string) $group['dropship']) !== '';

            if ($isKonsinyasi) {
                $this->seedConsignmentGroup($group, $isDropship);
            } else {
                $counters[$group['tgl']] = ($counters[$group['tgl']] ?? 0) + 1;
                $noOrder = 'ORD-'.str_replace('-', '', $group['tgl']).'-'.str_pad((string) $counters[$group['tgl']], 4, '0', STR_PAD_LEFT);
                $this->seedOrderGroup($group, $isDropship, $noOrder);
            }
        }
    }

    /**
     * @return array<int, array{tgl:string, pelanggan:string, dropship:string, noTlp:string, alamat:string, jnsBayar:string, items:array<int, array{namaBuku:string, qty:int, harga:int, sub:int}>, ongkirSum:int}>
     */
    private function readCsvGroups(): array
    {
        $path = base_path('penjualan-agustus.csv');
        if (! file_exists($path)) {
            throw new \RuntimeException("File tidak ditemukan: {$path}");
        }
        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines === false || count($lines) < 2) {
            return [];
        }
        array_shift($lines); // header
        $groups = [];
        foreach ($lines as $line) {
            // str_getcsv handle "40,000" quoted
            $cols = str_getcsv($line, ',', '"', '"');
            // Normalize to 12 cols
            $cols = array_pad($cols, 12, '');
            $tglStr = trim((string) ($cols[0] ?? ''));
            if ($tglStr === '') {
                continue;
            }
            try {
                $tgl = Carbon::createFromFormat('d/m/Y', $tglStr)->format('Y-m-d');
            } catch (\Throwable $e) {
                continue;
            }
            $pelanggan = trim((string) ($cols[1] ?? ''));
            $dropship = trim((string) ($cols[2] ?? ''));
            $noTlp = trim((string) ($cols[3] ?? ''));
            $alamat = trim((string) ($cols[4] ?? ''));
            $namaBuku = trim((string) ($cols[5] ?? ''));
            if ($namaBuku === '') {
                continue;
            }
            $qty = (int) preg_replace('/\D/', '', (string) ($cols[6] ?? '0'));
            if ($qty <= 0) {
                $qty = 1;
            }
            $harga = $this->parseNumber((string) ($cols[7] ?? ''));
            $sub = $this->parseNumber((string) ($cols[9] ?? ''));
            $ongkir = $this->parseNumber((string) ($cols[10] ?? ''));
            $jnsBayar = trim((string) ($cols[11] ?? ''));

            $key = $tgl.'|'.mb_strtolower($pelanggan).'|'.mb_strtolower($dropship).'|'.mb_strtolower($jnsBayar);
            if (! isset($groups[$key])) {
                $groups[$key] = [
                    'tgl' => $tgl,
                    'pelanggan' => $pelanggan,
                    'dropship' => $dropship,
                    'noTlp' => $noTlp,
                    'alamat' => $alamat,
                    'jnsBayar' => $jnsBayar,
                    'items' => [],
                    'ongkirSum' => 0,
                ];
            }
            if ($groups[$key]['noTlp'] === '' && $noTlp !== '') {
                $groups[$key]['noTlp'] = $noTlp;
            }
            if ($groups[$key]['alamat'] === '' && $alamat !== '') {
                $groups[$key]['alamat'] = $alamat;
            }
            $groups[$key]['items'][] = [
                'namaBuku' => $namaBuku,
                'qty' => $qty,
                'harga' => $harga,
                'sub' => $sub,
            ];
            $groups[$key]['ongkirSum'] += $ongkir;
        }

        return array_values($groups);
    }

    private function parseNumber(string $raw): int
    {
        $raw = trim($raw);
        if ($raw === '' || $raw === '-') {
            return 0;
        }
        // Remove commas, dots as thousand separator, but keep digits
        // "40,000" -> 40000, "38,445" -> 38445, "62,262" -> 62262
        $clean = str_replace([',', '.', ' ', '"', "'"], '', $raw);
        // If raw contains letters, return 0
        if (! preg_match('/^-?\d+$/', $clean)) {
            // Try extract digits
            $digits = preg_replace('/\D/', '', $raw);

            return $digits !== '' ? (int) $digits : 0;
        }

        return (int) $clean;
    }

    private function isKonsinyasi(string $jnsBayar, array $items): bool
    {
        if (stripos($jnsBayar, 'konsinyasi') !== false) {
            return true;
        }
        // Sample kelebihan kirim: Harga empty, Sub 0, Jns "konsinyasi karena kelebihan kirim" already handled
        // But empty Jns with zero sub also treat as konsinyasi sample
        if (trim($jnsBayar) === '' && collect($items)->sum('sub') === 0 && collect($items)->sum('harga') === 0) {
            return true;
        }

        return false;
    }

    private function mapPaymentCode(string $jnsBayar): string
    {
        $lower = mb_strtolower(trim($jnsBayar));
        if (str_contains($lower, 'shopee')) {
            return 'shopee';
        }
        if (str_contains($lower, 'qris')) {
            return 'qris';
        }
        if (str_contains($lower, 'konsinyasi')) {
            return 'konsinyasi';
        }
        if (str_contains($lower, 'hutang')) {
            return 'hutang';
        }
        // BSI / Transfer BSI / Transfer -> bsi
        if (str_contains($lower, 'bsi') || $lower === 'transfer' || str_contains($lower, 'transfer')) {
            return 'bsi';
        }

        return 'bsi'; // default
    }

    private function mapSumber(string $jnsBayar): string
    {
        return stripos($jnsBayar, 'shopee') !== false ? 'shopee' : 'toko';
    }

    private function seedOrderGroup(array $group, bool $isDropship, string $noOrder): void
    {
        $pelangganName = $group['pelanggan'];
        if ($pelangganName === '') {
            $pelangganName = 'Pelanggan '.$group['tgl'];
        }
        // Pelanggan user: for dropship, phone/address belong to dropship, not pelanggan
        $pelangganPhone = $isDropship ? null : ($group['noTlp'] !== '' ? $group['noTlp'] : null);
        $pelangganAlamat = $isDropship ? null : ($group['alamat'] !== '' ? $group['alamat'] : null);

        $pelangganUser = $this->ensureUser($pelangganName, $pelangganPhone, $pelangganAlamat, $this->isKonsinyasi($group['jnsBayar'], $group['items']) ? 'bazaf' : 'reguler');

        $paymentCode = $this->mapPaymentCode($group['jnsBayar']);
        $sumber = $this->mapSumber($group['jnsBayar']);
        $totalItems = collect($group['items'])->sum('sub');
        $total = $totalItems + $group['ongkirSum'];

        $isHutang = stripos($group['jnsBayar'], 'hutang') !== false;
        $paymentStatus = $isHutang ? PaymentStatus::Menunggu : PaymentStatus::Lunas;

        $order = Order::firstOrCreate(
            ['no_order' => $noOrder],
            [
                'user_id' => $pelangganUser->id,
                'nama_pembeli' => $pelangganUser->name,
                'no_hp' => $pelangganUser->whatsapp_number,
                'alamat' => $pelangganUser->alamat,
                'provinsi' => $pelangganUser->provinsi,
                'kabupaten_kota' => $pelangganUser->kabupaten_kota,
                'kecamatan' => $pelangganUser->kecamatan,
                'kelurahan' => $pelangganUser->kelurahan,
                'kode_pos' => $pelangganUser->kode_pos,
                'nama_penerima' => $isDropship ? $group['dropship'] : null,
                'metode_bayar' => $paymentCode,
                'sumber_pembelian' => $sumber,
                'total' => $total,
                'shipping_cost' => $group['ongkirSum'],
                'is_dropship' => $isDropship,
                'warehouse_origin' => 'malang',
                'status' => OrderStatus::Selesai,
                'payment_status' => $paymentStatus,
                'ekspedisi' => null,
                'metode_pengambilan' => ($group['ongkirSum'] > 0 || $isDropship || $group['alamat'] !== '') ? 'kirim' : 'ambil',
                'created_at' => $group['tgl'].' 10:00:00',
                'updated_at' => $group['tgl'].' 10:00:00',
            ]
        );

        if ($order->wasRecentlyCreated === false && $order->items()->exists()) {
            // Already seeded, but ensure dropshipper record exists if needed
            if ($isDropship && ! $order->dropshipper()->exists()) {
                $this->ensureDropshipper($order, $pelangganUser, $group);
            }

            return;
        }

        // If order existed but items were deleted (edge), continue to create items
        // Remove existing items if any stale (should not happen for ORD)
        // Create items
        foreach ($group['items'] as $item) {
            $book = $this->ensureBook($item['namaBuku'], $item['harga']);
            $priceOriginal = $item['harga'];
            $priceFinal = $item['qty'] > 0 ? intdiv($item['sub'], $item['qty']) : $item['sub'];
            // Guard negative/over: sub could be 0 for sample? keep 0
            if ($priceFinal < 0) {
                $priceFinal = 0;
            }
            // If price_original 0 but sub >0, use priceFinal as original to avoid 0
            if ($priceOriginal === 0 && $priceFinal > 0) {
                $priceOriginal = $priceFinal;
            }
            $tierDiscount = max(0, $priceOriginal - $priceFinal);

            // harga_beli snapshot from book edition if available
            $hargaBeli = null;
            if ($book !== null) {
                $edition = $book->editions()->orderBy('cetakan_ke')->first();
                if ($edition !== null) {
                    $hargaBeli = $edition->harga_beli;
                }
            }

            OrderItem::create([
                'order_id' => $order->id,
                'book_id' => $book?->id,
                'judul_snapshot' => $book?->judul ?? $item['namaBuku'],
                'harga_snapshot' => $priceOriginal,
                'harga_beli_snapshot' => $hargaBeli,
                'qty' => $item['qty'],
                'price_original' => $priceOriginal,
                'promo_discount_amount' => 0,
                'tier_discount_amount' => $tierDiscount,
                'price_final' => $priceFinal,
                'is_preorder' => false,
            ]);
        }

        if ($isDropship) {
            $this->ensureDropshipper($order, $pelangganUser, $group);
        }
    }

    private function ensureDropshipper(Order $order, User $pelangganUser, array $group): void
    {
        if ($order->dropshipper()->exists()) {
            return;
        }
        // For Order dropship, end customer = Dropshiped
        $order->dropshipper()->create([
            'user_id' => $pelangganUser->id,
            'end_customer_name' => $group['dropship'],
            'end_customer_whatsapp' => $group['noTlp'] !== '' ? $group['noTlp'] : null,
            'end_customer_address' => $group['alamat'] !== '' ? $group['alamat'] : null,
        ]);
        // Also ensure order's alamat/nama_penerima reflect dropship if not set
        $updates = [];
        if (empty($order->nama_penerima) && $group['dropship'] !== '') {
            $updates['nama_penerima'] = $group['dropship'];
        }
        if (empty($order->alamat) && $group['alamat'] !== '') {
            $updates['alamat'] = $group['alamat'];
        }
        if (empty($order->no_hp) && $group['noTlp'] !== '') {
            $updates['no_hp'] = $group['noTlp'];
        }
        if ($updates !== []) {
            $order->update($updates);
        }
    }

    private function seedConsignmentGroup(array $group, bool $isDropship): void
    {
        $mitraName = $isDropship ? $group['dropship'] : $group['pelanggan'];
        if ($mitraName === '') {
            $mitraName = $group['pelanggan'] !== '' ? $group['pelanggan'] : 'Mitra Konsinyasi';
        }
        $mitraPhone = $group['noTlp'] !== '' ? $group['noTlp'] : null;
        $mitraAlamat = $group['alamat'] !== '' ? $group['alamat'] : null;

        // Ensure koordinator exists if dropship
        if ($isDropship && $group['pelanggan'] !== '' && mb_strtolower($group['pelanggan']) !== mb_strtolower($mitraName)) {
            $this->ensureUser($group['pelanggan'], null, null, 'bazaf');
        }
        $mitraUser = $this->ensureUser($mitraName, $mitraPhone, $mitraAlamat, 'bazaf');

        $notesBase = "Import CSV Agustus {$group['tgl']} - {$mitraName}";
        if ($isDropship) {
            $notesBase .= " via {$group['pelanggan']}";
        }

        $hasSale = collect($group['items'])->sum('sub') > 0;

        // Delivery: always for konsinyasi (titip) — pakai kode unik biar 1 mitra 1 tanggal dengan item/qty beda tetap 1/1, tidak akumulasi
        // Deterministik per grup CSV (customer + tanggal + notes) — re-run tidak
        // menduplikasi; kode hanya dibuat saat record baru dibuat.
        $delivery = ConsignmentDelivery::where('customer_id', $mitraUser->id)
            ->where('delivery_date', $group['tgl'])
            ->where('notes', $notesBase)
            ->first()
            ?? ConsignmentDelivery::create([
                'kode' => $this->generateConsignmentKode('KSN-D', $group['tgl']),
                'customer_id' => $mitraUser->id,
                'warehouse_id' => Warehouse::where('kode', 'malang')->value('id') ?? Warehouse::query()->value('id'),
                'delivery_date' => $group['tgl'],
                'notes' => $notesBase,
                'user_id' => null,
            ]);

        foreach ($group['items'] as $item) {
            $book = $this->ensureBook($item['namaBuku'], $item['harga']);
            if ($book === null) {
                continue;
            }
            $hargaAsli = $item['harga'];
            // Hitung harga titip tier (sama kayak dialog)
            $tier = $mitraUser->status_pelanggan;
            $pct = 0;
            if ($tier) {
                $pct = (int) (TierDiscount::query()->where('tier', $tier->value ?? $tier)->where('min_qty', '<=', $item['qty'])->orderByDesc('min_qty')->value('discount_percent') ?? 0);
            }
            $hargaTitip = max(0, $hargaAsli - intdiv($hargaAsli * $pct, 100));
            // Jika sub sudah ada (CSV historis), pakai sub/qty sebagai titip biar sync
            if ($item['sub'] > 0 && $item['qty'] > 0) {
                $hargaTitip = intdiv($item['sub'], $item['qty']);
                $hargaAsli = $item['harga'] > 0 ? $item['harga'] : $hargaTitip;
            }
            $exists = ConsignmentDeliveryItem::where('delivery_id', $delivery->id)->where('book_id', $book->id)->exists();
            if (! $exists) {
                ConsignmentDeliveryItem::create([
                    'delivery_id' => $delivery->id,
                    'book_id' => $book->id,
                    'qty' => $item['qty'],
                    'harga_asli' => $hargaAsli,
                    'harga_titip' => $hargaTitip,
                ]);
            } else {
                $existing = ConsignmentDeliveryItem::where('delivery_id', $delivery->id)->where('book_id', $book->id)->first();
                $totalQtyForBook = collect($group['items'])->where('namaBuku', $item['namaBuku'])->sum('qty');
                $totalSubForBook = collect($group['items'])->where('namaBuku', $item['namaBuku'])->sum('sub');
                $avgTitip = $totalQtyForBook > 0 ? intdiv($totalSubForBook, $totalQtyForBook) : $hargaTitip;
                $updates = [];
                if ((int) $existing->qty !== $totalQtyForBook) {
                    $updates['qty'] = $totalQtyForBook;
                }
                if ((int) $existing->harga_asli !== $hargaAsli) {
                    $updates['harga_asli'] = $hargaAsli;
                }
                if ((int) $existing->harga_titip !== $avgTitip) {
                    $updates['harga_titip'] = $avgTitip;
                }
                if ($updates) {
                    $existing->update($updates);
                }
            }
        }

        if (! $hasSale) {
            return; // sample kelebihan kirim: only delivery
        }

        $sale = ConsignmentSale::where('customer_id', $mitraUser->id)
            ->where('sale_date', $group['tgl'])
            ->where('notes', $notesBase)
            ->first()
            ?? ConsignmentSale::create([
                'kode' => $this->generateConsignmentKode('KSN-S', $group['tgl']),
                'customer_id' => $mitraUser->id,
                'sale_date' => $group['tgl'],
                'notes' => $notesBase,
                'user_id' => null,
            ]);

        $totalSale = 0;
        foreach ($group['items'] as $item) {
            if ($item['sub'] === 0) {
                continue; // sample
            }
            $book = $this->ensureBook($item['namaBuku'], $item['harga']);
            if ($book === null) {
                continue;
            }
            $price = $item['qty'] > 0 ? intdiv($item['sub'], $item['qty']) : $item['sub'];
            $totalSale += $item['sub'];
            $exists = ConsignmentSaleItem::where('sale_id', $sale->id)->where('book_id', $book->id)->exists();
            if (! $exists) {
                ConsignmentSaleItem::create([
                    'sale_id' => $sale->id,
                    'book_id' => $book->id,
                    'qty' => $item['qty'],
                    'price' => $price,
                ]);
            } else {
                $existing = ConsignmentSaleItem::where('sale_id', $sale->id)->where('book_id', $book->id)->first();
                $totalQtyForBook = collect($group['items'])->where('namaBuku', $item['namaBuku'])->where('sub', '>', 0)->sum('qty');
                $totalSubForBook = collect($group['items'])->where('namaBuku', $item['namaBuku'])->where('sub', '>', 0)->sum('sub');
                $avgPrice = $totalQtyForBook > 0 ? intdiv($totalSubForBook, $totalQtyForBook) : $price;
                if ((int) $existing->qty !== $totalQtyForBook || (int) $existing->price !== $avgPrice) {
                    $existing->update(['qty' => $totalQtyForBook, 'price' => $avgPrice]);
                }
            }
        }

        if ($totalSale > 0 && $sale->receivable_id === null) {
            $receivable = Receivable::firstOrCreate(
                [
                    'customer_id' => $mitraUser->id,
                    'notes' => "Konsinyasi {$group['tgl']} - {$mitraName}".($isDropship ? " via {$group['pelanggan']}" : ''),
                    'amount' => $totalSale,
                ],
                [
                    'paid_amount' => 0,
                ]
            );
            // Ensure sale linked
            if ($sale->receivable_id === null) {
                $sale->update(['receivable_id' => $receivable->id]);
            }
        }
    }

    private function ensureUser(string $name, ?string $hp, ?string $alamat, string $tier = 'reguler'): User
    {
        $name = trim($name);
        if ($name === '') {
            $name = 'Pelanggan Tanpa Nama';
        }
        $existing = User::withTrashed()
            ->where('name', $name)
            ->whereNull('email')
            ->first();

        $hpNorm = $hp !== null ? trim($hp) : null;
        if ($hpNorm === '') {
            $hpNorm = null;
        }
        $alamatNorm = $alamat !== null ? trim($alamat) : null;

        $resolved = $this->resolveWilayah($alamatNorm ?? '', '');

        if ($existing !== null) {
            $updates = [];
            if (empty($existing->whatsapp_number) && $hpNorm !== null) {
                $updates['whatsapp_number'] = $hpNorm;
            }
            if (empty($existing->alamat) && $resolved['alamat'] !== null) {
                $updates['alamat'] = $resolved['alamat'];
            }
            if (empty($existing->provinsi) && $resolved['provinsi'] !== null) {
                $updates['provinsi'] = $resolved['provinsi'];
            }
            if (empty($existing->kabupaten_kota) && $resolved['kabupaten_kota'] !== null) {
                $updates['kabupaten_kota'] = $resolved['kabupaten_kota'];
            }
            if (empty($existing->kecamatan) && $resolved['kecamatan'] !== null) {
                $updates['kecamatan'] = $resolved['kecamatan'];
            }
            if (empty($existing->kelurahan) && $resolved['kelurahan'] !== null) {
                $updates['kelurahan'] = $resolved['kelurahan'];
            }
            if (empty($existing->village_code) && $resolved['village_code'] !== null) {
                $updates['village_code'] = $resolved['village_code'];
            }
            if (empty($existing->kode_pos) && $resolved['kode_pos'] !== null) {
                $updates['kode_pos'] = $resolved['kode_pos'];
            }
            // Ensure tier bazaf for konsinyasi
            if ($tier === 'bazaf' && $existing->status_pelanggan?->value !== 'bazaf') {
                $updates['status_pelanggan'] = 'bazaf';
            }
            if ($updates !== []) {
                $existing->update($updates);
            }

            return $existing;
        }

        return User::create([
            'name' => $name,
            'email' => null,
            'password' => Hash::make(Str::random(32)),
            'whatsapp_number' => $hpNorm,
            'status_pelanggan' => $tier,
            'alamat' => $resolved['alamat'],
            'provinsi' => $resolved['provinsi'],
            'kabupaten_kota' => $resolved['kabupaten_kota'],
            'kecamatan' => $resolved['kecamatan'],
            'kelurahan' => $resolved['kelurahan'],
            'village_code' => $resolved['village_code'],
            'kode_pos' => $resolved['kode_pos'],
        ]);
    }

    /**
     * @var array<string, string>|null
     */
    private ?array $bookAlias = null;

    private function ensureBook(string $judul, int $harga): ?Book
    {
        $judul = trim($judul);
        if ($this->bookAlias === null) {
            $this->bookAlias = [
                'pemetaan' => 'Pemetaan Surah-Surah Al-Qur\'an',
                'pemetaan surah-surah al-qur\'an' => 'Pemetaan Surah-Surah Al-Qur\'an',
                'bundling ayah ibu dan kisah al fatihah' => 'Bundling Ayah Ibu dan Kisah Al Fatihah',
                'bundling allah menciptakan siang malam' => 'Bundling Allah Menciptakan Siang Malam',
                'bundling allah menciptakan siang malam ' => 'Bundling Allah Menciptakan Siang Malam',
                'bundling sifat baik buruk' => 'Bundling Sifat Baik Buruk',
                'tadabbur juz amma' => 'Tadabbur Juz Amma',
                'tadabbur surah abasa' => 'Tadabbur surah Abasa',
                'tadabbur surah al – fatihah' => 'Tadabbur surah Al – Fatihah',
                'tadabbur surah al - fatihah' => 'Tadabbur surah Al – Fatihah',
                'sirah nabawiyah untuk pemuda' => 'Sirah nabawiyah untuk pemuda',
                'sirah nabawiyah untuk pemuda ' => 'Sirah nabawiyah untuk pemuda',
                'kitabati guru' => 'Kitabati Guru',
                'kitabati umum' => 'Kitabati umum',
                'fami bi syauqin' => 'Fami Bi Syauqin',
                '40 hadits pengagungan terhadap al-quran' => '40 Hadits Pengagungan Terhadap  Al-Quran',
                'aku sahabatmu, al-qur\'an' => 'Aku Sahabatmu, Al-Qur\'an',
                'aku sahabatmu, al-quran' => 'Aku Sahabatmu, Al-Qur\'an',
            ];
        }
        $lower = mb_strtolower(trim($judul));
        $lower = preg_replace('/\s+/', ' ', $lower);
        if (isset($this->bookAlias[$lower])) {
            $judul = $this->bookAlias[$lower];
            $lower = mb_strtolower($judul);
        }

        // Exact match case-insensitive
        $book = Book::withTrashed()->whereRaw('LOWER(TRIM(judul)) = ?', [$lower])->first();
        if ($book !== null) {
            return $book;
        }
        // Fallback like
        $book = Book::withTrashed()->whereRaw('LOWER(judul) LIKE ?', ['%'.$lower.'%'])->first();
        if ($book !== null) {
            return $book;
        }

        // Create placeholder if not found — untuk Bundling jangan buat Bdl baru, pakai Promo Bundle ke depan
        if (str_starts_with($lower, 'bundling')) {
            // Historis Bdl tetap, tapi bundling baru harus via promotions.promo_type='bundle' — log warning
            Log::warning("Bundling '{$judul}' tidak ditemukan sebagai Book Bdl — buat placeholder non-Bdl, migrasi ke Promo Bundle disarankan.");
        }
        // Find default category (first non-Bdl)
        $categoryId = Category::query()->where('kode', '!=', 'Bdl')->value('id') ?? Category::query()->value('id');
        $book = Book::create([
            'kode_sku' => null,
            'judul' => $judul,
            'penulis' => null,
            'harga' => $harga > 0 ? $harga : 0,
            'category_id' => $categoryId,
        ]);

        return $book;
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
            $result['kode_pos'] = end($m[1]);
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
        $cityCandidates = $province !== null ? City::where('province_code', $province->code)->get() : City::all();
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
                        } else {
                            $prov = Province::where('code', $cityFromDist->province_code)->first();
                            if ($prov !== null && strtoupper($prov->name) !== strtoupper((string) $result['provinsi'])) {
                                $result['provinsi'] = $prov->name;
                            }
                        }
                    }
                }
            }
        }
        if ($district === null) {
            $districtCandidates = $city !== null ? District::where('city_code', $city->code)->get() : District::all();
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

    private function generateConsignmentKode(string $prefix, string $date): string
    {
        $ymd = str_replace('-', '', substr($date, 0, 10));
        $like = $prefix.'-'.$ymd.'-%';
        $table = $prefix === 'KSN-D' ? 'consignment_deliveries' : ($prefix === 'KSN-S' ? 'consignment_sales' : 'consignment_returns');
        $last = DB::table($table)->where('kode', 'like', $like)->orderByDesc('kode')->value('kode');
        $seq = $last ? ((int) substr($last, -4)) + 1 : 1;
        // Pastikan tidak bentrok dengan kode yang baru dibuat di loop yang sama
        while (DB::table($table)->where('kode', $prefix.'-'.$ymd.'-'.str_pad((string) $seq, 4, '0', STR_PAD_LEFT))->exists()) {
            $seq++;
        }

        return $prefix.'-'.$ymd.'-'.str_pad((string) $seq, 4, '0', STR_PAD_LEFT);
    }
}

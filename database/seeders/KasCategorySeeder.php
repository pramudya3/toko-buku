<?php

namespace Database\Seeders;

use App\Models\KasCategory;
use App\Models\KasSubCategory;
use Illuminate\Database\Seeder;

class KasCategorySeeder extends Seeder
{
    public function run(): void
    {
        $data = [
            'Pemasaran & Promosi' => [
                'Iklan Digital (Saldo FB Ads, IG Ads, Tiktok Ads, Google Ads)',
                'Endorsement / KOL (Bayar reviewer buku, Bookstagram, utawa Influencer)',
                'Promosi & Hadiah (Ongkir buku PR, cetak merchandise, stiker, totebag)',
                'Event & Pameran (Sewa stand pameran, konsumsi bedah buku, sewa Zoom premium)',
            ],
            'Logistik & Gudang' => [
                'Perlengkapan & Packing (Kardus lembaran, bubble wrap, lakban, plastik polymailer / shrink)',
                'Ongkos Kirim / Kurir (Ongkir retur, biaya kargo, utawa subsidi ongkir pelanggan)',
                'Perawatan Gudang (Beli rak besi, obat rayap, kapur barus, alat kebersihan)',
                'Aset (Pembelian rak, perlengkapan atau perangkat penunjang)',
            ],
            'Kulak Buku' => [
                'Bazaf',
                'Pustaka Nabawiyah',
            ],
            'Cetak Buku' => [
                'GIP',
                'Ladang Kata',
                'Hidayatullah',
            ],
            'Produksi' => [
                'Penulis',
                'Ilustrator',
                'Editor',
                'Layouter',
                'Poster',
            ],
            'Administrasi & Umum' => [
                'Konsumsi & Dapur (Galon banyu, kopi, gula, jajan tamu, jatah makan lembur)',
                'Transportasi & Bensin (Bensin motor/mobil operasional, parkir, e-Toll, ojek online)',
                'Alat Tulis Kantor (ATK) (Kertas HVS, tinta printer, stempel, pulpen, nota, stapler)',
                'Utilitas (Tagihan listrik, banyu, lan paket data internet / WiFi kantor)',
                'Langganan Software (Canva Pro, Google Workspace, domain/hosting web, Adobe)',
            ],
            'Mutasi Internal' => [
                'Kas Kecil',
            ],
            'Karyawan & Tim' => [
                'Gaji Operasional (Gaji bulanan Admin, CS, Tim Gudang)',
                'Bonus & Lemburan (Uang lembur packing, THR, utawa bonus target penjualan)',
                'Sewa Rumah',
            ],
            'Lain-lain' => [
                'Legalitas & Perizinan (Biaya urus ISBN, barcode, utawa daftar HAKI merek)',
                'Biaya Admin & Pajak Bank (Potongan transfer beda bank, potongan QRIS / Midtrans)',
                'Survey & Studi (Biaya transportasi dan akomodasi selama survey atau studi banding)',
                'Refund (Pengembalian ke customer)',
            ],
        ];

        $sort = 1;
        foreach ($data as $kategori => $subs) {
            $cat = KasCategory::firstOrCreate(['nama' => $kategori], ['sort_order' => $sort, 'is_active' => true]);
            $subSort = 1;
            foreach ($subs as $subNama) {
                // Ambil nama pendek sebelum " (" jika ada deskripsi
                $clean = trim(explode('(', $subNama)[0]);
                if ($clean === '') {
                    $clean = $subNama;
                }
                KasSubCategory::firstOrCreate(
                    ['cash_flow_category_id' => $cat->id, 'nama' => $clean],
                    ['description' => $subNama, 'sort_order' => $subSort, 'is_active' => true]
                );
                $subSort++;
            }
            $sort++;
        }
    }
}

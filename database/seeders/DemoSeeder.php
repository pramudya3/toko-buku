<?php

namespace Database\Seeders;

use App\Enums\CustomerTier;
use App\Enums\FlowType;
use App\Enums\MovementType;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PromotionType;
use App\Enums\Warehouse;
use App\Models\Book;
use App\Models\CashFlow;
use App\Models\Category;
use App\Models\Dropshipper;
use App\Models\InventoryStock;
use App\Services\InventoryService;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Promotion;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Data demo: 1 admin + customer tiap tier, kategori, buku + stok gudang,
 * promo (3 tipe), order sample semua status, cash flow & mutasi (F0.7).
 */
class DemoSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $admin = User::create([
            'name' => 'Admin Toko',
            'email' => 'admin@tokobuku.test',
            'password' => Hash::make('password'),
            'is_admin' => true,
        ]);

        $customers = collect([
            ['name' => 'Budi Santoso', 'email' => 'budi@example.com', 'tier' => CustomerTier::Reguler],
            ['name' => 'Siti Aminah', 'email' => 'siti@example.com', 'tier' => CustomerTier::Bazaf],
            ['name' => 'Pak Guru Joko', 'email' => 'joko@example.com', 'tier' => CustomerTier::Guru],
            ['name' => 'Toko Buku Cerdas', 'email' => 'reseller@example.com', 'tier' => CustomerTier::Reseller],
        ])->map(fn (array $data) => User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make('password'),
            'status_pelanggan' => $data['tier'],
            'whatsapp_number' => fake()->numerify('08##########'),
            'provinsi' => 'Jawa Timur',
            'kabupaten' => 'Malang',
            'kecamatan' => 'Lowokwaru',
            'kode_pos' => '65141',
            'alamat' => fake()->streetAddress(),
        ]));

        $categories = collect([
            ['nama' => 'Fiksi', 'slug' => 'fiksi'],
            ['nama' => 'Non-Fiksi', 'slug' => 'non-fiksi'],
            ['nama' => 'Anak & Remaja', 'slug' => 'anak-remaja'],
            ['nama' => 'Religi', 'slug' => 'religi'],
            ['nama' => 'Pendidikan', 'slug' => 'pendidikan'],
        ])->map(fn (array $data) => Category::create($data));

        $bookData = [
            ['judul' => 'Laskar Pelangi', 'penulis' => 'Andrea Hirata', 'harga' => 85000, 'isbn' => '9789793062792', 'kategori' => 0, 'stok' => [12, 5, 1]],
            ['judul' => 'Bumi Manusia', 'penulis' => 'Pramoedya Ananta Toer', 'harga' => 95000, 'isbn' => '9789799731234', 'kategori' => 0, 'stok' => [8, 0, 0]],
            ['judul' => 'Filosofi Teras', 'penulis' => 'Henry Manampiring', 'harga' => 98000, 'isbn' => '9786020643091', 'kategori' => 1, 'stok' => [14, 4, 1]],
            ['judul' => 'Atomic Habits', 'penulis' => 'James Clear', 'harga' => 102000, 'isbn' => '9786020633180', 'kategori' => 1, 'stok' => [20, 10, 2]],
            ['judul' => 'Negeri 5 Menara', 'penulis' => 'Ahmad Fuadi', 'harga' => 75000, 'isbn' => '9789793062614', 'kategori' => 0, 'stok' => [2, 0, 0]],
            ['judul' => 'Si Anak Kuat', 'penulis' => 'Tere Liye', 'harga' => 89000, 'isbn' => '9786020641950', 'kategori' => 2, 'stok' => [15, 6, 0]],
            ['judul' => 'Tafsir Al-Mishbah', 'penulis' => 'M. Quraish Shihab', 'harga' => 250000, 'isbn' => '9789794336980', 'kategori' => 3, 'stok' => [5, 3, 0]],
            ['judul' => 'Matematika SMA Kelas 10', 'penulis' => 'Kemendikbud', 'harga' => 65000, 'isbn' => '9786022445878', 'kategori' => 4, 'stok' => [0, 25, 0]],
            ['judul' => 'Psychology of Money', 'penulis' => 'Morgan Housel', 'harga' => 98000, 'isbn' => '9786020633181', 'kategori' => 1, 'stok' => [9, 9, 0]],
            ['judul' => 'Preorder: Novel Terbaru 2026', 'penulis' => 'Penulis Misterius', 'harga' => 120000, 'isbn' => null, 'kategori' => 0, 'stok' => [0, 0, 0]],
        ];

        $books = collect($bookData)->map(function (array $data, int $index) use ($categories): Book {
            $book = Book::create([
                'judul' => $data['judul'],
                'penulis' => $data['penulis'],
                'penerbit' => fake()->randomElement(['Gramedia Pustaka Utama', 'Bentang Pustaka', 'Republika', 'Gagas Media']),
                'tahun' => fake()->numberBetween(2005, now()->year),
                'isbn' => $data['isbn'],
                'sinopsis' => fake()->paragraph(3),
                'harga' => $data['harga'],
                'stok' => $data['stok'][0] + $data['stok'][1],
                'category_id' => $categories[$data['kategori']]->id,
                'aktif' => true,
                'is_preorder' => str_starts_with($data['judul'], 'Preorder'),
                'po_label' => str_starts_with($data['judul'], 'Preorder') ? 'PO - '.now()->addMonths(2)->format('M Y') : null,
                'kode_sku' => 'SKU-'.str_pad((string) ($index + 1), 4, '0', STR_PAD_LEFT),
                'berat_gr' => fake()->numberBetween(150, 900),
                'jumlah_halaman' => fake()->numberBetween(96, 640),
            ]);

            InventoryStock::create([
                'book_id' => $book->id,
                'stock_malang' => $data['stok'][0],
                'stock_sidoarjo' => $data['stok'][1],
                'stock_defect' => $data['stok'][2],
            ]);

            return $book;
        });

        $promotions = collect([
            Promotion::create([
                'promo_name' => 'Diskon Akhir Pekan 10%',
                'promo_type' => PromotionType::Percentage,
                'discount_percentage' => 10,
                'start_date' => now()->subDays(2)->toDateString(),
                'end_date' => now()->addDays(5)->toDateString(),
                'is_active' => true,
            ]),
            Promotion::create([
                'promo_name' => 'Harga Khusus Atomic Habits',
                'promo_type' => PromotionType::Fixed,
                'promo_value' => 79000,
                'start_date' => now()->subDay()->toDateString(),
                'end_date' => now()->addDays(14)->toDateString(),
                'is_active' => true,
            ]),
            Promotion::create([
                'promo_name' => 'Beli 3 Hemat 15%',
                'promo_type' => PromotionType::Bundle,
                'bundle_qty' => 3,
                'discount_percentage' => 15,
                'start_date' => now()->subDay()->toDateString(),
                'end_date' => now()->addDays(30)->toDateString(),
                'is_active' => true,
            ]),
        ]);

        $promotions[0]->books()->attach($books->take(5)->pluck('id')->all());
        $promotions[1]->books()->attach($books[3]->id);
        $promotions[2]->books()->attach($books->pluck('id')->all());

        $inventory = app(InventoryService::class);

        // Order sample: 1 menunggu konfirmasi, 1 diproses, 1 dikirim, 2 selesai, 1 batal.
        $orderSpecs = [
            ['customer' => 0, 'status' => OrderStatus::MenungguKonfirmasi, 'shipping' => 0, 'warehouse' => null, 'items' => [[0, 2], [1, 1]]],
            ['customer' => 1, 'status' => OrderStatus::Diproses, 'shipping' => 15000, 'warehouse' => Warehouse::Malang, 'items' => [[3, 2]]],
            ['customer' => 2, 'status' => OrderStatus::Dikirim, 'shipping' => 12000, 'warehouse' => Warehouse::Malang, 'items' => [[4, 1], [5, 1]]],
            ['customer' => 3, 'status' => OrderStatus::Selesai, 'shipping' => 20000, 'warehouse' => Warehouse::Malang, 'items' => [[2, 12]]],
            ['customer' => 0, 'status' => OrderStatus::Selesai, 'shipping' => 15000, 'warehouse' => Warehouse::Malang, 'items' => [[3, 1], [6, 1]]],
            ['customer' => 1, 'status' => OrderStatus::Batal, 'shipping' => 0, 'warehouse' => null, 'items' => [[7, 5]]],
        ];

        $sequence = 1;

        foreach ($orderSpecs as $spec) {
            $customer = $customers[$spec['customer']];

            $order = Order::create([
                'no_order' => 'ORD-'.now()->format('Ymd').'-'.str_pad((string) $sequence++, 4, '0', STR_PAD_LEFT),
                'user_id' => $customer->id,
                'nama_pembeli' => $customer->name,
                'alamat' => $customer->alamat,
                'metode_bayar' => fake()->randomElement([PaymentMethod::Transfer, PaymentMethod::Cod]),
                'total' => 0,
                'shipping_cost' => $spec['shipping'],
                'warehouse_origin' => $spec['warehouse'],
                'status' => $spec['status'],
                'ekspedisi' => $spec['warehouse'] ? fake()->randomElement(['jne', 'jnt', 'sicepat']) : null,
            ]);

            $total = 0;

            foreach ($spec['items'] as [$bookIndex, $qty]) {
                $book = $books[$bookIndex];
                $final = $qty >= 3 && $book->promotions()->where('promotions.id', $promotions[2]->id)->exists()
                    ? (int) round($book->harga * 0.85)
                    : $book->harga;

                OrderItem::create([
                    'order_id' => $order->id,
                    'book_id' => $book->id,
                    'qty' => $qty,
                    'price_original' => $book->harga,
                    'promo_discount_amount' => $book->harga - $final,
                    'tier_discount_amount' => 0,
                    'price_final' => $final,
                ]);

                $total += $final * $qty;
            }

            $order->update(['total' => $total + $spec['shipping']]);

            if ($spec['status'] === OrderStatus::Selesai) {
                foreach ($spec['items'] as [$bookIndex, $qty]) {
                    $inventory->move(
                        book: $books[$bookIndex],
                        type: MovementType::Out,
                        qty: $qty,
                        from: $spec['warehouse'],
                        orderId: $order->id,
                        userId: $admin->id,
                        description: "Deduksi stok order {$order->no_order}",
                    );
                }

                CashFlow::create(['order_id' => $order->id, 'entry_date' => now()->subDays(rand(0, 5))->toDateString(), 'flow_type' => FlowType::Revenue, 'amount' => $total, 'description' => "Pendapatan order {$order->no_order}"]);
                CashFlow::create(['order_id' => $order->id, 'entry_date' => now()->subDays(rand(0, 5))->toDateString(), 'flow_type' => FlowType::Shipping, 'amount' => $spec['shipping'], 'description' => "Ongkir order {$order->no_order}"]);
            }
        }

        $dropshipOrder = Order::where('no_order', 'like', 'ORD-'.now()->format('Ymd').'-0004')->firstOrFail();
        $dropshipOrder->update(['is_dropship' => true]);

        Dropshipper::create([
            'order_id' => $dropshipOrder->id,
            'user_id' => $customers[3]->id,
            'end_customer_name' => 'Andini Putri',
            'end_customer_whatsapp' => '081234567890',
            'end_customer_address' => 'Jl. Merdeka No. 45, Bandung',
        ]);

        // Mutasi sample — lewat InventoryService agar stok & audit trail konsisten.
        $inventory->move($books[1], MovementType::In, 5, to: Warehouse::Malang, userId: $admin->id, description: 'Stok masuk demo');
        $inventory->move($books[1], MovementType::Transfer, 3, Warehouse::Malang, Warehouse::Sidoarjo, userId: $admin->id, description: 'Transfer demo');
        $inventory->move($books[2], MovementType::Defect, 1, Warehouse::Malang, Warehouse::Defect, userId: $admin->id, description: 'Barang cacat demo');

        $this->command->info('Demo data selesai: admin@tokobuku.test / password');
    }
}

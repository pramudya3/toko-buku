<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('supplier_purchases', function (Blueprint $table) {
            $table->json('warehouse_kodes')->nullable()->after('warehouse_kode');
        });

        Schema::create('supplier_purchase_item_allocations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('supplier_purchase_item_id');
            $table->string('warehouse_kode', 50);
            $table->integer('qty')->unsigned();
            $table->timestamps();

            $table->foreign('supplier_purchase_item_id')
                ->references('id')
                ->on('supplier_purchase_items')
                ->cascadeOnDelete();

            $table->index('warehouse_kode');
            $table->unique(['supplier_purchase_item_id', 'warehouse_kode'], 'spia_item_warehouse_unique');
        });

        // Backfill existing data: for each purchase with warehouse_kode, create allocations per item
        $purchases = DB::table('supplier_purchases')->whereNotNull('warehouse_kode')->get(['id', 'warehouse_kode']);
        foreach ($purchases as $purchase) {
            // Set warehouse_kodes json for header
            DB::table('supplier_purchases')
                ->where('id', $purchase->id)
                ->update(['warehouse_kodes' => json_encode([$purchase->warehouse_kode])]);

            $items = DB::table('supplier_purchase_items')->where('supplier_purchase_id', $purchase->id)->get(['id', 'qty']);
            foreach ($items as $item) {
                DB::table('supplier_purchase_item_allocations')->insert([
                    'id' => (string) Str::uuid(),
                    'supplier_purchase_item_id' => $item->id,
                    'warehouse_kode' => $purchase->warehouse_kode,
                    'qty' => $item->qty,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        // For purchases without warehouse_kode (null), set to default warehouse if exists
        $nullPurchases = DB::table('supplier_purchases')->whereNull('warehouse_kode')->whereNull('warehouse_kodes')->get(['id']);
        if ($nullPurchases->isNotEmpty()) {
            $defaultKode = DB::table('warehouses')->where('is_defect', 0)->where('is_active', 1)->orderBy('kode')->value('kode') ?? 'malang';
            foreach ($nullPurchases as $purchase) {
                DB::table('supplier_purchases')
                    ->where('id', $purchase->id)
                    ->update(['warehouse_kodes' => json_encode([$defaultKode])]);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('supplier_purchase_item_allocations');

        Schema::table('supplier_purchases', function (Blueprint $table) {
            $table->dropColumn('warehouse_kodes');
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('supplier_return_item_allocations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('supplier_return_item_id');
            $table->string('warehouse_kode', 50);
            $table->integer('qty')->unsigned();
            $table->timestamps();

            $table->foreign('supplier_return_item_id')
                ->references('id')
                ->on('supplier_return_items')
                ->cascadeOnDelete();

            $table->index('warehouse_kode');
            $table->unique(['supplier_return_item_id', 'warehouse_kode'], 'sria_item_warehouse_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('supplier_return_item_allocations');
    }
};

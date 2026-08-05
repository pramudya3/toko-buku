<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_stocks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('book_id')->constrained()->cascadeOnDelete();
            $table->integer('stock_malang')->unsigned()->default(0);
            $table->integer('stock_sidoarjo')->unsigned()->default(0);
            $table->integer('stock_defect')->unsigned()->default(0);
            $table->timestamps();

            $table->unique('book_id');
        });

        Schema::create('inventory_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('book_id')->constrained()->cascadeOnDelete();
            $table->string('type'); // transfer | in | out | defect
            $table->string('from_warehouse')->nullable();
            $table->string('to_warehouse')->nullable();
            $table->integer('qty')->unsigned();
            $table->string('reference')->nullable(); // no_order / keterangan
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index('book_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_movements');
        Schema::dropIfExists('inventory_stocks');
    }
};

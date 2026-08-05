<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('no_order')->unique();
            $table->string('nama_pembeli');
            $table->text('alamat')->nullable();
            $table->string('no_hp')->nullable();
            $table->string('metode_bayar'); // transfer | cod
            $table->integer('total')->unsigned();
            $table->string('status')->default('baru'); // baru | diproses | dikirim | selesai | batal
            $table->timestamps();

            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('email_pembeli')->nullable()->after('no_hp');
            $table->string('kota')->nullable()->after('alamat');
            $table->string('provinsi')->nullable()->after('kota');
            $table->string('kode_pos')->nullable()->after('provinsi');
            $table->string('nama_penerima')->nullable()->after('kode_pos');
            $table->string('ekspedisi')->nullable()->after('nama_penerima'); // jne | jnt | wahana
            $table->integer('ongkir_estimasi')->unsigned()->nullable()->after('ekspedisi');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn([
                'email_pembeli', 'kota', 'provinsi', 'kode_pos',
                'nama_penerima', 'ekspedisi', 'ongkir_estimasi',
            ]);
        });
    }
};

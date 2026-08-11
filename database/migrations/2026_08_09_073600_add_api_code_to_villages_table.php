<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Kode desa api.co.id (BPS) disimpan lokal per kelurahan — di-sync 1× per
 * kecamatan via Regional API sehingga resolve ongkir tidak perlu hit API
 * berulang (lihat plan.md).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('villages', function (Blueprint $table) {
            $table->string('api_code', 10)->nullable()->after('code');
            $table->boolean('courier_support')->default(false)->after('api_code');
            $table->timestamp('api_synced_at')->nullable()->after('courier_support');
        });
    }

    public function down(): void
    {
        Schema::table('villages', function (Blueprint $table) {
            $table->dropColumn(['api_code', 'courier_support', 'api_synced_at']);
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Menambahkan kolom kontak pembeli (no_hp, email_pembeli) ke orders —
 * dengan guard agar aman di DB lama/baru.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if (! Schema::hasColumn('orders', 'no_hp')) {
                $table->string('no_hp')->nullable()->after('nama_pembeli');
            }

            if (! Schema::hasColumn('orders', 'email_pembeli')) {
                $table->string('email_pembeli')->nullable()->after('no_hp');
            }
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['no_hp', 'email_pembeli']);
        });
    }
};

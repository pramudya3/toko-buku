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
        Schema::table('cash_flow_months', function (Blueprint $table) {
            $table->boolean('is_closed')->default(false)->after('bulan');
            $table->timestamp('closed_at')->nullable()->after('is_closed');
            $table->uuid('closed_by')->nullable()->after('closed_at');
        });
    }

    public function down(): void
    {
        Schema::table('cash_flow_months', function (Blueprint $table) {
            $table->dropColumn(['is_closed', 'closed_at', 'closed_by']);
        });
    }
};

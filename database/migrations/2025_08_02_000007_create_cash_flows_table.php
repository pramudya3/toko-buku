<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cash_flows', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            $table->date('entry_date');
            $table->string('flow_type'); // revenue | shipping | refund
            $table->integer('amount')->unsigned();
            $table->string('description')->nullable();
            $table->timestamps();

            $table->index(['entry_date', 'flow_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cash_flows');
    }
};

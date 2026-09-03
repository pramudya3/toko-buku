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
        Schema::create('cash_flow_sub_categories', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('cash_flow_category_id');
            $table->string('nama');
            $table->string('description')->nullable();
            $table->integer('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('cash_flow_category_id')->references('id')->on('cash_flow_categories')->cascadeOnDelete();
            $table->unique(['cash_flow_category_id', 'nama']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cash_flow_sub_categories');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('book_edition_stocks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('book_edition_id')->constrained()->cascadeOnDelete();
            $table->foreignId('warehouse_id')->constrained()->cascadeOnDelete();
            $table->integer('qty')->unsigned()->default(0);
            $table->timestamps();

            $table->unique(['book_edition_id', 'warehouse_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('book_edition_stocks');
    }
};

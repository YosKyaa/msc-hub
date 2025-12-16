<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_booking_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inventory_booking_id')->constrained()->cascadeOnDelete();
            $table->foreignId('inventory_item_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('quantity')->default(1);
            $table->timestamps();

            $table->unique(['inventory_booking_id', 'inventory_item_id'], 'inv_booking_item_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_booking_items');
    }
};

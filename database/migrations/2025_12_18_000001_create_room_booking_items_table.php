<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('room_booking_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('room_booking_id')->constrained()->cascadeOnDelete();
            $table->foreignId('inventory_item_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('quantity')->default(1);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['room_booking_id', 'inventory_item_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('room_booking_items');
    }
};

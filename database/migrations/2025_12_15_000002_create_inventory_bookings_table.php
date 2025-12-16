<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_bookings', function (Blueprint $table) {
            $table->id();
            $table->string('booking_code', 20)->unique();

            // Requester info
            $table->string('requester_name');
            $table->string('requester_email');
            $table->string('unit')->nullable();
            $table->text('purpose')->nullable();

            // Booking period
            $table->dateTime('start_at');
            $table->dateTime('end_at');

            // Status
            $table->string('status')->default('pending');

            // Staff approval
            $table->timestamp('staff_approved_at')->nullable();
            $table->foreignId('staff_approved_by')->nullable()->constrained('users')->nullOnDelete();

            // Head approval
            $table->timestamp('head_approved_at')->nullable();
            $table->foreignId('head_approved_by')->nullable()->constrained('users')->nullOnDelete();

            // Rejection
            $table->timestamp('rejected_at')->nullable();
            $table->foreignId('rejected_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('reject_reason')->nullable();

            // Check-out
            $table->timestamp('checked_out_at')->nullable();
            $table->foreignId('checked_out_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('checkout_note')->nullable();

            // Return
            $table->timestamp('returned_at')->nullable();
            $table->foreignId('returned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('return_note')->nullable();

            // Cancelled
            $table->timestamp('cancelled_at')->nullable();

            $table->timestamps();

            $table->index('status');
            $table->index(['start_at', 'end_at']);
            $table->index('requester_email');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_bookings');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inventory_bookings', function (Blueprint $table) {
            $table->string('requester_google_id')->nullable()->after('requester_email');
        });

        Schema::table('room_bookings', function (Blueprint $table) {
            $table->string('requester_google_id')->nullable()->after('requester_email');
        });
    }

    public function down(): void
    {
        Schema::table('inventory_bookings', function (Blueprint $table) {
            $table->dropColumn('requester_google_id');
        });

        Schema::table('room_bookings', function (Blueprint $table) {
            $table->dropColumn('requester_google_id');
        });
    }
};

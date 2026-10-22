<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Absensi untuk peserta di luar JGU.
 *
 * Bawaannya tetap khusus email JGU. Kegiatan yang terbuka untuk umum —
 * seminar, lomba, pelatihan dengan peserta dari luar kampus — menyalakannya
 * per kegiatan.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('certificate_events', function (Blueprint $table) {
            $table->boolean('attendance_guests_allowed')->default(false)->after('attendance_enabled');
        });
    }

    public function down(): void
    {
        Schema::table('certificate_events', function (Blueprint $table) {
            $table->dropColumn('attendance_guests_allowed');
        });
    }
};

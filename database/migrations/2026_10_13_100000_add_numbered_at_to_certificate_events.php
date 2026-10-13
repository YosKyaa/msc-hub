<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tanggal yang dipakai bulan dan tahun pada nomor sertifikat.
 *
 * Kosong berarti mengikuti tanggal kegiatan. Diisi hanya bila nomor harus
 * memakai tanggal lain, misalnya tanggal surat keputusan.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('certificate_events', function (Blueprint $table) {
            $table->date('numbered_at')->nullable()->after('certificate_number_format');
        });
    }

    public function down(): void
    {
        Schema::table('certificate_events', function (Blueprint $table) {
            $table->dropColumn('numbered_at');
        });
    }
};

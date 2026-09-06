<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Penomoran sertifikat mengikuti ketentuan kampus, bukan pola acak.
 *
 * Formatnya disimpan sebagai pola bertoken: satu pola default berlaku untuk
 * seluruh sertifikat, dan tiap kegiatan boleh menimpanya. Nomor urutnya
 * dialokasikan lewat tabel sequence agar tidak pernah bertabrakan.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Penyimpanan pengaturan aplikasi yang boleh diubah admin tanpa deploy.
        Schema::create('app_settings', function (Blueprint $table) {
            $table->string('key')->primary();
            $table->json('value')->nullable();
            $table->timestamps();
        });

        Schema::create('certificate_number_sequences', function (Blueprint $table) {
            $table->id();
            // Cakupan penomoran, mis. "global", "2026", "2026-09", atau "event-12".
            $table->string('scope', 64);
            $table->unsignedInteger('last_number')->default(0);
            $table->timestamps();

            $table->unique('scope');
        });

        Schema::table('certificate_events', function (Blueprint $table) {
            // Kode kegiatan yang dipakai token {kode_kegiatan} pada pola nomor.
            $table->string('certificate_code', 40)->nullable()->after('organizer');
            // Pola khusus kegiatan ini; kosong berarti mengikuti pola default.
            $table->string('certificate_number_format')->nullable()->after('certificate_code');
        });
    }

    public function down(): void
    {
        Schema::table('certificate_events', function (Blueprint $table) {
            $table->dropColumn(['certificate_code', 'certificate_number_format']);
        });

        Schema::dropIfExists('certificate_number_sequences');
        Schema::dropIfExists('app_settings');
    }
};

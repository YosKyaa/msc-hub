<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Sertifikat desain sendiri: nomor dan QR diterbitkan lebih dulu, desainnya
 * dibuat di luar sistem, lalu berkas jadinya boleh diunggah kembali.
 *
 * Kegiatan yang seluruh sertifikatnya didesain sendiri tidak memerlukan
 * template, jadi kolom templatenya kini boleh kosong.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('certificates', function (Blueprint $table) {
            $table->string('design_source', 20)->default('template')->after('recipient_role_label');
            $table->string('design_file_path')->nullable()->after('design_source');
            $table->timestamp('design_uploaded_at')->nullable()->after('design_file_path');
        });

        Schema::table('certificate_events', function (Blueprint $table) {
            $table->unsignedBigInteger('certificate_template_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        // Kegiatan tanpa template tidak bisa dikembalikan ke skema lama tanpa
        // memilihkan template untuknya, dan itu bukan keputusan migrasi.
        if (DB::table('certificate_events')->whereNull('certificate_template_id')->exists()) {
            throw new RuntimeException('Masih ada kegiatan tanpa template sertifikat. Pilihkan template untuk kegiatan itu sebelum migrasi ini dibatalkan.');
        }

        Schema::table('certificate_events', function (Blueprint $table) {
            $table->unsignedBigInteger('certificate_template_id')->nullable(false)->change();
        });

        Schema::table('certificates', function (Blueprint $table) {
            $table->dropColumn(['design_source', 'design_file_path', 'design_uploaded_at']);
        });
    }
};

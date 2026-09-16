<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Daftar penerima yang boleh dibuka untuk umum.
 *
 * Sebagian kegiatan memang ingin daftar penerimanya bisa dilihat siapa saja,
 * seperti pengumuman kelulusan yang ditempel di papan. Sebagian lain tidak.
 * Karena itu keputusannya dipegang admin per kegiatan, dan bawaannya tertutup
 * — membuka daftar nama orang tidak boleh terjadi tanpa seseorang memilihnya.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('certificate_events', function (Blueprint $table) {
            $table->boolean('recipients_public')
                ->default(false)
                ->after('status')
                ->comment('Daftar penerima sertifikat dapat dilihat umum tanpa masuk.');
        });
    }

    public function down(): void
    {
        Schema::table('certificate_events', function (Blueprint $table) {
            $table->dropColumn('recipients_public');
        });
    }
};

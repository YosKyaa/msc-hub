<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Nomor awal tiap penerbit.
 *
 * Unit penerbit di kampus — Rektorat, SCD, jurusan, MSC — sering sudah
 * memegang buku register sendiri yang berjalan di luar sistem ini. Tanpa
 * nomor awal, urutannya selalu dipaksa mulai dari satu dan bertabrakan
 * dengan register yang sudah ada.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('issuers', function (Blueprint $table) {
            $table->unsignedInteger('number_start')
                ->default(1)
                ->after('number_reset')
                ->comment('Nomor urut pertama setiap kali urutan dimulai ulang.');
        });
    }

    public function down(): void
    {
        Schema::table('issuers', function (Blueprint $table) {
            $table->dropColumn('number_start');
        });
    }
};

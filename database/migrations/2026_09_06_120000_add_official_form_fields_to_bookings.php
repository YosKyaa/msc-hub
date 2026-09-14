<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Dua isian wajib pada formulir resmi kampus (FM/JGU/L.89) yang belum ada di
 * sistem: penanggung jawab dosen dan nomor HP peminjam.
 *
 * Dibuat nullable agar booking lama tetap sah; pada cetakan, isian yang kosong
 * tampil sebagai garis titik-titik untuk diisi tangan.
 */
return new class extends Migration
{
    private array $tables = ['room_bookings', 'inventory_bookings'];

    public function up(): void
    {
        foreach ($this->tables as $table) {
            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->string('supervisor_name')->nullable()->after('unit');
                $blueprint->string('requester_phone', 30)->nullable()->after('requester_email');
            });
        }
    }

    public function down(): void
    {
        foreach ($this->tables as $table) {
            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->dropColumn(['supervisor_name', 'requester_phone']);
            });
        }
    }
};

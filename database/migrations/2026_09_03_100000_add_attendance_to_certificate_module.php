<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('certificate_events', function (Blueprint $table) {
            $table->boolean('attendance_enabled')->default(false)->after('status');
            $table->string('attendance_token', 40)->nullable()->unique()->after('attendance_enabled');
            $table->timestamp('attendance_open_at')->nullable()->after('attendance_token');
            $table->timestamp('attendance_close_at')->nullable()->after('attendance_open_at');
            $table->string('eligibility_rule', 32)->default('manual')->after('attendance_close_at');
        });

        Schema::table('certificate_event_participants', function (Blueprint $table) {
            $table->timestamp('checked_in_at')->nullable()->after('attendance_status');
            $table->timestamp('checked_out_at')->nullable()->after('checked_in_at');
            $table->string('source', 32)->default('manual')->after('checked_out_at');
            // Nomor sertifikat yang ditentukan lebih dulu lewat import; dipakai saat penerbitan.
            $table->string('certificate_number')->nullable()->after('source');
        });

        Schema::table('participants', function (Blueprint $table) {
            // Nama asli dari profil Google; kolom `name` tetap nama yang dicetak.
            $table->string('google_display_name')->nullable()->after('name');
        });
    }

    public function down(): void
    {
        Schema::table('participants', function (Blueprint $table) {
            $table->dropColumn('google_display_name');
        });

        Schema::table('certificate_event_participants', function (Blueprint $table) {
            $table->dropColumn(['checked_in_at', 'checked_out_at', 'source', 'certificate_number']);
        });

        Schema::table('certificate_events', function (Blueprint $table) {
            $table->dropUnique('certificate_events_attendance_token_unique');
            $table->dropColumn([
                'attendance_enabled',
                'attendance_token',
                'attendance_open_at',
                'attendance_close_at',
                'eligibility_rule',
            ]);
        });
    }
};

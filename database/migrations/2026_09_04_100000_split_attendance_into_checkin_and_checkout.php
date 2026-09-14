<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Check-in dan check-out dipisah menjadi dua token, dua URL, dan dua window
 * waktu. Tujuannya agar peserta tidak dapat langsung check-out sesaat setelah
 * check-in: QR check-out ditolak server sampai jam yang ditentukan panitia.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('certificate_events', function (Blueprint $table) {
            $table->dropUnique('certificate_events_attendance_token_unique');
        });

        Schema::table('certificate_events', function (Blueprint $table) {
            $table->renameColumn('attendance_token', 'checkin_token');
            $table->renameColumn('attendance_open_at', 'checkin_open_at');
            $table->renameColumn('attendance_close_at', 'checkin_close_at');
        });

        Schema::table('certificate_events', function (Blueprint $table) {
            $table->string('checkout_token', 40)->nullable()->after('checkin_token');
            $table->timestamp('checkout_open_at')->nullable()->after('checkin_close_at');
            $table->timestamp('checkout_close_at')->nullable()->after('checkout_open_at');
            $table->unique('checkin_token');
            $table->unique('checkout_token');
        });

        $this->backfillExistingEvents();
    }

    public function down(): void
    {
        Schema::table('certificate_events', function (Blueprint $table) {
            $table->dropUnique('certificate_events_checkin_token_unique');
            $table->dropUnique('certificate_events_checkout_token_unique');
            $table->dropColumn(['checkout_token', 'checkout_open_at', 'checkout_close_at']);
        });

        Schema::table('certificate_events', function (Blueprint $table) {
            $table->renameColumn('checkin_token', 'attendance_token');
            $table->renameColumn('checkin_open_at', 'attendance_open_at');
            $table->renameColumn('checkin_close_at', 'attendance_close_at');
        });

        Schema::table('certificate_events', function (Blueprint $table) {
            $table->unique('attendance_token', 'certificate_events_attendance_token_unique');
        });
    }

    /**
     * Kegiatan yang absensinya sudah aktif mendapat token check-out sendiri,
     * dengan window awal disalin dari window lama supaya perilakunya tidak
     * berubah sampai panitia mempersempitnya.
     */
    private function backfillExistingEvents(): void
    {
        DB::table('certificate_events')
            ->where('attendance_enabled', true)
            ->orderBy('id')
            ->each(function (object $event) {
                DB::table('certificate_events')->where('id', $event->id)->update([
                    'checkout_token' => (string) Str::ulid(),
                    'checkout_open_at' => $event->checkin_open_at,
                    'checkout_close_at' => $event->checkin_close_at,
                ]);
            });
    }
};

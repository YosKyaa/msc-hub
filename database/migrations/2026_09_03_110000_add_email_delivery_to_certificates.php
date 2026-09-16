<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('certificates', function (Blueprint $table) {
            // Penanda idempotensi pengiriman email.
            $table->timestamp('emailed_at')->nullable()->after('issued_at');
            $table->timestamp('email_failed_at')->nullable()->after('emailed_at');
            $table->string('email_error')->nullable()->after('email_failed_at');

            // Satu keikutsertaan hanya boleh menghasilkan satu sertifikat.
            // NULL tidak saling bertabrakan, sehingga sertifikat manual tetap boleh.
            $table->unique('event_participant_id', 'certificates_event_participant_unique');
        });
    }

    public function down(): void
    {
        Schema::table('certificates', function (Blueprint $table) {
            // Foreign key dilepas dulu karena MySQL dapat memakai index unique
            // di atas sebagai index pendukungnya.
            $table->dropForeign(['event_participant_id']);
            $table->dropUnique('certificates_event_participant_unique');
            $table->foreign('event_participant_id')
                ->references('id')
                ->on('certificate_event_participants')
                ->nullOnDelete();
            $table->dropColumn(['emailed_at', 'email_failed_at', 'email_error']);
        });
    }
};

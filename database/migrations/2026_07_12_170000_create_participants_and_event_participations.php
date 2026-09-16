<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('participants', function (Blueprint $table) {
            $table->id();
            $table->enum('type', ['student', 'lecturer', 'staff', 'guest'])->default('guest');
            $table->string('institutional_id')->nullable()->index();
            $table->string('name');
            $table->string('email')->nullable()->index();
            $table->string('phone', 30)->nullable();
            $table->string('institution')->nullable();
            $table->string('faculty')->nullable();
            $table->string('study_program')->nullable();
            $table->string('source')->default('admin');
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->unique(['email', 'institutional_id']);
        });

        Schema::create('certificate_event_participants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('certificate_event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('participant_id')->constrained()->cascadeOnDelete();
            $table->string('role')->default('participant');
            $table->string('role_label')->nullable();
            $table->enum('attendance_status', ['registered', 'approved', 'attended', 'absent', 'cancelled'])->default('registered');
            $table->timestamp('eligible_at')->nullable();
            $table->json('variables')->nullable();
            $table->timestamps();
            $table->unique(['certificate_event_id', 'participant_id', 'role'], 'event_participant_role_unique');
        });

        Schema::table('certificates', function (Blueprint $table) {
            $table->foreignId('participant_id')->nullable()->after('certificate_event_id')->constrained()->nullOnDelete();
            $table->foreignId('event_participant_id')->nullable()->after('participant_id')->constrained('certificate_event_participants')->nullOnDelete();
            $table->string('recipient_role')->default('participant')->after('recipient_email');
            $table->string('recipient_role_label')->nullable()->after('recipient_role');
        });
    }

    public function down(): void
    {
        Schema::table('certificates', function (Blueprint $table) {
            $table->dropConstrainedForeignId('event_participant_id');
            $table->dropConstrainedForeignId('participant_id');
            $table->dropColumn(['recipient_role', 'recipient_role_label']);
        });
        Schema::dropIfExists('certificate_event_participants');
        Schema::dropIfExists('participants');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('certificate_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('background_path');
            $table->unsignedInteger('canvas_width')->default(1123);
            $table->unsignedInteger('canvas_height')->default(794);
            $table->json('elements')->nullable();
            $table->json('fonts')->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('certificate_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('certificate_template_id')->constrained()->restrictOnDelete();
            $table->string('name');
            $table->string('slug')->unique();
            $table->date('event_date');
            $table->string('organizer')->nullable();
            $table->string('signatory_name')->nullable();
            $table->string('signatory_title')->nullable();
            $table->enum('status', ['draft', 'published', 'archived'])->default('draft');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('certificates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('certificate_event_id')->constrained()->cascadeOnDelete();
            $table->string('certificate_number')->unique();
            $table->uuid('verification_token')->unique();
            $table->string('recipient_name');
            $table->string('recipient_email')->nullable()->index();
            $table->json('variables')->nullable();
            $table->timestamp('issued_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->text('revocation_reason')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('certificates');
        Schema::dropIfExists('certificate_events');
        Schema::dropIfExists('certificate_templates');
    }
};

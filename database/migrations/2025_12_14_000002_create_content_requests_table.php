<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('content_requests', function (Blueprint $table) {
            $table->id();
            $table->string('request_code', 20)->unique();
            
            // Requester info
            $table->string('requester_name');
            $table->string('requester_email');
            $table->string('requester_google_id')->nullable();
            $table->string('requester_type')->default('other'); // student, lecturer, staff, other
            $table->string('unit')->nullable();
            $table->string('phone', 20)->nullable();
            
            // Request details
            $table->string('content_type'); // Required
            $table->string('platform_target')->nullable();
            $table->text('purpose')->nullable();
            $table->text('audience')->nullable();
            $table->date('event_date')->nullable();
            $table->string('location')->nullable();
            $table->date('deadline')->nullable();
            $table->text('materials_link')->nullable();
            $table->longText('notes')->nullable();
            
            // Workflow
            $table->string('status')->default('incoming');
            $table->foreignId('assigned_to_user_id')->nullable()->constrained('users')->nullOnDelete();
            
            // Staff approval
            $table->timestamp('staff_approved_at')->nullable();
            $table->foreignId('staff_approved_by')->nullable()->constrained('users')->nullOnDelete();
            
            // Head approval
            $table->timestamp('head_approved_at')->nullable();
            $table->foreignId('head_approved_by')->nullable()->constrained('users')->nullOnDelete();
            
            // Rejection
            $table->text('reject_reason')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->foreignId('rejected_by')->nullable()->constrained('users')->nullOnDelete();
            
            // Publication
            $table->text('published_link')->nullable();
            $table->text('source_link')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamp('archived_at')->nullable();
            
            // Asset Vault integration
            $table->foreignId('linked_project_id')->nullable()->constrained('projects')->nullOnDelete();
            $table->foreignId('created_asset_id')->nullable()->constrained('assets')->nullOnDelete();
            
            $table->timestamps();
            
            // Indexes
            $table->index('status');
            $table->index('requester_email');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('content_requests');
    }
};

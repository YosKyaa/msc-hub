<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title');
            $table->string('asset_type');
            $table->string('platform');
            $table->string('source_link', 2048)->nullable();
            $table->string('output_link', 2048)->nullable();
            $table->date('happened_at')->nullable();
            $table->unsignedSmallInteger('year')->nullable();
            $table->string('status')->default('final');
            $table->foreignId('pic_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->text('notes')->nullable();
            $table->boolean('is_featured')->default(false);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['asset_type', 'platform', 'status']);
            $table->index('year');
            $table->index('happened_at');
            $table->index('is_featured');
            $table->fullText(['title', 'notes']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assets');
    }
};

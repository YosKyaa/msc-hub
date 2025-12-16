<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('content_request_comments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('content_request_id')->constrained()->cascadeOnDelete();
            $table->string('author_type'); // requester, staff, head
            $table->string('author_name')->nullable();
            $table->string('author_email')->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->longText('message');
            $table->timestamps();
            
            $table->index('content_request_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('content_request_comments');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Twibbon: bingkai PNG berlubang transparan yang dipasang peserta di atas
 * fotonya sendiri. Fotonya diolah di peramban peserta dan tidak pernah sampai
 * ke server, jadi tabel ini hanya menyimpan bingkainya.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('twibbons', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->text('caption')->nullable();
            $table->string('frame_path');
            $table->unsignedInteger('frame_width')->default(0);
            $table->unsignedInteger('frame_height')->default(0);
            $table->decimal('transparent_ratio', 5, 2)->nullable();
            $table->boolean('is_published')->default(false);
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->unsignedBigInteger('uses_count')->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['is_published', 'starts_at', 'ends_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('twibbons');
    }
};

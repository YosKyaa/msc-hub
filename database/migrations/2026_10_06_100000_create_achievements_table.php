<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Prestasi MSC: penghargaan, sertifikat, dan piala.
 *
 * Dipisahkan dari featured_works karena keduanya menjawab pertanyaan yang
 * berbeda. Karya menunjukkan "apa yang bisa dibuatkan untuk saya"; prestasi
 * menunjukkan "seberapa bisa dipercaya yang membuatkannya". Menyatukannya
 * memaksa salah satunya memakai kolom yang tidak berlaku baginya — karya
 * tidak punya pemberi penghargaan, prestasi tidak punya klien.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('achievements', function (Blueprint $table) {
            $table->id();
            $table->string('title');

            // Dari siapa penghargaannya. Inilah yang membuat sebuah prestasi
            // berarti; tanpa penyebutan pemberinya ia hanya klaim sepihak.
            $table->string('awarded_by')->nullable();

            $table->string('category')->default('penghargaan');

            // Tingkatannya — kampus, nasional, internasional — dipakai
            // mengurutkan yang paling berbobot lebih dulu.
            $table->string('level')->nullable();

            $table->text('description')->nullable();

            // Foto piala atau pindaian sertifikatnya. Boleh kosong: prestasi
            // yang belum sempat difoto tetap layak disebut.
            $table->string('image')->nullable();

            $table->date('achieved_at')->nullable();
            $table->integer('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            // Beranda hanya memuat yang aktif, terurut. Satu indeks menutup
            // keduanya sekaligus.
            $table->index(['is_active', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('achievements');
    }
};

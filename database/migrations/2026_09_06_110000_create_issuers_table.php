<?php

use App\Services\Certificates\CertificateNumberFormat;
use App\Support\AppSetting;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Penerbit sertifikat, agar MSC dapat menerbitkan untuk pihak di luar JGU.
 *
 * Sebelumnya penyelenggara hanya teks bebas pada kegiatan, sehingga identitas
 * JGU tertanam di halaman verifikasi, email, dan kode penomoran. Penerbit kini
 * menjadi record tersendiri yang memiliki brand dan penomorannya masing-masing.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('issuers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code', 40)->unique();
            $table->string('logo_path')->nullable();
            $table->string('address_line')->nullable();
            $table->string('verification_note')->nullable();
            // Pola dan titik ulang nomor khusus penerbit ini; kosong berarti
            // mengikuti pengaturan default sistem.
            $table->string('number_pattern')->nullable();
            $table->string('number_reset', 20)->nullable();
            // Penerbit rumah dipakai untuk kegiatan yang tidak menyebut penerbit.
            $table->boolean('is_house')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::table('certificate_events', function (Blueprint $table) {
            $table->foreignId('issuer_id')->nullable()->after('organizer')->constrained('issuers')->nullOnDelete();
        });

        $this->createHouseIssuer();
    }

    public function down(): void
    {
        Schema::table('certificate_events', function (Blueprint $table) {
            $table->dropConstrainedForeignId('issuer_id');
        });

        // Kembalikan cakupan nomor urut ke bentuk tanpa awalan penerbit.
        DB::table('certificate_number_sequences')->get()->each(function (object $row) {
            $scope = preg_replace('/^issuer-\d+:/', '', $row->scope);

            if ($scope !== $row->scope) {
                DB::table('certificate_number_sequences')->where('id', $row->id)->update(['scope' => $scope]);
            }
        });

        Schema::dropIfExists('issuers');
    }

    /**
     * MSC JGU menjadi penerbit rumah, memakai kode unit yang sudah diatur.
     * Nomor urut yang sudah berjalan dipindahkan ke cakupannya supaya
     * penomoran yang sedang berlaku tidak terulang dari awal.
     */
    private function createHouseIssuer(): void
    {
        $code = (string) AppSetting::get(
            CertificateNumberFormat::SETTING_UNIT_CODE,
            CertificateNumberFormat::DEFAULT_UNIT_CODE,
        );

        $houseId = DB::table('issuers')->insertGetId([
            'name' => 'Media & Strategic Communications, Jakarta Global University',
            'code' => $code,
            'address_line' => 'Jl. Boulevard Grand Depok City, Depok, Jawa Barat',
            'is_house' => true,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('certificate_number_sequences')->update([
            'scope' => DB::raw("CONCAT('issuer-{$houseId}:', scope)"),
        ]);
    }
};

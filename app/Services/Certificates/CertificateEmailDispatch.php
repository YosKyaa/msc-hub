<?php

namespace App\Services\Certificates;

use Illuminate\Bus\Batch;

/**
 * Hasil satu permintaan kirim email sertifikat.
 *
 * Tidak semua yang diminta selalu ikut diantrekan: bila kuota harian akun
 * pengirim tidak cukup, sisanya ditahan dan tetap berstatus menunggu kirim.
 * Admin harus tahu berapa yang ditahan, kalau tidak ia mengira seluruhnya
 * sedang dikirim.
 */
final class CertificateEmailDispatch
{
    public function __construct(
        public readonly Batch $batch,
        public readonly int $heldBack = 0,
        public readonly ?int $dailyLimit = null,
    ) {}

    public function queued(): int
    {
        return $this->batch->totalJobs;
    }
}

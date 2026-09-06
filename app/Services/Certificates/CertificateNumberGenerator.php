<?php

namespace App\Services\Certificates;

use App\Models\Certificate;
use App\Models\CertificateEvent;
use Illuminate\Support\Facades\DB;

/**
 * Menerbitkan nomor sertifikat berikutnya menurut pola yang berlaku.
 *
 * Nomor urut dialokasikan dengan penguncian baris, sehingga dua penerbitan
 * yang berjalan bersamaan — misalnya dua job dari satu batch — tidak pernah
 * memperoleh nomor yang sama.
 */
class CertificateNumberGenerator
{
    /** Batas percobaan bila pola menghasilkan nomor yang sudah terpakai. */
    private const MAX_ATTEMPTS = 25;

    public function next(CertificateEvent $event): string
    {
        $format = CertificateNumberFormat::for($event);
        $moment = now();
        $scope = $format->sequenceScope($event, $moment);

        for ($attempt = 0; $attempt < self::MAX_ATTEMPTS; $attempt++) {
            $number = $format->render($event, $this->allocate($scope), $moment);

            // Pola yang tidak memuat token {nomor} akan selalu menghasilkan
            // teks sama; berhenti agar tidak berputar tanpa guna.
            if (! Certificate::where('certificate_number', $number)->exists()) {
                return $number;
            }

            if (! str_contains($format->pattern, '{nomor')) {
                break;
            }
        }

        throw CertificateNumberException::exhausted($format->pattern);
    }

    /**
     * Ambil nomor urut berikutnya untuk sebuah cakupan.
     */
    private function allocate(string $scope): int
    {
        return DB::transaction(function () use ($scope) {
            $current = DB::table('certificate_number_sequences')
                ->where('scope', $scope)
                ->lockForUpdate()
                ->first();

            if ($current === null) {
                DB::table('certificate_number_sequences')->insert([
                    'scope' => $scope,
                    'last_number' => 1,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                return 1;
            }

            $next = $current->last_number + 1;

            DB::table('certificate_number_sequences')
                ->where('scope', $scope)
                ->update(['last_number' => $next, 'updated_at' => now()]);

            return $next;
        });
    }
}

<?php

namespace App\Services\Certificates;

use App\Enums\CertificateNumberReset;
use App\Models\Issuer;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Penghitung nomor urut milik tiap penerbit.
 *
 * Unit penerbit di kampus memegang buku register sendiri, dan pertanyaan
 * pertama yang selalu muncul adalah "kita sudah sampai nomor berapa?".
 * Angkanya dulu hanya hidup di dalam tabel dan tidak pernah terlihat, apalagi
 * bisa diselaraskan dengan register yang sudah berjalan. Semua pembacaan dan
 * penyetelannya dikumpulkan di sini.
 */
class CertificateNumberCounter
{
    /**
     * Cakupan penghitung yang sedang berlaku bagi sebuah penerbit.
     *
     * Mengembalikan null untuk penerbit yang mengulang nomor tiap kegiatan:
     * penghitungnya memang bukan satu, melainkan satu per kegiatan.
     */
    public function currentScope(Issuer $issuer, ?Carbon $moment = null): ?string
    {
        $moment ??= Carbon::now();
        $reset = $issuer->number_reset ?? CertificateNumberFormat::default()->reset;

        $period = match ($reset) {
            CertificateNumberReset::YEARLY => 'year-'.$moment->format('Y'),
            CertificateNumberReset::MONTHLY => 'month-'.$moment->format('Y-m'),
            CertificateNumberReset::NEVER => 'global',
            CertificateNumberReset::PER_EVENT => null,
        };

        return $period === null ? null : 'issuer-'.$issuer->id.':'.$period;
    }

    /**
     * Nomor terakhir yang sudah terpakai, atau null bila belum ada satu pun.
     */
    public function lastNumber(Issuer $issuer, ?Carbon $moment = null): ?int
    {
        $scope = $this->currentScope($issuer, $moment);

        if ($scope === null) {
            return null;
        }

        $last = DB::table('certificate_number_sequences')->where('scope', $scope)->value('last_number');

        return $last === null ? null : (int) $last;
    }

    /**
     * Nomor yang akan diberikan pada penerbitan berikutnya.
     */
    public function nextNumber(Issuer $issuer, ?Carbon $moment = null): int
    {
        $last = $this->lastNumber($issuer, $moment);

        return $last === null ? $issuer->startingNumber() : $last + 1;
    }

    /**
     * Selaraskan penghitung dengan register yang sudah berjalan di luar
     * sistem: sebutkan nomor berikutnya yang diinginkan.
     */
    public function setNextNumber(Issuer $issuer, int $next, ?Carbon $moment = null): void
    {
        $scope = $this->currentScope($issuer, $moment);

        if ($scope === null) {
            throw CertificateNumberException::perEventCounter();
        }

        // Disimpan sebagai "nomor terakhir", karena itulah yang dibaca
        // pengalokasi saat menerbitkan berikutnya.
        DB::table('certificate_number_sequences')->updateOrInsert(
            ['scope' => $scope],
            ['last_number' => max(0, $next - 1), 'updated_at' => Carbon::now()],
        );
    }
}

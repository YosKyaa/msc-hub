<?php

namespace App\Services\Certificates;

use App\Models\Certificate;
use App\Models\Participant;

/**
 * Satu tempat untuk mengoreksi nama dan email penerima.
 *
 * `certificates.recipient_name` dan `recipient_email` adalah salinan nilai
 * peserta pada saat penerbitan, bukan rujukan hidup ke tabel peserta. Akibatnya
 * membetulkan data di tabel peserta saja tidak mengubah apa pun yang sudah
 * terbit: nama yang salah ketik tetap tercetak di PDF dan di halaman
 * verifikasi, dan email yang sudah dibetulkan tetap dikirim ke alamat lama.
 * Keduanya gagal tanpa suara — tidak ada galat, dan panel tetap menyatakan
 * semuanya beres.
 *
 * Karena itu koreksinya dikerjakan di sini, lalu disalin ulang ke seluruh
 * sertifikat milik orang tersebut. Satu orang satu baris peserta, dipakai
 * bersama oleh semua kegiatan yang pernah diikutinya, jadi koreksi yang
 * dilakukan dari kegiatan mana pun berlaku untuk semuanya.
 */
class RecipientCorrection
{
    public function __construct(private readonly ParticipantRegistry $registry) {}

    /**
     * Terapkan koreksi, lalu rambatkan ke sertifikat yang sudah terbit.
     *
     * Nama kosong diabaikan alih-alih menghapus nama yang ada: sertifikat
     * tanpa nama tidak ada gunanya. Email boleh dikosongkan — peserta hasil
     * impor memang ada yang tidak punya alamat.
     */
    public function apply(Participant $participant, ?string $name, ?string $email): RecipientCorrectionOutcome
    {
        $namaBaru = trim((string) $name);
        $emailBaru = ParticipantRegistry::normaliseEmail($email) ?: null;

        $namaBerubah = $namaBaru !== '' && $namaBaru !== $participant->name;
        $emailBerubah = $emailBaru !== $participant->email;

        if (! $namaBerubah && ! $emailBerubah) {
            return RecipientCorrectionOutcome::nothing();
        }

        if ($namaBerubah) {
            $participant->name = $namaBaru;
        }

        if ($emailBerubah) {
            $participant->email = $emailBaru;
        }

        $participant->save();

        [$disalin, $dibukaUlang] = $this->syncCertificates($participant, $namaBerubah, $emailBerubah);

        return new RecipientCorrectionOutcome($namaBerubah, $emailBerubah, $disalin, $dibukaUlang);
    }

    /**
     * Apakah email ini sudah dipakai peserta lain?
     *
     * Aturan dedupnya sendiri tinggal di ParticipantRegistry; di sini hanya
     * dipanggil agar bentrokan ditolak dengan pesan yang jelas sebelum
     * disimpan, bukan baru ketahuan sebagai galat dari batas unik basis data.
     */
    public function emailTaken(Participant $participant, ?string $email): bool
    {
        return $this->registry->emailTakenByAnother($participant, $email);
    }

    /**
     * @return array{0: int, 1: bool} Jumlah sertifikat yang disesuaikan, dan
     *                                apakah ada penanda pengiriman yang dibuka ulang.
     */
    private function syncCertificates(Participant $participant, bool $namaBerubah, bool $emailBerubah): array
    {
        $sertifikat = Certificate::where('participant_id', $participant->getKey())->get();

        $disalin = 0;
        $dibukaUlang = false;

        foreach ($sertifikat as $satu) {
            $perubahan = [];

            if ($namaBerubah) {
                $perubahan['recipient_name'] = $participant->name;
            }

            if ($emailBerubah) {
                $perubahan['recipient_email'] = $participant->email;

                // Catatan pengiriman itu menyangkut alamat lama, jadi tidak
                // lagi berlaku bagi alamat yang baru: kalau penandanya
                // dibiarkan, tombol Kirim Email akan melewati sertifikat ini
                // dan orangnya tidak pernah menerima apa pun. Sertifikat yang
                // sudah dicabut dilewati — ia memang tidak untuk dikirim.
                if ($satu->revoked_at === null) {
                    $dibukaUlang = $dibukaUlang || $satu->emailed_at !== null;

                    $perubahan['emailed_at'] = null;
                    $perubahan['email_failed_at'] = null;
                    $perubahan['email_error'] = null;
                }
            }

            $satu->forceFill($perubahan)->save();
            $disalin++;
        }

        return [$disalin, $dibukaUlang];
    }
}

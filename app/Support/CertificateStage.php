<?php

namespace App\Support;

use App\Models\CertificateEventParticipant;

/**
 * Sejauh mana sertifikat seseorang sudah berjalan.
 *
 * Keadaannya dulu tersebar di beberapa kolom terpisah — eligible, sertifikat,
 * email — sehingga admin harus merangkainya sendiri, di dua tabel pula. Satu
 * tahap yang bernama jelas menggantikan semuanya, dan aturannya hanya hidup
 * di sini.
 */
enum CertificateStage: string
{
    case NOT_ELIGIBLE = 'not_eligible';
    case READY = 'ready';
    case ISSUED = 'issued';
    case ISSUED_WITHOUT_EMAIL = 'issued_without_email';
    case SENT = 'sent';
    case FAILED = 'failed';
    case REVOKED = 'revoked';

    public static function for(CertificateEventParticipant $participation): self
    {
        $certificate = $participation->certificate;

        if ($certificate === null) {
            return $participation->isEligible() ? self::READY : self::NOT_ELIGIBLE;
        }

        if ($certificate->revoked_at !== null) {
            return self::REVOKED;
        }

        if ($certificate->emailed_at !== null) {
            return self::SENT;
        }

        if ($certificate->email_failed_at !== null) {
            return self::FAILED;
        }

        return blank($certificate->recipient_email) ? self::ISSUED_WITHOUT_EMAIL : self::ISSUED;
    }

    public function getLabel(): string
    {
        return match ($this) {
            self::NOT_ELIGIBLE => 'Belum berhak',
            self::READY => 'Siap diterbitkan',
            self::ISSUED => 'Terbit, belum dikirim',
            self::ISSUED_WITHOUT_EMAIL => 'Terbit, tanpa email',
            self::SENT => 'Email terkirim',
            self::FAILED => 'Email gagal',
            self::REVOKED => 'Dicabut',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::NOT_ELIGIBLE => 'gray',
            self::READY => 'info',
            self::ISSUED, self::ISSUED_WITHOUT_EMAIL => 'warning',
            self::SENT => 'success',
            self::FAILED, self::REVOKED => 'danger',
        };
    }

    /**
     * Apa yang perlu dilakukan admin berikutnya, dalam satu kalimat.
     */
    public function getHint(): string
    {
        return match ($this) {
            self::NOT_ELIGIBLE => 'Tandai berhak dulu sebelum sertifikatnya bisa diterbitkan.',
            self::READY => 'Tinggal diterbitkan lewat tombol Terbitkan Digital.',
            self::ISSUED => 'Sudah bisa diunduh dan diverifikasi. Kirim emailnya bila hasilnya sudah benar.',
            self::ISSUED_WITHOUT_EMAIL => 'Penerimanya belum punya alamat email, jadi tidak bisa dikirimi.',
            self::SENT => 'Selesai — sertifikat sudah sampai ke penerimanya.',
            self::FAILED => 'Pengiriman gagal. Periksa alamat emailnya, lalu kirim ulang.',
            self::REVOKED => 'Sertifikat dicabut dan tidak lagi dianggap sah.',
        };
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        $options = [];

        foreach (self::cases() as $case) {
            $options[$case->value] = $case->getLabel();
        }

        return $options;
    }
}

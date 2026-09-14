<?php

namespace App\Services\Certificates;

use App\Models\CertificateEventParticipant;

/**
 * Hasil satu kali penekanan tombol absensi.
 */
class AttendanceOutcome
{
    public const CHECKED_IN = 'checked_in';

    public const ALREADY_CHECKED_IN = 'already_checked_in';

    public const CHECKED_OUT = 'checked_out';

    public const ALREADY_CHECKED_OUT = 'already_checked_out';

    /** Peserta memindai QR check-out tanpa pernah check-in. */
    public const NOT_CHECKED_IN = 'not_checked_in';

    public function __construct(
        public readonly string $action,
        public readonly ?CertificateEventParticipant $participation = null,
    ) {}

    public function isError(): bool
    {
        return $this->action === self::NOT_CHECKED_IN;
    }

    public function message(): string
    {
        return match ($this->action) {
            self::CHECKED_IN => 'Check-in berhasil. Terima kasih sudah hadir.',
            self::ALREADY_CHECKED_IN => 'Anda sudah check-in sebelumnya.',
            self::CHECKED_OUT => 'Check-out berhasil. Kehadiran Anda lengkap.',
            self::ALREADY_CHECKED_OUT => 'Anda sudah check-out sebelumnya.',
            self::NOT_CHECKED_IN => 'Anda belum melakukan check-in, sehingga check-out tidak dapat dicatat. Hubungi panitia.',
            default => 'Kehadiran Anda sudah tercatat.',
        };
    }
}

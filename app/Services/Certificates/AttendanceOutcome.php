<?php

namespace App\Services\Certificates;

use App\Models\CertificateEventParticipant;

/**
 * Hasil satu kali penekanan tombol absensi.
 */
class AttendanceOutcome
{
    public const CHECKED_IN = 'checked_in';

    public const CHECKED_OUT = 'checked_out';

    public const ALREADY_COMPLETE = 'already_complete';

    public function __construct(
        public readonly string $action,
        public readonly CertificateEventParticipant $participation,
    ) {}

    public function message(): string
    {
        return match ($this->action) {
            self::CHECKED_IN => 'Check-in berhasil. Terima kasih sudah hadir.',
            self::CHECKED_OUT => 'Check-out berhasil. Kehadiran Anda lengkap.',
            default => 'Kehadiran Anda sudah tercatat sebelumnya.',
        };
    }
}

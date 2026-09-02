<?php

namespace App\Enums;

/**
 * Aturan penentuan kelayakan peserta menerima sertifikat, dipilih per kegiatan.
 */
enum EligibilityRule: string
{
    case CHECKIN_ONLY = 'checkin_only';
    case CHECKIN_AND_CHECKOUT = 'checkin_and_checkout';
    case MANUAL = 'manual';

    public function getLabel(): string
    {
        return match ($this) {
            self::CHECKIN_ONLY => 'Cukup check-in',
            self::CHECKIN_AND_CHECKOUT => 'Check-in dan check-out',
            self::MANUAL => 'Ditentukan manual oleh admin',
        };
    }

    public function getDescription(): string
    {
        return match ($this) {
            self::CHECKIN_ONLY => 'Peserta berhak setelah melakukan check-in.',
            self::CHECKIN_AND_CHECKOUT => 'Peserta berhak setelah check-in dan check-out.',
            self::MANUAL => 'Absensi tetap dicatat, tetapi kelayakan hanya ditandai admin.',
        };
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return array_reduce(
            self::cases(),
            fn (array $options, self $case) => $options + [$case->value => $case->getLabel()],
            [],
        );
    }
}

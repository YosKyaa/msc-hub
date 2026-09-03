<?php

namespace App\Enums;

/**
 * Check-in dan check-out adalah dua aksi terpisah dengan token, URL, QR, dan
 * window waktu masing-masing. Enum ini menjadi satu-satunya tempat pemetaan
 * aksi ke kolom database, sehingga tidak ada string kolom yang berserakan.
 */
enum AttendanceAction: string
{
    case CHECK_IN = 'checkin';
    case CHECK_OUT = 'checkout';

    public function getLabel(): string
    {
        return match ($this) {
            self::CHECK_IN => 'Check-in',
            self::CHECK_OUT => 'Check-out',
        };
    }

    public function getInstruction(): string
    {
        return match ($this) {
            self::CHECK_IN => 'Tekan saat Anda tiba di lokasi kegiatan.',
            self::CHECK_OUT => 'Tekan saat kegiatan selesai dan Anda meninggalkan lokasi.',
        };
    }

    public function tokenColumn(): string
    {
        return "{$this->value}_token";
    }

    public function openColumn(): string
    {
        return "{$this->value}_open_at";
    }

    public function closeColumn(): string
    {
        return "{$this->value}_close_at";
    }

    /** Kolom jam pada certificate_event_participants. */
    public function recordedColumn(): string
    {
        return match ($this) {
            self::CHECK_IN => 'checked_in_at',
            self::CHECK_OUT => 'checked_out_at',
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

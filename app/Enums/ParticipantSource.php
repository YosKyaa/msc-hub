<?php

namespace App\Enums;

/**
 * Asal pendaftaran seseorang pada sebuah kegiatan.
 */
enum ParticipantSource: string
{
    case ATTENDANCE = 'attendance';
    case MANUAL = 'manual';
    case IMPORT = 'import';

    public function getLabel(): string
    {
        return match ($this) {
            self::ATTENDANCE => 'Absensi QR',
            self::MANUAL => 'Input manual',
            self::IMPORT => 'Import Excel',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::ATTENDANCE => 'success',
            self::MANUAL => 'gray',
            self::IMPORT => 'info',
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

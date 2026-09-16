<?php

namespace App\Enums;

/**
 * Kapan nomor urut sertifikat kembali ke 1.
 */
enum CertificateNumberReset: string
{
    case YEARLY = 'yearly';
    case MONTHLY = 'monthly';
    case PER_EVENT = 'per_event';
    case NEVER = 'never';

    public function getLabel(): string
    {
        return match ($this) {
            self::YEARLY => 'Setiap tahun',
            self::MONTHLY => 'Setiap bulan',
            self::PER_EVENT => 'Setiap kegiatan',
            self::NEVER => 'Tidak pernah (berlanjut terus)',
        };
    }

    public function getDescription(): string
    {
        return match ($this) {
            self::YEARLY => 'Nomor urut mulai dari 1 lagi pada 1 Januari.',
            self::MONTHLY => 'Nomor urut mulai dari 1 lagi setiap awal bulan.',
            self::PER_EVENT => 'Tiap kegiatan punya urutan sendiri mulai dari 1.',
            self::NEVER => 'Satu urutan berjalan terus untuk seluruh sertifikat.',
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

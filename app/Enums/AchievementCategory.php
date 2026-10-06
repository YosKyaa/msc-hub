<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * Jenis prestasi yang dipajang MSC.
 *
 * Dibatasi empat supaya daftarnya tetap terbaca sekilas. "Lainnya" ada untuk
 * yang tidak masuk ketiganya — apresiasi, piagam, pengakuan — ketimbang
 * memaksa admin memilih kategori yang keliru.
 */
enum AchievementCategory: string implements HasColor, HasLabel
{
    case AWARD = 'penghargaan';
    case CERTIFICATE = 'sertifikat';
    case TROPHY = 'piala';
    case OTHER = 'lainnya';

    public function getLabel(): string
    {
        return match ($this) {
            self::AWARD => 'Penghargaan',
            self::CERTIFICATE => 'Sertifikat',
            self::TROPHY => 'Piala',
            self::OTHER => 'Lainnya',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::AWARD => 'warning',
            self::CERTIFICATE => 'info',
            self::TROPHY => 'success',
            self::OTHER => 'gray',
        };
    }

    /**
     * Lambang yang dipakai di beranda saat prestasinya belum berfoto.
     */
    public function icon(): string
    {
        return match ($this) {
            self::AWARD => 'M11.48 3.5a.56.56 0 0 1 1.04 0l2.12 4.3 4.75.69a.56.56 0 0 1 .31.96l-3.44 3.35.81 4.73a.56.56 0 0 1-.81.59L12 15.88l-4.26 2.24a.56.56 0 0 1-.81-.6l.81-4.72-3.43-3.35a.56.56 0 0 1 .31-.96l4.74-.69 2.12-4.3Z',
            self::CERTIFICATE => 'M9 12h6m-6 4h4m5 5H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h9l5 5v11a2 2 0 0 1-2 2Zm-5-18v6h6',
            self::TROPHY => 'M8 21h8m-4-4v4m6-17v5a6 6 0 0 1-12 0V4h12ZM6 6H4.5A1.5 1.5 0 0 0 3 7.5v1A3.5 3.5 0 0 0 6.5 12M18 6h1.5A1.5 1.5 0 0 1 21 7.5v1A3.5 3.5 0 0 1 17.5 12',
            self::OTHER => 'm12 3 2.6 5.3 5.9.9-4.3 4.1 1 5.8-5.2-2.7-5.2 2.7 1-5.8L3.5 9.2l5.9-.9L12 3Z',
        };
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        $pilihan = [];

        foreach (self::cases() as $case) {
            $pilihan[$case->value] = $case->getLabel();
        }

        return $pilihan;
    }
}

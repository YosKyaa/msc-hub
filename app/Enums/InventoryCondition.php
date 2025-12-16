<?php

namespace App\Enums;

enum InventoryCondition: string
{
    case GOOD = 'good';
    case MINOR_ISSUE = 'minor_issue';
    case BROKEN = 'broken';
    case MAINTENANCE = 'maintenance';

    public function getLabel(): string
    {
        return match ($this) {
            self::GOOD => 'Baik',
            self::MINOR_ISSUE => 'Ada Masalah Kecil',
            self::BROKEN => 'Rusak',
            self::MAINTENANCE => 'Sedang Diperbaiki',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::GOOD => 'success',
            self::MINOR_ISSUE => 'warning',
            self::BROKEN => 'danger',
            self::MAINTENANCE => 'info',
        };
    }

    public function isBookable(): bool
    {
        return in_array($this, [self::GOOD, self::MINOR_ISSUE]);
    }
}

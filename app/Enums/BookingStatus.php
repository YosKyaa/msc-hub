<?php

namespace App\Enums;

enum BookingStatus: string
{
    case PENDING = 'pending';
    case APPROVED_STAFF = 'approved_staff';
    case APPROVED_HEAD = 'approved_head';
    case REJECTED = 'rejected';
    case CHECKED_OUT = 'checked_out';
    case RETURNED = 'returned';
    case CANCELLED = 'cancelled';
    case COMPLETED = 'completed';

    public function getLabel(): string
    {
        return match ($this) {
            self::PENDING => 'Pending',
            self::APPROVED_STAFF => 'Approved (Staff)',
            self::APPROVED_HEAD => 'Approved (Head)',
            self::REJECTED => 'Ditolak',
            self::CHECKED_OUT => 'Dipinjam',
            self::RETURNED => 'Dikembalikan',
            self::CANCELLED => 'Dibatalkan',
            self::COMPLETED => 'Selesai',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::PENDING => 'warning',
            self::APPROVED_STAFF => 'info',
            self::APPROVED_HEAD => 'success',
            self::REJECTED => 'danger',
            self::CHECKED_OUT => 'primary',
            self::RETURNED => 'success',
            self::CANCELLED => 'gray',
            self::COMPLETED => 'success',
        };
    }

    public function getIcon(): string
    {
        return match ($this) {
            self::PENDING => 'heroicon-o-clock',
            self::APPROVED_STAFF => 'heroicon-o-check',
            self::APPROVED_HEAD => 'heroicon-o-check-badge',
            self::REJECTED => 'heroicon-o-x-circle',
            self::CHECKED_OUT => 'heroicon-o-arrow-right-start-on-rectangle',
            self::RETURNED => 'heroicon-o-arrow-uturn-left',
            self::CANCELLED => 'heroicon-o-x-mark',
            self::COMPLETED => 'heroicon-o-check-circle',
        };
    }

    public static function activeStatuses(): array
    {
        return [
            self::PENDING,
            self::APPROVED_STAFF,
            self::APPROVED_HEAD,
            self::CHECKED_OUT,
        ];
    }

    public static function roomActiveStatuses(): array
    {
        return [
            self::PENDING,
            self::APPROVED_STAFF,
            self::APPROVED_HEAD,
        ];
    }
}

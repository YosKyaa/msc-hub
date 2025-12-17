<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum AnnouncementCategory: string implements HasLabel, HasColor, HasIcon
{
    case Announcement = 'announcement';
    case Event = 'event';
    case Sop = 'sop';
    case Maintenance = 'maintenance';

    public function getLabel(): string
    {
        return match ($this) {
            self::Announcement => 'Pengumuman',
            self::Event => 'Event',
            self::Sop => 'SOP',
            self::Maintenance => 'Maintenance',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::Announcement => 'primary',
            self::Event => 'success',
            self::Sop => 'warning',
            self::Maintenance => 'danger',
        };
    }

    public function getIcon(): ?string
    {
        return match ($this) {
            self::Announcement => 'heroicon-o-megaphone',
            self::Event => 'heroicon-o-calendar',
            self::Sop => 'heroicon-o-document-text',
            self::Maintenance => 'heroicon-o-wrench-screwdriver',
        };
    }
}

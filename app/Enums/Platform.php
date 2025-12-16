<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum Platform: string implements HasLabel, HasColor, HasIcon
{
    case Instagram = 'instagram';
    case TikTok = 'tiktok';
    case Facebook = 'facebook';
    case Website = 'website';
    case YouTube = 'youtube';
    case Drive = 'drive';
    case Figma = 'figma';
    case Canva = 'canva';
    case Other = 'other';

    public function getLabel(): string
    {
        return match ($this) {
            self::Instagram => 'Instagram',
            self::TikTok => 'TikTok',
            self::Facebook => 'Facebook',
            self::Website => 'Website',
            self::YouTube => 'YouTube',
            self::Drive => 'Google Drive',
            self::Figma => 'Figma',
            self::Canva => 'Canva',
            self::Other => 'Lainnya',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::Instagram => 'danger',
            self::TikTok => 'gray',
            self::Facebook => 'info',
            self::Website => 'success',
            self::YouTube => 'danger',
            self::Drive => 'warning',
            self::Figma => 'primary',
            self::Canva => 'info',
            self::Other => 'gray',
        };
    }

    public function getIcon(): ?string
    {
        return match ($this) {
            self::Instagram => 'heroicon-o-camera',
            self::TikTok => 'heroicon-o-musical-note',
            self::Facebook => 'heroicon-o-chat-bubble-oval-left',
            self::Website => 'heroicon-o-globe-alt',
            self::YouTube => 'heroicon-o-play-circle',
            self::Drive => 'heroicon-o-cloud',
            self::Figma => 'heroicon-o-swatch',
            self::Canva => 'heroicon-o-sparkles',
            self::Other => 'heroicon-o-ellipsis-horizontal-circle',
        };
    }
}

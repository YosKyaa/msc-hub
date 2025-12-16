<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum AssetType: string implements HasLabel, HasColor, HasIcon
{
    case Photo = 'photo';
    case Video = 'video';
    case Design = 'design';
    case Banner = 'banner';
    case Document = 'document';
    case Post = 'post';
    case Other = 'other';

    public function getLabel(): string
    {
        return match ($this) {
            self::Photo => 'Foto',
            self::Video => 'Video',
            self::Design => 'Desain',
            self::Banner => 'Banner',
            self::Document => 'Dokumen',
            self::Post => 'Post',
            self::Other => 'Lainnya',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::Photo => 'success',
            self::Video => 'danger',
            self::Design => 'warning',
            self::Banner => 'info',
            self::Document => 'gray',
            self::Post => 'primary',
            self::Other => 'gray',
        };
    }

    public function getIcon(): ?string
    {
        return match ($this) {
            self::Photo => 'heroicon-o-photo',
            self::Video => 'heroicon-o-video-camera',
            self::Design => 'heroicon-o-paint-brush',
            self::Banner => 'heroicon-o-rectangle-group',
            self::Document => 'heroicon-o-document-text',
            self::Post => 'heroicon-o-chat-bubble-left-right',
            self::Other => 'heroicon-o-ellipsis-horizontal-circle',
        };
    }
}

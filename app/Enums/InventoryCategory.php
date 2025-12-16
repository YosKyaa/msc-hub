<?php

namespace App\Enums;

enum InventoryCategory: string
{
    case CAMERA = 'camera';
    case LENS = 'lens';
    case MICROPHONE = 'microphone';
    case TRIPOD = 'tripod';
    case LIGHTING = 'lighting';
    case AUDIO = 'audio';
    case VIDEO = 'video';
    case COMPUTER = 'computer';
    case PROJECTOR = 'projector';
    case OTHER = 'other';

    public function getLabel(): string
    {
        return match ($this) {
            self::CAMERA => 'Kamera',
            self::LENS => 'Lensa',
            self::MICROPHONE => 'Mikrofon',
            self::TRIPOD => 'Tripod',
            self::LIGHTING => 'Lighting',
            self::AUDIO => 'Audio',
            self::VIDEO => 'Video',
            self::COMPUTER => 'Komputer',
            self::PROJECTOR => 'Proyektor',
            self::OTHER => 'Lainnya',
        };
    }

    public function getIcon(): string
    {
        return match ($this) {
            self::CAMERA => 'heroicon-o-camera',
            self::LENS => 'heroicon-o-eye',
            self::MICROPHONE => 'heroicon-o-microphone',
            self::TRIPOD => 'heroicon-o-arrows-pointing-out',
            self::LIGHTING => 'heroicon-o-light-bulb',
            self::AUDIO => 'heroicon-o-speaker-wave',
            self::VIDEO => 'heroicon-o-video-camera',
            self::COMPUTER => 'heroicon-o-computer-desktop',
            self::PROJECTOR => 'heroicon-o-tv',
            self::OTHER => 'heroicon-o-cube',
        };
    }
}

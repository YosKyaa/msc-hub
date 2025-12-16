<?php

namespace App\Enums;

enum ContentType: string
{
    case PHOTO_DOCUMENTATION = 'photo_documentation';
    case VIDEO_DOCUMENTATION = 'video_documentation';
    case DESIGN_POSTER = 'design_poster';
    case DESIGN_BANNER = 'design_banner';
    case DESIGN_FLYER = 'design_flyer';
    case SOCIAL_MEDIA_POST = 'social_media_post';
    case VIDEO_PROFILE = 'video_profile';
    case VIDEO_TEASER = 'video_teaser';
    case LIVE_STREAMING = 'live_streaming';
    case WEBSITE_NEWS = 'website_news';
    case OTHER = 'other';

    public function getLabel(): string
    {
        return match ($this) {
            self::PHOTO_DOCUMENTATION => 'Dokumentasi Foto',
            self::VIDEO_DOCUMENTATION => 'Dokumentasi Video',
            self::DESIGN_POSTER => 'Desain Poster',
            self::DESIGN_BANNER => 'Desain Banner',
            self::DESIGN_FLYER => 'Desain Flyer',
            self::SOCIAL_MEDIA_POST => 'Post Social Media',
            self::VIDEO_PROFILE => 'Video Profil',
            self::VIDEO_TEASER => 'Video Teaser/Promo',
            self::LIVE_STREAMING => 'Live Streaming',
            self::WEBSITE_NEWS => 'Berita Website',
            self::OTHER => 'Lainnya',
        };
    }

    public function getIcon(): string
    {
        return match ($this) {
            self::PHOTO_DOCUMENTATION => 'heroicon-o-camera',
            self::VIDEO_DOCUMENTATION => 'heroicon-o-video-camera',
            self::DESIGN_POSTER, self::DESIGN_BANNER, self::DESIGN_FLYER => 'heroicon-o-paint-brush',
            self::SOCIAL_MEDIA_POST => 'heroicon-o-chat-bubble-left',
            self::VIDEO_PROFILE, self::VIDEO_TEASER => 'heroicon-o-film',
            self::LIVE_STREAMING => 'heroicon-o-signal',
            self::WEBSITE_NEWS => 'heroicon-o-newspaper',
            self::OTHER => 'heroicon-o-document',
        };
    }
}

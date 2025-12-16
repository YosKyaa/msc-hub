<?php

namespace App\Filament\Widgets;

use App\Enums\AssetStatus;
use App\Enums\AssetType;
use App\Models\Asset;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class AssetStatsWidget extends StatsOverviewWidget
{
    protected static ?int $sort = -2;

    protected function getStats(): array
    {
        $totalAssets = Asset::count();
        $publishedAssets = Asset::where('status', AssetStatus::Published)->count();
        $photoAssets = Asset::where('asset_type', AssetType::Photo)->count();
        $videoAssets = Asset::where('asset_type', AssetType::Video)->count();
        $designAssets = Asset::where('asset_type', AssetType::Design)->count();

        return [
            Stat::make('Total Aset', $totalAssets)
                ->description('Semua aset tersimpan')
                ->descriptionIcon('heroicon-m-archive-box')
                ->color('primary'),

            Stat::make('Published', $publishedAssets)
                ->description('Sudah dipublikasi')
                ->descriptionIcon('heroicon-m-check-badge')
                ->color('success'),

            Stat::make('Foto', $photoAssets)
                ->description('Dokumentasi foto')
                ->descriptionIcon('heroicon-m-photo')
                ->color('info'),

            Stat::make('Video', $videoAssets)
                ->description('Konten video')
                ->descriptionIcon('heroicon-m-video-camera')
                ->color('danger'),

            Stat::make('Desain', $designAssets)
                ->description('Desain grafis')
                ->descriptionIcon('heroicon-m-paint-brush')
                ->color('warning'),
        ];
    }
}

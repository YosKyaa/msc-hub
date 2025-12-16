<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\AssetResource;
use App\Models\Asset;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class RecentAssetsWidget extends BaseWidget
{
    protected static ?int $sort = -1;

    protected int|string|array $columnSpan = 'full';

    protected static ?string $heading = 'Aset Terbaru';

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Asset::query()
                    ->with(['project', 'creator'])
                    ->latest()
                    ->limit(5)
            )
            ->columns([
                TextColumn::make('title')
                    ->label('Judul')
                    ->limit(40)
                    ->url(fn (Asset $record): string => AssetResource::getUrl('view', ['record' => $record])),

                TextColumn::make('asset_type')
                    ->label('Tipe')
                    ->badge(),

                TextColumn::make('platform')
                    ->badge(),

                TextColumn::make('project.title')
                    ->label('Project')
                    ->placeholder('Standalone')
                    ->limit(20),

                TextColumn::make('status')
                    ->badge(),

                TextColumn::make('creator.name')
                    ->label('Dibuat Oleh'),

                TextColumn::make('created_at')
                    ->label('Tanggal')
                    ->since(),
            ])
            ->paginated(false);
    }
}

<?php

namespace App\Filament\Resources\AssetResource\Pages;

use App\Filament\Resources\AssetResource;
use Filament\Actions;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ViewAsset extends ViewRecord
{
    protected static string $resource = AssetResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('open_output')
                ->label('Buka Output')
                ->icon('heroicon-o-arrow-top-right-on-square')
                ->color('success')
                ->url(fn () => $this->record->output_link)
                ->openUrlInNewTab()
                ->visible(fn () => $this->record->output_link),

            Actions\Action::make('open_source')
                ->label('Buka Source')
                ->icon('heroicon-o-link')
                ->color('info')
                ->url(fn () => $this->record->source_link)
                ->openUrlInNewTab()
                ->visible(fn () => $this->record->source_link),

            Actions\EditAction::make(),
        ];
    }

    public function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Informasi Aset')
                    ->schema([
                        TextEntry::make('title')
                            ->label('Judul'),

                        TextEntry::make('asset_type')
                            ->label('Tipe')
                            ->badge(),

                        TextEntry::make('platform')
                            ->badge(),

                        TextEntry::make('status')
                            ->badge(),

                        TextEntry::make('project.title')
                            ->label('Project')
                            ->placeholder('Standalone'),

                        TextEntry::make('happened_at')
                            ->label('Tanggal')
                            ->date('d M Y'),

                        TextEntry::make('pic.name')
                            ->label('PIC')
                            ->placeholder('-'),

                        IconEntry::make('is_featured')
                            ->label('Featured')
                            ->boolean()
                            ->trueIcon('heroicon-o-star')
                            ->falseIcon('heroicon-o-star')
                            ->trueColor('warning')
                            ->falseColor('gray'),
                    ])
                    ->columns(4),

                Section::make('Link')
                    ->schema([
                        TextEntry::make('source_link')
                            ->label('Link Source')
                            ->placeholder('Tidak ada')
                            ->url(fn ($record) => $record->source_link)
                            ->openUrlInNewTab(),

                        TextEntry::make('output_link')
                            ->label('Link Output')
                            ->placeholder('Tidak ada')
                            ->url(fn ($record) => $record->output_link)
                            ->openUrlInNewTab(),
                    ])
                    ->columns(2),

                Section::make('Tags')
                    ->schema([
                        TextEntry::make('tags.name')
                            ->label('')
                            ->badge()
                            ->color('primary')
                            ->separator(',')
                            ->placeholder('Tidak ada tags'),
                    ]),

                Section::make('Catatan')
                    ->schema([
                        TextEntry::make('notes')
                            ->label('')
                            ->placeholder('Tidak ada catatan')
                            ->markdown(),
                    ])
                    ->collapsible(),

                Section::make('Metadata')
                    ->schema([
                        TextEntry::make('creator.name')
                            ->label('Dibuat Oleh'),

                        TextEntry::make('created_at')
                            ->label('Dibuat Pada')
                            ->dateTime('d M Y H:i'),

                        TextEntry::make('updated_at')
                            ->label('Terakhir Diupdate')
                            ->dateTime('d M Y H:i'),
                    ])
                    ->columns(3)
                    ->collapsed(),
            ]);
    }
}

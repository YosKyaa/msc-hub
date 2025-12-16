<?php

namespace App\Filament\Resources\ProjectResource\RelationManagers;

use App\Enums\AssetStatus;
use App\Enums\AssetType;
use App\Enums\Platform;
use App\Models\User;
use Filament\Actions;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class AssetsRelationManager extends RelationManager
{
    protected static string $relationship = 'assets';

    protected static ?string $title = 'Aset';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('title')
                    ->label('Judul')
                    ->required()
                    ->maxLength(255),

                Select::make('asset_type')
                    ->label('Tipe')
                    ->options(AssetType::class)
                    ->required(),

                Select::make('platform')
                    ->label('Platform')
                    ->options(Platform::class)
                    ->required(),

                Select::make('status')
                    ->label('Status')
                    ->options(AssetStatus::class)
                    ->default(AssetStatus::Final)
                    ->required(),

                TextInput::make('source_link')
                    ->label('Link Source (Drive/Figma/Canva)')
                    ->url()
                    ->maxLength(2048),

                TextInput::make('output_link')
                    ->label('Link Output (IG/YouTube/Web)')
                    ->url()
                    ->maxLength(2048),

                DatePicker::make('happened_at')
                    ->label('Tanggal')
                    ->default(now())
                    ->native(false),

                Select::make('pic_user_id')
                    ->label('PIC')
                    ->options(fn () => User::whereHas('roles', fn ($q) => $q->whereIn('name', ['admin', 'staff_msc', 'head_msc']))->pluck('name', 'id'))
                    ->searchable(),

                Textarea::make('notes')
                    ->label('Catatan')
                    ->rows(2)
                    ->columnSpanFull(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('title')
            ->columns([
                TextColumn::make('title')
                    ->label('Judul')
                    ->searchable()
                    ->limit(30),

                TextColumn::make('asset_type')
                    ->label('Tipe')
                    ->badge(),

                TextColumn::make('platform')
                    ->label('Platform')
                    ->badge(),

                TextColumn::make('status')
                    ->badge(),

                TextColumn::make('happened_at')
                    ->label('Tanggal')
                    ->date('d M Y'),

                IconColumn::make('has_links')
                    ->label('Link')
                    ->state(fn ($record) => $record->source_link || $record->output_link)
                    ->boolean(),
            ])
            ->filters([
                SelectFilter::make('asset_type')
                    ->options(AssetType::class),
                SelectFilter::make('status')
                    ->options(AssetStatus::class),
            ])
            ->headerActions([
                Actions\CreateAction::make()
                    ->mutateFormDataUsing(function (array $data): array {
                        $data['created_by'] = auth()->id();
                        return $data;
                    }),
            ])
            ->actions([
                Actions\Action::make('open_link')
                    ->label('Buka')
                    ->icon('heroicon-o-arrow-top-right-on-square')
                    ->url(fn ($record) => $record->output_link ?? $record->source_link)
                    ->openUrlInNewTab()
                    ->visible(fn ($record) => $record->output_link || $record->source_link),
                Actions\EditAction::make(),
                Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Actions\BulkActionGroup::make([
                    Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }
}

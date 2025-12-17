<?php

namespace App\Filament\Resources;

use App\Enums\AssetStatus;
use App\Enums\AssetType;
use App\Enums\Platform;
use App\Filament\Resources\AssetResource\Pages;
use App\Models\Asset;
use App\Models\Project;
use App\Models\Tag;
use App\Models\User;
use BackedEnum;
use Filament\Actions;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use UnitEnum;

class AssetResource extends Resource
{
    protected static ?string $model = Asset::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-archive-box';

    protected static string|UnitEnum|null $navigationGroup = 'Asset Vault';

    protected static ?int $navigationSort = 2;

    protected static ?string $recordTitleAttribute = 'title';

    public static function canAccess(): bool
    {
        return auth()->user()?->can('assets.view') ?? false;
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['title', 'notes', 'project.title'];
    }

    public static function getGlobalSearchResultDetails(\Illuminate\Database\Eloquent\Model $record): array
    {
        return [
            'Tipe' => $record->asset_type->getLabel(),
            'Project' => $record->project?->title ?? 'Standalone',
        ];
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Informasi Aset')
                    ->schema([
                        TextInput::make('title')
                            ->label('Judul')
                            ->required()
                            ->maxLength(255)
                            ->columnSpanFull(),

                        Select::make('asset_type')
                            ->label('Tipe Aset')
                            ->options(AssetType::class)
                            ->required()
                            ->native(false),

                        Select::make('platform')
                            ->label('Platform')
                            ->options(Platform::class)
                            ->required()
                            ->native(false),

                        Select::make('project_id')
                            ->label('Project')
                            ->relationship('project', 'title')
                            ->searchable()
                            ->preload()
                            ->placeholder('Standalone (tanpa project)'),

                        Select::make('status')
                            ->label('Status')
                            ->options(AssetStatus::class)
                            ->default(AssetStatus::Final)
                            ->required()
                            ->native(false),

                        DatePicker::make('happened_at')
                            ->label('Tanggal Event/Konten')
                            ->default(now())
                            ->native(false),

                        Select::make('pic_user_id')
                            ->label('PIC')
                            ->options(fn () => User::whereHas('roles', fn ($q) => $q->whereIn('name', ['admin', 'staff_msc', 'head_msc']))->pluck('name', 'id'))
                            ->searchable()
                            ->placeholder('Pilih PIC'),
                    ])
                    ->columns(2),

                Section::make('Link')
                    ->schema([
                        TextInput::make('source_link')
                            ->label('Link Source')
                            ->helperText('Link kerja (Drive/Figma/Canva)')
                            ->url()
                            ->maxLength(2048),

                        TextInput::make('output_link')
                            ->label('Link Output')
                            ->helperText('Link final (Instagram/YouTube/Website)')
                            ->url()
                            ->maxLength(2048),
                    ])
                    ->columns(2),

                Section::make('Detail Tambahan')
                    ->schema([
                        Select::make('tags')
                            ->label('Tags')
                            ->relationship('tags', 'name')
                            ->multiple()
                            ->preload()
                            ->createOptionForm([
                                TextInput::make('name')
                                    ->label('Nama Tag')
                                    ->required()
                                    ->maxLength(255),
                            ]),

                        Toggle::make('is_featured')
                            ->label('Featured')
                            ->helperText('Tampilkan di showcase/highlight'),

                        Textarea::make('notes')
                            ->label('Catatan')
                            ->rows(3)
                            ->columnSpanFull(),
                    ])
                    ->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('title')
                    ->label('Judul')
                    ->searchable()
                    ->sortable()
                    ->limit(35)
                    ->tooltip(fn ($record) => $record->title),

                TextColumn::make('asset_type')
                    ->label('Tipe')
                    ->badge()
                    ->sortable(),

                TextColumn::make('platform')
                    ->badge()
                    ->sortable(),

                TextColumn::make('project.title')
                    ->label('Project')
                    ->placeholder('Standalone')
                    ->limit(20)
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('status')
                    ->badge()
                    ->sortable(),

                TextColumn::make('happened_at')
                    ->label('Tanggal')
                    ->date('d M Y')
                    ->sortable(),

                IconColumn::make('is_featured')
                    ->label('Featured')
                    ->boolean()
                    ->trueIcon('heroicon-o-star')
                    ->falseIcon('heroicon-o-star')
                    ->trueColor('warning')
                    ->falseColor('gray')
                    ->toggleable(),

                TextColumn::make('pic.name')
                    ->label('PIC')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('creator.name')
                    ->label('Dibuat Oleh')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('updated_at')
                    ->label('Diupdate')
                    ->dateTime('d M Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('updated_at', 'desc')
            ->filters([
                SelectFilter::make('asset_type')
                    ->label('Tipe')
                    ->options(AssetType::class),

                SelectFilter::make('platform')
                    ->options(Platform::class),

                SelectFilter::make('status')
                    ->options(AssetStatus::class),

                SelectFilter::make('project_id')
                    ->label('Project')
                    ->options(fn () => Project::pluck('title', 'id'))
                    ->searchable(),

                SelectFilter::make('year')
                    ->label('Tahun')
                    ->options(fn () => Asset::query()
                        ->whereNotNull('year')
                        ->distinct()
                        ->orderBy('year', 'desc')
                        ->pluck('year', 'year')
                        ->toArray()
                    ),

                SelectFilter::make('tags')
                    ->relationship('tags', 'name')
                    ->multiple()
                    ->preload(),

                SelectFilter::make('pic_user_id')
                    ->label('PIC')
                    ->options(fn () => User::whereHas('roles', fn ($q) => $q->whereIn('name', ['admin', 'staff_msc', 'head_msc']))->pluck('name', 'id'))
                    ->searchable(),

                TernaryFilter::make('is_featured')
                    ->label('Featured'),

                TrashedFilter::make(),
            ])
            ->actions([
                Actions\ActionGroup::make([
                    Actions\Action::make('open_primary')
                        ->label('Buka Link')
                        ->icon('heroicon-o-arrow-top-right-on-square')
                        ->color('primary')
                        ->url(fn ($record) => $record->output_link ?? $record->source_link)
                        ->openUrlInNewTab()
                        ->visible(fn ($record) => $record->output_link || $record->source_link),

                    Actions\Action::make('open_source')
                        ->label('Buka Source')
                        ->icon('heroicon-o-link')
                        ->url(fn ($record) => $record->source_link)
                        ->openUrlInNewTab()
                        ->visible(fn ($record) => $record->source_link && $record->output_link),

                    Actions\Action::make('toggle_featured')
                        ->label(fn ($record) => $record->is_featured ? 'Unfeature' : 'Feature')
                        ->icon(fn ($record) => $record->is_featured ? 'heroicon-o-star' : 'heroicon-s-star')
                        ->color(fn ($record) => $record->is_featured ? 'gray' : 'warning')
                        ->action(fn ($record) => $record->update(['is_featured' => !$record->is_featured]))
                        ->requiresConfirmation(),

                    Actions\Action::make('duplicate')
                        ->label('Duplikat')
                        ->icon('heroicon-o-document-duplicate')
                        ->color('gray')
                        ->action(function ($record) {
                            $newAsset = $record->replicate(['created_by']);
                            $newAsset->title = $record->title . ' (Copy)';
                            $newAsset->created_by = auth()->id();
                            $newAsset->save();
                            $newAsset->tags()->sync($record->tags->pluck('id'));

                            Notification::make()
                                ->title('Aset berhasil diduplikat')
                                ->success()
                                ->send();
                        }),

                    Actions\ViewAction::make(),
                    Actions\EditAction::make(),
                    Actions\DeleteAction::make(),
                    Actions\RestoreAction::make(),
                ]),
            ])
            ->bulkActions([
                Actions\BulkActionGroup::make([
                    Actions\BulkAction::make('change_status')
                        ->label('Ubah Status')
                        ->icon('heroicon-o-arrow-path')
                        ->form([
                            Select::make('status')
                                ->label('Status Baru')
                                ->options(AssetStatus::class)
                                ->required(),
                        ])
                        ->action(function (Collection $records, array $data) {
                            $records->each->update(['status' => $data['status']]);
                            Notification::make()
                                ->title('Status berhasil diubah')
                                ->success()
                                ->send();
                        })
                        ->deselectRecordsAfterCompletion(),

                    Actions\BulkAction::make('set_project')
                        ->label('Set Project')
                        ->icon('heroicon-o-folder')
                        ->form([
                            Select::make('project_id')
                                ->label('Project')
                                ->options(fn () => Project::pluck('title', 'id'))
                                ->searchable()
                                ->placeholder('Pilih project atau kosongkan untuk standalone'),
                        ])
                        ->action(function (Collection $records, array $data) {
                            $records->each->update(['project_id' => $data['project_id'] ?: null]);
                            Notification::make()
                                ->title('Project berhasil diset')
                                ->success()
                                ->send();
                        })
                        ->deselectRecordsAfterCompletion(),

                    Actions\BulkAction::make('add_tags')
                        ->label('Tambah Tags')
                        ->icon('heroicon-o-tag')
                        ->form([
                            Select::make('tags')
                                ->label('Tags')
                                ->options(fn () => Tag::pluck('name', 'id'))
                                ->multiple()
                                ->required(),
                        ])
                        ->action(function (Collection $records, array $data) {
                            $records->each(fn ($record) => $record->tags()->syncWithoutDetaching($data['tags']));
                            Notification::make()
                                ->title('Tags berhasil ditambahkan')
                                ->success()
                                ->send();
                        })
                        ->deselectRecordsAfterCompletion(),

                    Actions\DeleteBulkAction::make(),
                    Actions\RestoreBulkAction::make(),
                    Actions\ForceDeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAssets::route('/'),
            'create' => Pages\CreateAsset::route('/create'),
            'view' => Pages\ViewAsset::route('/{record}'),
            'edit' => Pages\EditAsset::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }
}

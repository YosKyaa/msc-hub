<?php

namespace App\Filament\Resources;

use App\Enums\AnnouncementCategory;
use App\Filament\Resources\AnnouncementResource\Pages;
use App\Models\Announcement;
use BackedEnum;
use Filament\Actions;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class AnnouncementResource extends Resource
{
    protected static ?string $model = Announcement::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-megaphone';

    protected static string|UnitEnum|null $navigationGroup = 'Konten';

    protected static ?int $navigationSort = 1;

    protected static ?string $navigationLabel = 'Pengumuman';

    protected static ?string $modelLabel = 'Pengumuman';

    protected static ?string $pluralModelLabel = 'Pengumuman';

    protected static ?string $recordTitleAttribute = 'title';

    public static function getGloballySearchableAttributes(): array
    {
        return ['title', 'summary', 'content'];
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Informasi Pengumuman')
                    ->schema([
                        TextInput::make('title')
                            ->label('Judul')
                            ->required()
                            ->maxLength(255)
                            ->live(onBlur: true)
                            ->afterStateUpdated(function ($state, $set, $record) {
                                if (!$record) {
                                    $set('slug', Announcement::generateUniqueSlug($state ?? ''));
                                }
                            })
                            ->columnSpanFull(),

                        TextInput::make('slug')
                            ->label('Slug')
                            ->required()
                            ->maxLength(255)
                            ->unique(ignoreRecord: true)
                            ->helperText('URL-friendly identifier (auto-generated)')
                            ->columnSpanFull(),

                        Textarea::make('summary')
                            ->label('Ringkasan')
                            ->required()
                            ->maxLength(300)
                            ->rows(2)
                            ->helperText('Maks 300 karakter. Ditampilkan di daftar pengumuman.')
                            ->columnSpanFull(),

                        Select::make('category')
                            ->label('Kategori')
                            ->options(AnnouncementCategory::class)
                            ->default(AnnouncementCategory::Announcement)
                            ->required()
                            ->native(false),

                        DateTimePicker::make('published_at')
                            ->label('Tanggal Terbit')
                            ->helperText('Kosongkan untuk draft. Set masa depan untuk jadwal.')
                            ->native(false),
                    ])
                    ->columns(2),

                Section::make('Gambar')
                    ->schema([
                        FileUpload::make('image')
                            ->label('Gambar Pengumuman')
                            ->image()
                            ->directory('announcements')
                            ->imageEditor()
                            ->maxSize(2048)
                            ->helperText('Maks 2MB. Format: JPG, PNG, WebP')
                            ->columnSpanFull(),
                    ]),

                Section::make('Konten')
                    ->schema([
                        RichEditor::make('content')
                            ->label('Konten Lengkap')
                            ->helperText('Opsional. Ditampilkan di halaman detail.')
                            ->toolbarButtons([
                                'bold',
                                'italic',
                                'underline',
                                'strike',
                                'link',
                                'orderedList',
                                'bulletList',
                                'h2',
                                'h3',
                                'blockquote',
                            ])
                            ->columnSpanFull(),
                    ]),

                Section::make('Pengaturan')
                    ->schema([
                        Toggle::make('is_pinned')
                            ->label('Disematkan')
                            ->helperText('Ditampilkan di atas daftar pengumuman'),

                        Toggle::make('is_active')
                            ->label('Aktif')
                            ->default(true)
                            ->helperText('Nonaktifkan untuk menyembunyikan dari publik'),
                    ])
                    ->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('image')
                    ->label('Gambar')
                    ->circular()
                    ->defaultImageUrl(fn () => 'https://ui-avatars.com/api/?name=No+Image&background=e5e7eb&color=9ca3af')
                    ->size(40),

                TextColumn::make('title')
                    ->label('Judul')
                    ->searchable()
                    ->sortable()
                    ->limit(40)
                    ->tooltip(fn ($record) => $record->title),

                TextColumn::make('category')
                    ->label('Kategori')
                    ->badge()
                    ->sortable(),

                TextColumn::make('published_at')
                    ->label('Terbit')
                    ->dateTime('d M Y H:i')
                    ->sortable()
                    ->placeholder('Draft')
                    ->description(fn ($record) => $record->isDraft() ? 'Belum diterbitkan' : ($record->published_at->isFuture() ? 'Terjadwal' : 'Sudah terbit')),

                IconColumn::make('is_pinned')
                    ->label('Pin')
                    ->boolean()
                    ->trueIcon('heroicon-s-bookmark')
                    ->falseIcon('heroicon-o-bookmark')
                    ->trueColor('warning')
                    ->falseColor('gray'),

                IconColumn::make('is_active')
                    ->label('Aktif')
                    ->boolean(),

                TextColumn::make('creator.name')
                    ->label('Dibuat Oleh')
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('updated_at')
                    ->label('Diupdate')
                    ->dateTime('d M Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('updated_at', 'desc')
            ->filters([
                SelectFilter::make('category')
                    ->label('Kategori')
                    ->options(AnnouncementCategory::class),

                SelectFilter::make('status')
                    ->label('Status')
                    ->options([
                        'published' => 'Sudah Terbit',
                        'scheduled' => 'Terjadwal',
                        'draft' => 'Draft',
                    ])
                    ->query(function (Builder $query, array $data) {
                        return match ($data['value']) {
                            'published' => $query->whereNotNull('published_at')->where('published_at', '<=', now()),
                            'scheduled' => $query->whereNotNull('published_at')->where('published_at', '>', now()),
                            'draft' => $query->whereNull('published_at'),
                            default => $query,
                        };
                    }),

                TernaryFilter::make('is_pinned')
                    ->label('Disematkan'),

                TernaryFilter::make('is_active')
                    ->label('Aktif'),
            ])
            ->actions([
                Actions\ActionGroup::make([
                    Actions\Action::make('publish_now')
                        ->label('Terbitkan Sekarang')
                        ->icon('heroicon-o-paper-airplane')
                        ->color('success')
                        ->action(function ($record) {
                            $record->update(['published_at' => now()]);
                            Notification::make()
                                ->title('Pengumuman berhasil diterbitkan')
                                ->success()
                                ->send();
                        })
                        ->requiresConfirmation()
                        ->visible(fn ($record) => $record->isDraft() || $record->published_at?->isFuture()),

                    Actions\Action::make('toggle_pin')
                        ->label(fn ($record) => $record->is_pinned ? 'Lepas Pin' : 'Sematkan')
                        ->icon(fn ($record) => $record->is_pinned ? 'heroicon-o-bookmark-slash' : 'heroicon-s-bookmark')
                        ->color(fn ($record) => $record->is_pinned ? 'gray' : 'warning')
                        ->action(fn ($record) => $record->update(['is_pinned' => !$record->is_pinned])),

                    Actions\Action::make('view_public')
                        ->label('Lihat di Website')
                        ->icon('heroicon-o-eye')
                        ->url(fn ($record) => route('announcements.show', $record->slug))
                        ->openUrlInNewTab()
                        ->visible(fn ($record) => $record->isPublished() && $record->is_active),

                    Actions\ViewAction::make(),
                    Actions\EditAction::make(),
                    Actions\DeleteAction::make()
                        ->visible(fn () => auth()->user()->isAdmin()),
                ]),
            ])
            ->bulkActions([
                Actions\BulkActionGroup::make([
                    Actions\BulkAction::make('publish_all')
                        ->label('Terbitkan Semua')
                        ->icon('heroicon-o-paper-airplane')
                        ->action(function ($records) {
                            $records->each->update(['published_at' => now()]);
                            Notification::make()
                                ->title('Pengumuman berhasil diterbitkan')
                                ->success()
                                ->send();
                        })
                        ->requiresConfirmation()
                        ->deselectRecordsAfterCompletion(),

                    Actions\BulkAction::make('toggle_active')
                        ->label('Toggle Aktif')
                        ->icon('heroicon-o-eye')
                        ->action(function ($records) {
                            $records->each(fn ($record) => $record->update(['is_active' => !$record->is_active]));
                            Notification::make()
                                ->title('Status aktif berhasil diubah')
                                ->success()
                                ->send();
                        })
                        ->deselectRecordsAfterCompletion(),

                    Actions\DeleteBulkAction::make()
                        ->visible(fn () => auth()->user()->isAdmin()),
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
            'index' => Pages\ListAnnouncements::route('/'),
            'create' => Pages\CreateAnnouncement::route('/create'),
            'view' => Pages\ViewAnnouncement::route('/{record}'),
            'edit' => Pages\EditAnnouncement::route('/{record}/edit'),
        ];
    }
}

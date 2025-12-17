<?php

namespace App\Filament\Resources;

use App\Filament\Resources\FeaturedWorkResource\Pages;
use App\Models\FeaturedWork;
use BackedEnum;
use Filament\Actions;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use UnitEnum;

class FeaturedWorkResource extends Resource
{
    protected static ?string $model = FeaturedWork::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-star';

    protected static string|UnitEnum|null $navigationGroup = 'Konten';

    protected static ?int $navigationSort = 2;

    protected static ?string $navigationLabel = 'Featured Works';

    protected static ?string $modelLabel = 'Featured Work';

    protected static ?string $pluralModelLabel = 'Featured Works';

    protected static ?string $recordTitleAttribute = 'title';

    public static function canAccess(): bool
    {
        return auth()->user()?->can('featured_works.view') ?? false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Informasi Karya')
                    ->schema([
                        TextInput::make('title')
                            ->label('Judul')
                            ->required()
                            ->maxLength(255)
                            ->live(onBlur: true)
                            ->afterStateUpdated(function ($state, $set, $record) {
                                if (!$record) {
                                    $set('slug', FeaturedWork::generateUniqueSlug($state ?? ''));
                                }
                            })
                            ->columnSpanFull(),

                        TextInput::make('slug')
                            ->label('Slug')
                            ->required()
                            ->maxLength(255)
                            ->unique(ignoreRecord: true)
                            ->helperText('URL-friendly identifier'),

                        TextInput::make('category')
                            ->label('Kategori')
                            ->placeholder('Video, Desain, Foto, dll')
                            ->maxLength(100),

                        TextInput::make('client')
                            ->label('Klien')
                            ->placeholder('Nama klien/unit')
                            ->maxLength(255),

                        DatePicker::make('project_date')
                            ->label('Tanggal Project')
                            ->native(false),

                        TextInput::make('url')
                            ->label('URL')
                            ->url()
                            ->placeholder('https://...')
                            ->helperText('Link ke karya (YouTube, Behance, dll)'),

                        Textarea::make('description')
                            ->label('Deskripsi')
                            ->rows(3)
                            ->columnSpanFull(),
                    ])
                    ->columns(2),

                Section::make('Gambar')
                    ->schema([
                        FileUpload::make('image')
                            ->label('Gambar/Thumbnail')
                            ->image()
                            ->required()
                            ->disk('public')
                            ->directory('featured-works')
                            ->visibility('public')
                            ->imageEditor()
                            ->imageCropAspectRatio('16:9')
                            ->maxSize(2048)
                            ->helperText('Rasio 16:9 disarankan. Maks 2MB.')
                            ->columnSpanFull(),
                    ]),

                Section::make('Pengaturan')
                    ->schema([
                        TextInput::make('sort_order')
                            ->label('Urutan')
                            ->numeric()
                            ->default(0)
                            ->helperText('Angka kecil = tampil lebih dulu'),

                        Toggle::make('is_active')
                            ->label('Aktif')
                            ->default(true)
                            ->helperText('Tampilkan di website'),
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
                    ->disk('public')
                    ->size(60),

                TextColumn::make('title')
                    ->label('Judul')
                    ->searchable()
                    ->sortable()
                    ->limit(30),

                TextColumn::make('category')
                    ->label('Kategori')
                    ->badge()
                    ->color('info')
                    ->placeholder('-'),

                TextColumn::make('client')
                    ->label('Klien')
                    ->searchable()
                    ->placeholder('-'),

                TextColumn::make('project_date')
                    ->label('Tanggal')
                    ->date('M Y')
                    ->sortable()
                    ->placeholder('-'),

                TextColumn::make('sort_order')
                    ->label('Urutan')
                    ->sortable()
                    ->alignCenter(),

                IconColumn::make('is_active')
                    ->label('Aktif')
                    ->boolean(),

                TextColumn::make('created_at')
                    ->label('Dibuat')
                    ->dateTime('d M Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('sort_order')
            ->reorderable('sort_order')
            ->filters([
                TernaryFilter::make('is_active')
                    ->label('Aktif'),
            ])
            ->actions([
                Actions\ActionGroup::make([
                    Actions\Action::make('view_url')
                        ->label('Buka URL')
                        ->icon('heroicon-o-arrow-top-right-on-square')
                        ->url(fn ($record) => $record->url)
                        ->openUrlInNewTab()
                        ->visible(fn ($record) => !empty($record->url)),
                    Actions\EditAction::make(),
                    Actions\DeleteAction::make(),
                ]),
            ])
            ->bulkActions([
                Actions\BulkActionGroup::make([
                    Actions\DeleteBulkAction::make(),
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
            'index' => Pages\ListFeaturedWorks::route('/'),
            'create' => Pages\CreateFeaturedWork::route('/create'),
            'edit' => Pages\EditFeaturedWork::route('/{record}/edit'),
        ];
    }
}

<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\AuthorizesCertificateModule;
use App\Filament\Resources\CertificateTemplateResource\Pages;
use App\Models\CertificateTemplate;
use BackedEnum;
use Filament\Actions;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

class CertificateTemplateResource extends Resource
{
    use AuthorizesCertificateModule;

    protected static ?string $model = CertificateTemplate::class;
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-document-duplicate';
    protected static string|UnitEnum|null $navigationGroup = 'Sertifikat';
    protected static ?string $navigationLabel = 'Template Sertifikat';
    protected static ?string $recordTitleAttribute = 'name';


    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Kanvas Sertifikat')->description('Gunakan desain tanpa nama/nomor/QR sebagai gambar latar.')
                ->schema([
                    TextInput::make('name')->label('Nama template')->required()->maxLength(150),
                    FileUpload::make('background_path')->label('Desain latar')->image()->disk('public')->directory('certificates/templates')->required()->helperText('Disarankan PNG/JPG landscape 1123 × 794 px.'),
                    TextInput::make('canvas_width')->label('Lebar kanvas (px)')->numeric()->required()->default(1123),
                    TextInput::make('canvas_height')->label('Tinggi kanvas (px)')->numeric()->required()->default(794),
                    Toggle::make('is_active')->label('Template aktif')->default(true),
                ])->columns(2),

            Section::make('Font Kustom')->description('Unggah TTF jika diperlukan. Setelah menyimpan, Anda otomatis masuk ke editor visual untuk menambah dan mengatur elemen.')
                ->schema([FileUpload::make('fonts')->multiple()->disk('public')->directory('certificates/fonts')->acceptedFileTypes(['font/ttf', 'application/x-font-ttf'])->maxSize(5120)]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('name')->label('Template')->searchable()->sortable(),
            TextColumn::make('events_count')->label('Event')->counts('events'),
            TextColumn::make('canvas_width')->label('Ukuran')->formatStateUsing(fn ($record) => "{$record->canvas_width} × {$record->canvas_height}"),
            IconColumn::make('is_active')->label('Aktif')->boolean(),
        ])->actions([
            Actions\Action::make('editor')->label('Editor Visual')->icon('heroicon-o-cursor-arrow-rays')->url(fn ($record) => static::getUrl('editor', ['record' => $record])),
            Actions\EditAction::make(), Actions\DeleteAction::make(),
        ]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ListCertificateTemplates::route('/'), 'create' => Pages\CreateCertificateTemplate::route('/create'), 'edit' => Pages\EditCertificateTemplate::route('/{record}/edit'), 'editor' => Pages\EditCertificateLayout::route('/{record}/editor')];
    }
}

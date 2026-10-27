<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\AuthorizesCertificateModule;
use App\Filament\Resources\CertificateTemplateResource\Pages;
use App\Models\CertificateTemplate;
use App\Support\CertificateFont;
use App\Support\UploadLimit;
use BackedEnum;
use Closure;
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
use Illuminate\Http\UploadedFile;
use Illuminate\Support\HtmlString;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use UnitEnum;

class CertificateTemplateResource extends Resource
{
    /**
     * Ukuran yang wajar untuk kanvas 1123 × 794. Batas sesungguhnya tetap
     * mengikuti server bila PHP di sana lebih ketat daripada ini.
     */
    private const LATAR_DISARANKAN_KB = 4096;

    use AuthorizesCertificateModule;

    protected static ?string $model = CertificateTemplate::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-document-duplicate';

    protected static string|UnitEnum|null $navigationGroup = 'Sertifikat';

    protected static ?string $navigationLabel = 'Desain Sertifikat';

    protected static ?int $navigationSort = 20;

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Kanvas Sertifikat')->description('Gunakan desain tanpa nama/nomor/QR sebagai gambar latar.')
                ->schema([
                    TextInput::make('name')->label('Nama template')->required()->maxLength(150),
                    // Satu-satunya unggahan yang dulu tanpa batas, sehingga
                    // berkas yang ditolak PHP hanya menghasilkan pesan
                    // "failed to upload" tanpa menyebut sebab maupun angkanya.
                    FileUpload::make('background_path')
                        ->label('Desain latar')
                        ->image()
                        ->disk('public')
                        ->directory('certificates/templates')
                        ->required()
                        ->maxSize(UploadLimit::forField(self::LATAR_DISARANKAN_KB))
                        ->helperText(new HtmlString(
                            'Disarankan PNG/JPG landscape 1123 × 794 px, maksimal '
                            .e(UploadLimit::describe(UploadLimit::forField(self::LATAR_DISARANKAN_KB))).'.'
                            .'<br><span style="color:rgb(113 113 122);">Gambar ini ditanam ke dalam setiap PDF sertifikat, '
                            .'jadi berkas yang besar membuat seluruh sertifikatnya ikut berat. '
                            .'Kompres dulu bila ukurannya berlebih — hasilnya tidak akan terlihat berbeda.</span>'
                        )),
                    TextInput::make('canvas_width')->label('Lebar kanvas (px)')->numeric()->required()->default(1123),
                    TextInput::make('canvas_height')->label('Tinggi kanvas (px)')->numeric()->required()->default(794),
                    Toggle::make('is_active')->label('Template aktif')->default(true),
                ])->columns(2),

            Section::make('Font Kustom')->description('Opsional. Font yang diunggah muncul di pilihan Font pada editor visual dengan nama berkasnya. Setelah menyimpan, Anda otomatis masuk ke editor visual untuk menambah dan mengatur elemen.')
                ->schema([
                    FileUpload::make('fonts')
                        ->label('Berkas font')
                        ->multiple()
                        ->disk('public')
                        ->directory(CertificateFont::DIRECTORY)
                        ->acceptedFileTypes(CertificateFont::MIME_TYPES)
                        ->mimeTypeMap(CertificateFont::MIME_TYPE_MAP)
                        ->maxSize(UploadLimit::forField(CertificateFont::MAX_KB))
                        ->validationMessages(['mimetypes' => CertificateFont::WRONG_TYPE_MESSAGE])
                        ->getUploadedFileNameForStorageUsing(fn (TemporaryUploadedFile $file): string => CertificateFont::storedName($file->getClientOriginalName()))
                        // Isinya diperiksa sebelum disimpan: font yang tidak
                        // bisa ditanam baru ketahuan ketika PDF sertifikat
                        // ternyata memakai font cadangan.
                        ->rule(fn () => function (string $attribute, mixed $value, Closure $fail): void {
                            if ($value instanceof UploadedFile && ($masalah = CertificateFont::problem((string) $value->getRealPath(), $value->getClientOriginalName())) !== null) {
                                $fail($masalah);
                            }
                        })
                        ->helperText(fn (): HtmlString => self::fontHelp()),
                ]),
        ]);
    }

    private static function fontHelp(): HtmlString
    {
        $bantuan = 'Berkas .ttf (atau .otf berbasis TrueType), maksimal '
            .e(UploadLimit::describe(UploadLimit::forField(CertificateFont::MAX_KB))).' per berkas. '
            .'Font ikut ditanam ke setiap PDF sertifikat.';

        // Font tetap tampil di editor, tetapi PDF memakai font cadangan
        // tanpa tanda apa pun — jadi diberitahukan di sini.
        if (! CertificateFont::cacheIsWritable()) {
            $bantuan .= '<br><span style="color:rgb(220 38 38);">Folder storage/fonts di server tidak dapat ditulisi, sehingga PDF sertifikat memakai font cadangan. '
                .'Jalankan <code>bash deploy.sh</code> di server untuk memperbaikinya.</span>';
        }

        return new HtmlString($bantuan);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('name')->label('Template')->searchable()->sortable(),
            TextColumn::make('events_count')->label('Event')->counts('events'),
            TextColumn::make('canvas_width')->label('Ukuran')->formatStateUsing(fn ($record) => "{$record->canvas_width} × {$record->canvas_height}"),
            IconColumn::make('is_active')->label('Aktif')->boolean(),
        ])
            ->emptyStateHeading('Belum ada desain sertifikat')
            ->emptyStateDescription('Desain adalah gambar latar sertifikat beserta letak nama, nomor, dan QR di atasnya. Satu desain bisa dipakai berkali-kali oleh kegiatan yang berbeda.')
            ->emptyStateIcon('heroicon-o-photo')
            ->actions([
                // Menaruh elemen di atas desain adalah pekerjaan yang
                // sesungguhnya di sini, jadi tombolnya menonjol.
                Actions\Action::make('editor')
                    ->label('Atur Letak Tulisan')
                    ->icon('heroicon-o-cursor-arrow-rays')
                    ->color('primary')
                    ->button()
                    ->url(fn ($record) => static::getUrl('editor', ['record' => $record])),
                Actions\EditAction::make(), Actions\DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ListCertificateTemplates::route('/'), 'create' => Pages\CreateCertificateTemplate::route('/create'), 'edit' => Pages\EditCertificateTemplate::route('/{record}/edit'), 'editor' => Pages\EditCertificateLayout::route('/{record}/editor')];
    }
}

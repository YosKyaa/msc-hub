<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\AuthorizesByPermission;
use App\Filament\Resources\TwibbonResource\Pages;
use App\Models\Twibbon;
use App\Support\TwibbonFrame;
use App\Support\UploadLimit;
use BackedEnum;
use Closure;
use Filament\Actions;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;
use UnitEnum;

/**
 * Twibbon: admin mengunggah bingkai, publik memasangnya di atas fotonya.
 *
 * Halaman publiknya di /twibbon/{slug}. Foto peserta diolah di peramban dan
 * tidak pernah dikirim ke server.
 */
class TwibbonResource extends Resource
{
    use AuthorizesByPermission;

    protected static ?string $model = Twibbon::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-photo';

    protected static string|UnitEnum|null $navigationGroup = 'Konten';

    protected static ?string $navigationLabel = 'Twibbon';

    protected static ?string $modelLabel = 'Twibbon';

    protected static ?string $pluralModelLabel = 'Twibbon';

    protected static ?int $navigationSort = 40;

    /** Bingkai PNG beresolusi tinggi wajar sampai beberapa MB. */
    private const BINGKAI_DISARANKAN_KB = 5120;

    protected static function permissionPrefix(): string
    {
        return 'twibbons';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Bingkai')
                ->description('PNG dengan bagian transparan sebagai tempat foto peserta.')
                ->schema([
                    FileUpload::make('frame_path')
                        ->label('Berkas bingkai')
                        ->image()
                        ->disk('public')
                        ->directory('twibbons')
                        ->acceptedFileTypes(['image/png'])
                        ->maxSize(UploadLimit::forField(self::BINGKAI_DISARANKAN_KB))
                        ->required()
                        // Diperiksa sebelum disimpan: bingkai tanpa bagian
                        // transparan menutup foto peserta seluruhnya, dan baru
                        // ketahuan ketika ratusan peserta mencobanya.
                        ->rule(fn () => function (string $attribute, mixed $value, Closure $fail): void {
                            if ($value instanceof UploadedFile && ($masalah = TwibbonFrame::problem((string) $value->getRealPath())) !== null) {
                                $fail($masalah);
                            }
                        })
                        ->helperText(new HtmlString(
                            'PNG berlatar transparan, sisi terpendek minimal '.TwibbonFrame::MIN_SIDE.' px, rasio '
                            .e(implode(', ', array_keys(TwibbonFrame::RATIOS))).'. Bagian yang transparan adalah tempat foto peserta. '
                            .'Maksimal '.e(UploadLimit::describe(UploadLimit::forField(self::BINGKAI_DISARANKAN_KB))).'.'
                        )),
                ]),

            Section::make('Keterangan')
                ->columns(2)
                ->schema([
                    TextInput::make('title')
                        ->label('Judul')
                        ->required()
                        ->maxLength(150)
                        ->columnSpanFull()
                        ->live(onBlur: true)
                        ->afterStateUpdated(function (?string $state, Set $set, ?Twibbon $record): void {
                            if ($record === null) {
                                $set('slug', Str::slug((string) $state));
                            }
                        }),
                    // Awalannya dibuat pendek: domain lengkap di depan kolom
                    // menyisakan ruang ketik hanya beberapa huruf. Tautan
                    // utuhnya ditampilkan di bawah, mengikuti isian.
                    TextInput::make('slug')
                        ->label('Alamat')
                        ->prefix('/twibbon/')
                        ->placeholder('pkkmb-2026')
                        ->required()
                        ->maxLength(150)
                        ->alphaDash()
                        ->unique(ignoreRecord: true)
                        ->live(onBlur: true)
                        ->helperText(fn (Get $get) => new HtmlString(
                            'Tautan yang dibagikan: <strong>'.e(url('/twibbon/'.($get('slug') ?: '…'))).'</strong><br>'
                            .'Hanya huruf, angka, dan tanda hubung. Jangan diubah setelah tautannya tersebar.'
                        ))
                        ->columnSpanFull(),
                    Textarea::make('description')
                        ->label('Deskripsi singkat')
                        ->rows(2)
                        ->maxLength(500)
                        ->columnSpanFull(),
                    Textarea::make('caption')
                        ->label('Caption untuk dibagikan')
                        ->rows(4)
                        ->maxLength(2200)
                        ->helperText('Disalin peserta dengan satu tombol saat membagikan, lengkap dengan tagar. Batas Instagram 2.200 karakter.')
                        ->columnSpanFull(),
                ]),

            Section::make('Publikasi')
                ->columns(3)
                ->schema([
                    Toggle::make('is_published')
                        ->label('Terbitkan')
                        ->helperText('Selama belum diterbitkan, halamannya tidak bisa dibuka publik.'),
                    DateTimePicker::make('starts_at')
                        ->label('Dibuka mulai')
                        ->seconds(false)
                        ->native(false)
                        ->helperText('Kosongkan agar langsung dibuka.'),
                    DateTimePicker::make('ends_at')
                        ->label('Ditutup pada')
                        ->seconds(false)
                        ->native(false)
                        ->after('starts_at')
                        ->helperText('Kosongkan agar tidak pernah ditutup.'),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                ImageColumn::make('frame_path')->label('Bingkai')->disk('public')->square()->imageSize(64),

                TextColumn::make('title')
                    ->label('Judul')
                    ->description(fn (Twibbon $record) => $record->publicUrl())
                    ->searchable()
                    ->wrap(),

                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->state(fn (Twibbon $record) => match ($record->status()) {
                        Twibbon::STATUS_DRAFT => 'Draf',
                        Twibbon::STATUS_SCHEDULED => 'Terjadwal',
                        Twibbon::STATUS_ENDED => 'Sudah ditutup',
                        default => 'Dibuka',
                    })
                    ->color(fn (Twibbon $record) => match ($record->status()) {
                        Twibbon::STATUS_OPEN => 'success',
                        Twibbon::STATUS_SCHEDULED => 'info',
                        default => 'gray',
                    }),

                TextColumn::make('frame_size')
                    ->label('Ukuran')
                    ->state(fn (Twibbon $record) => $record->frame_width.' × '.$record->frame_height)
                    ->description(fn (Twibbon $record) => $record->transparent_ratio !== null
                        ? TwibbonFrame::percent((float) $record->transparent_ratio).' transparan'
                        : null)
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('uses_count')->label('Dipakai')->numeric(thousandsSeparator: '.')->sortable(),

                TextColumn::make('ends_at')->label('Ditutup')->dateTime('d M Y, H:i')->placeholder('—')->sortable(),
            ])
            ->actions([
                Actions\Action::make('open')
                    ->iconButton()
                    ->tooltip('Buka halaman publik')
                    ->icon('heroicon-o-arrow-top-right-on-square')
                    ->url(fn (Twibbon $record) => $record->publicUrl())
                    ->openUrlInNewTab(),
                Actions\EditAction::make()->iconButton()->tooltip('Ubah'),
                Actions\DeleteAction::make()->iconButton()->tooltip('Hapus'),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTwibbons::route('/'),
            'create' => Pages\CreateTwibbon::route('/create'),
            'edit' => Pages\EditTwibbon::route('/{record}/edit'),
        ];
    }
}

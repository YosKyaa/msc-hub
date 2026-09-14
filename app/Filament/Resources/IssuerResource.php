<?php

namespace App\Filament\Resources;

use App\Enums\CertificateNumberReset;
use App\Filament\Concerns\AuthorizesCertificateModule;
use App\Filament\Resources\IssuerResource\Pages;
use App\Models\Issuer;
use App\Services\Certificates\CertificateNumberCounter;
use App\Services\Certificates\CertificateNumberException;
use App\Services\Certificates\CertificateNumberFormat;
use BackedEnum;
use Filament\Actions;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

/**
 * Penerbit sertifikat — MSC JGU sendiri maupun mitra di luar kampus.
 */
class IssuerResource extends Resource
{
    use AuthorizesCertificateModule;

    protected static ?string $model = Issuer::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-building-library';

    protected static string|UnitEnum|null $navigationGroup = 'Sertifikat';

    protected static ?string $navigationLabel = 'Penerbit';

    protected static ?int $navigationSort = 80;

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Identitas Penerbit')
                ->description('Dipakai pada halaman verifikasi publik dan kop email sertifikat.')
                ->schema([
                    TextInput::make('name')
                        ->label('Nama penerbit')
                        ->required()
                        ->maxLength(190)
                        ->columnSpanFull(),

                    TextInput::make('code')
                        ->label('Kode penerbit')
                        ->required()
                        ->maxLength(40)
                        ->unique(ignoreRecord: true)
                        ->helperText('Mengisi token {kode_unit} pada nomor sertifikat, contoh LPPI.'),

                    FileUpload::make('logo_path')
                        ->label('Logo')
                        ->image()
                        ->disk('public')
                        ->directory('issuers')
                        ->maxSize(1024)
                        ->helperText('Kosongkan untuk memakai logo JGU.'),

                    TextInput::make('address_line')
                        ->label('Baris alamat')
                        ->maxLength(190)
                        ->columnSpanFull(),

                    TextInput::make('verification_note')
                        ->label('Kalimat pada halaman verifikasi')
                        ->maxLength(255)
                        ->placeholder(fn (?Issuer $record) => $record?->verificationStatement())
                        ->helperText('Kosongkan untuk memakai kalimat bawaan. Untuk penerbit di luar JGU, peran MSC disebut sebagai fasilitator.')
                        ->columnSpanFull(),
                ])
                ->columns(2),

            Section::make('Penomoran')
                ->description('Tiap penerbit memegang urutan nomornya sendiri, jadi nomor Rektorat, SCD, jurusan, dan MSC tidak pernah saling menggerus.')
                ->schema([
                    TextInput::make('number_pattern')
                        ->label('Pola nomor penerbit ini')
                        ->maxLength(190)
                        ->placeholder(CertificateNumberFormat::default()->pattern)
                        ->helperText('Kosongkan untuk mengikuti pola default sistem.')
                        ->rules([
                            fn (): \Closure => function (string $attribute, $value, \Closure $fail) {
                                if (filled($value) && ! str_contains((string) $value, '{nomor')) {
                                    $fail('Pola wajib memuat token {nomor} agar setiap sertifikat memperoleh nomor urut berbeda.');
                                }
                            },
                        ])
                        ->columnSpanFull(),

                    Select::make('number_reset')
                        ->label('Nomor urut diulang')
                        ->options(CertificateNumberReset::options())
                        ->placeholder('Ikuti pengaturan default sistem')
                        ->helperText(fn ($state) => CertificateNumberReset::tryFrom((string) $state)?->getDescription()),

                    TextInput::make('number_start')
                        ->label('Mulai dari nomor')
                        ->numeric()
                        ->minValue(1)
                        ->default(1)
                        ->required()
                        ->helperText('Nomor pertama setiap kali urutan dimulai ulang. Isi bila unit ini sudah memegang register sendiri, misalnya mulai dari 120.'),

                    Toggle::make('is_active')
                        ->label('Aktif')
                        ->default(true)
                        ->helperText('Penerbit nonaktif tidak dapat dipilih pada kegiatan baru.'),
                ])
                ->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Penerbit')
                    ->description(fn (Issuer $record) => $record->address_line)
                    ->searchable()
                    ->sortable(),
                TextColumn::make('code')->label('Kode')->badge()->searchable(),
                TextColumn::make('events_count')->label('Kegiatan')->counts('events'),
                TextColumn::make('number_pattern')->label('Pola nomor')->placeholder('Default sistem')->toggleable(),

                // Pertanyaan pertama tiap unit penerbit adalah "kita sudah
                // sampai nomor berapa?". Angkanya dulu hanya hidup di dalam
                // tabel dan tidak pernah terlihat.
                TextColumn::make('nomor_berikutnya')
                    ->label('Nomor berikutnya')
                    ->badge()
                    ->color('info')
                    ->state(function (Issuer $record): string {
                        $counter = app(CertificateNumberCounter::class);

                        if ($counter->currentScope($record) === null) {
                            return 'Per kegiatan';
                        }

                        return (string) $counter->nextNumber($record);
                    })
                    ->description(function (Issuer $record): ?string {
                        $last = app(CertificateNumberCounter::class)->lastNumber($record);

                        return $last === null ? 'Belum ada yang terbit' : 'Terakhir terpakai: '.$last;
                    }),
                IconColumn::make('is_house')->label('Penerbit rumah')->boolean()->alignCenter(),
                IconColumn::make('is_active')->label('Aktif')->boolean()->alignCenter(),
            ])
            ->defaultSort('is_house', 'desc')
            ->actions([
                // Register kertas sering sudah berjalan lebih dulu; ini cara
                // menyelaraskan sistem dengan nomor yang sudah terpakai di sana.
                Actions\Action::make('setNextNumber')
                    ->iconButton()
                    ->tooltip('Setel nomor berikutnya')
                    ->icon('heroicon-o-hashtag')
                    ->color('info')
                    ->modalHeading('Setel Nomor Berikutnya')
                    ->modalDescription('Pakai ini bila unit penerbit sudah memegang register sendiri. Sertifikat berikutnya akan memakai nomor yang Anda sebut.')
                    ->modalSubmitActionLabel('Simpan')
                    ->fillForm(fn (Issuer $record) => [
                        'next' => app(CertificateNumberCounter::class)->currentScope($record) === null
                            ? $record->startingNumber()
                            : app(CertificateNumberCounter::class)->nextNumber($record),
                    ])
                    ->schema([
                        TextInput::make('next')
                            ->label('Nomor berikutnya')
                            ->numeric()
                            ->minValue(1)
                            ->required()
                            ->helperText('Sertifikat yang diterbitkan setelah ini akan memakai nomor tersebut.'),
                    ])
                    ->visible(fn (Issuer $record) => app(CertificateNumberCounter::class)->currentScope($record) !== null)
                    ->action(function (Issuer $record, array $data): void {
                        try {
                            app(CertificateNumberCounter::class)->setNextNumber($record, (int) $data['next']);
                        } catch (CertificateNumberException $exception) {
                            Notification::make()->title('Tidak dapat disetel')->body($exception->getMessage())->warning()->send();

                            return;
                        }

                        Notification::make()
                            ->title('Nomor berikutnya disetel ke '.$data['next'])
                            ->success()
                            ->send();
                    }),

                Actions\EditAction::make()->iconButton()->tooltip('Ubah penerbit'),
                Actions\DeleteAction::make()
                    ->iconButton()
                    ->tooltip('Hapus penerbit')
                    // Penerbit rumah menjadi cadangan bagi kegiatan tanpa penerbit;
                    // menghapusnya membuat halaman verifikasi kehilangan identitas.
                    ->visible(fn (Issuer $record) => ! $record->is_house),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListIssuers::route('/'),
            'create' => Pages\CreateIssuer::route('/create'),
            'edit' => Pages\EditIssuer::route('/{record}/edit'),
        ];
    }
}

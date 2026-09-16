<?php

namespace App\Filament\Resources;

use App\Enums\AttendanceAction;
use App\Enums\EligibilityRule;
use App\Filament\Concerns\AuthorizesCertificateModule;
use App\Filament\Resources\CertificateEventResource\Pages;
use App\Filament\Resources\CertificateEventResource\RelationManagers\ParticipationsRelationManager;
use App\Models\CertificateEvent;
use App\Models\Issuer;
use App\Services\Certificates\CertificateNumberFormat;
use App\Support\CertificatePermission;
use BackedEnum;
use Filament\Actions;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Actions as FormActions;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;
use UnitEnum;

class CertificateEventResource extends Resource
{
    use AuthorizesCertificateModule;

    protected static ?string $model = CertificateEvent::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-academic-cap';

    protected static string|UnitEnum|null $navigationGroup = 'Sertifikat';

    protected static ?string $navigationLabel = 'Manajemen Sertifikat';

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Tabs::make()->columnSpanFull()->tabs([
                Tab::make('Kegiatan')
                    ->icon('heroicon-o-information-circle')
                    ->schema([
                        Section::make('Informasi Kegiatan')->schema([
                            TextInput::make('name')
                                ->label('Nama kegiatan')
                                ->required()
                                ->live(onBlur: true)
                                ->afterStateUpdated(fn ($state, $set, $record) => $record ?: $set('slug', Str::slug($state)))
                                ->columnSpanFull(),
                            TextInput::make('slug')->required()->unique(ignoreRecord: true),
                            DatePicker::make('event_date')->label('Tanggal kegiatan')->required()->native(false),
                            Select::make('certificate_template_id')
                                ->label('Template')
                                ->relationship('template', 'name', fn ($query) => $query->where('is_active', true))
                                ->required()
                                ->searchable()
                                ->preload(),
                            Select::make('issuer_id')
                                ->label('Penerbit')
                                ->relationship('issuer', 'name', fn ($query) => $query->where('is_active', true))
                                ->searchable()
                                ->preload()
                                ->live()
                                ->default(fn () => Issuer::house()?->id)
                                ->helperText('Menentukan brand halaman verifikasi, kop email, dan urutan nomor.'),
                            TextInput::make('organizer')
                                ->label('Penyelenggara yang dicetak')
                                ->helperText('Muncul pada sertifikat lewat variabel {organizer}. Kosongkan untuk memakai nama penerbit.'),
                        ])->columns(2),

                        Section::make('Penomoran Sertifikat')
                            ->description('Nomor dibuat otomatis dari pola yang berlaku. Nomor per peserta tetap dapat diisi manual lewat tab peserta atau kolom nomor_sertifikat pada file import.')
                            ->schema([
                                TextInput::make('certificate_code')
                                    ->label('Kode kegiatan')
                                    ->maxLength(40)
                                    ->live(onBlur: true)
                                    ->placeholder('Contoh: ESSENTIAL')
                                    ->helperText('Mengisi token {kode_kegiatan} pada pola nomor.'),

                                TextInput::make('certificate_number_format')
                                    ->label('Pola khusus kegiatan ini')
                                    ->maxLength(190)
                                    ->live(onBlur: true)
                                    ->placeholder(CertificateNumberFormat::default()->pattern)
                                    ->helperText('Kosongkan untuk mengikuti pola default kampus.')
                                    ->rules([
                                        fn (): \Closure => function (string $attribute, $value, \Closure $fail) {
                                            if (filled($value) && ! str_contains((string) $value, '{nomor')) {
                                                $fail('Pola wajib memuat token {nomor} agar setiap sertifikat memperoleh nomor urut berbeda.');
                                            }
                                        },
                                    ]),

                                Text::make(fn (Get $get, ?CertificateEvent $record) => static::numberPreview($get, $record))
                                    ->columnSpanFull(),
                            ])
                            ->columns(2),

                        Section::make('Penandatangan & Publikasi')->schema([
                            TextInput::make('signatory_name')->label('Nama penandatangan'),
                            TextInput::make('signatory_title')->label('Jabatan'),
                            Select::make('status')
                                ->label('Status kegiatan')
                                ->options(['draft' => 'Draft', 'published' => 'Dipublikasikan', 'archived' => 'Diarsipkan'])
                                ->default('draft')
                                ->required()
                                ->helperText('Sertifikat baru sah dan emailnya terkirim setelah status Dipublikasikan.')
                                ->columnSpanFull(),

                            // Membuka daftar nama orang ke publik adalah
                            // keputusan yang harus diambil sadar, jadi
                            // akibatnya ditulis apa adanya di sini.
                            Toggle::make('recipients_public')
                                ->label('Buka daftar penerima untuk umum')
                                ->helperText(fn (?CertificateEvent $record) => new HtmlString(
                                    'Siapa pun tanpa perlu masuk dapat melihat <strong>nama, peran, dan nomor sertifikat</strong> '
                                    .'seluruh penerima, serta membuka halaman verifikasi masing-masing. '
                                    .'Alamat email tidak pernah ditampilkan. Berlaku setelah kegiatan berstatus Dipublikasikan.'
                                    .($record?->exists
                                        ? '<br><span style="color:rgb(113 113 122);">Tautannya: <code>'.e($record->publicRecipientsUrl()).'</code></span>'
                                        : '')
                                ))
                                ->columnSpanFull(),
                        ])->columns(2),
                    ]),

                Tab::make('Absensi & QR')
                    ->icon('heroicon-o-qr-code')
                    ->badge(fn (?CertificateEvent $record) => $record?->attendance_enabled ? 'Aktif' : null)
                    ->badgeColor('success')
                    ->schema([
                        Section::make()
                            ->description('Check-in dan check-out memakai QR terpisah. Buka window check-out mendekati akhir acara agar peserta tidak menutup kehadiran sesaat setelah membukanya.')
                            ->schema([
                                Toggle::make('attendance_enabled')
                                    ->label('Aktifkan absensi untuk kegiatan ini')
                                    ->helperText('Dua tautan dan dua QR dibuat otomatis saat pertama kali diaktifkan, lalu tidak pernah berubah.')
                                    ->live()
                                    ->columnSpanFull(),

                                Select::make('eligibility_rule')
                                    ->label('Aturan kelayakan sertifikat')
                                    ->options(EligibilityRule::options())
                                    ->default(EligibilityRule::MANUAL->value)
                                    ->required()
                                    ->helperText(fn ($state) => (EligibilityRule::tryFrom((string) $state) ?? EligibilityRule::MANUAL)->getDescription())
                                    ->live()
                                    ->visible(fn (Get $get) => (bool) $get('attendance_enabled'))
                                    ->columnSpanFull(),
                            ]),

                        // Berdampingan supaya kedua window mudah dibandingkan sekilas.
                        Grid::make(['default' => 1, 'lg' => 2])
                            ->visible(fn (Get $get) => (bool) $get('attendance_enabled'))
                            ->schema([
                                static::attendanceCard(AttendanceAction::CHECK_IN),
                                static::attendanceCard(AttendanceAction::CHECK_OUT),
                            ]),
                    ]),
            ]),
        ]);
    }

    /**
     * Satu kartu pengaturan per aksi absensi: window waktu, tautan, dan QR.
     */
    /**
     * Contoh nomor menurut pola yang sedang diisi, supaya admin tidak perlu
     * menerbitkan sertifikat hanya untuk memeriksa hasilnya.
     */
    protected static function numberPreview(Get $get, ?CertificateEvent $record): HtmlString
    {
        $default = CertificateNumberFormat::default();
        $issuer = Issuer::find($get('issuer_id')) ?? Issuer::house();

        $pattern = (string) ($get('certificate_number_format')
            ?: ($issuer?->number_pattern ?: $default->pattern));

        $preview = (new CertificateNumberFormat(
            $pattern,
            $issuer?->number_reset ?? $default->reset,
            $issuer?->code ?: $default->unitCode,
        ))
            ->preview(new CertificateEvent([
                'name' => $get('name') ?: ($record?->name ?? 'Contoh Kegiatan'),
                'certificate_code' => $get('certificate_code'),
            ]));

        return new HtmlString(
            '<div class="rounded-lg bg-gray-50 px-4 py-3 dark:bg-white/5">'
            .'<p class="text-xs uppercase tracking-wide text-gray-500">Contoh nomor</p>'
            .'<p class="mt-1 font-mono text-base font-semibold text-gray-950 dark:text-white">'
            .e($preview).'</p></div>'
        );
    }

    protected static function attendanceCard(AttendanceAction $action): Section
    {
        $label = $action->getLabel();
        $isCheckIn = $action === AttendanceAction::CHECK_IN;
        $hasToken = fn (?CertificateEvent $record) => (bool) $record?->attendanceToken($action);

        return Section::make($label)
            ->icon($isCheckIn ? 'heroicon-o-arrow-right-on-rectangle' : 'heroicon-o-arrow-left-on-rectangle')
            ->iconColor($isCheckIn ? 'primary' : 'success')
            ->description($isCheckIn
                ? 'Dipindai saat peserta tiba di lokasi.'
                : 'Dipindai saat acara selesai. Buka menjelang acara bubar.')
            ->schema([
                DateTimePicker::make($action->openColumn())
                    ->label('Dibuka')
                    ->native(false)
                    ->seconds(false)
                    ->helperText('Kosongkan bila tidak ada batas awal.'),

                DateTimePicker::make($action->closeColumn())
                    ->label('Ditutup')
                    ->native(false)
                    ->seconds(false)
                    ->after($action->openColumn())
                    ->validationMessages(['after' => "Waktu tutup {$label} harus setelah waktu bukanya."])
                    ->helperText('Kosongkan bila tidak ada batas akhir.'),

                TextEntry::make($action->value.'_url')
                    ->label('Tautan peserta')
                    ->state(fn (?CertificateEvent $record) => $record?->attendanceUrl($action))
                    ->copyable()
                    ->copyMessage("Tautan {$label} disalin.")
                    ->visible($hasToken)
                    ->columnSpanFull(),

                FormActions::make([
                    Actions\Action::make('poster'.ucfirst($action->value))
                        ->label("Tampilkan QR {$label}")
                        ->icon('heroicon-o-qr-code')
                        ->color($isCheckIn ? 'primary' : 'success')
                        ->url(fn (?CertificateEvent $record) => $record
                            ? route('attendance.poster', ['event' => $record, 'action' => $action->value])
                            : null)
                        ->openUrlInNewTab(),
                ])
                    ->visible($hasToken)
                    ->columnSpanFull(),
            ])
            ->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('name')->label('Kegiatan')->searchable()->sortable(),
            TextColumn::make('template.name')->label('Template')->toggleable(),
            TextColumn::make('issuer.name')->label('Penerbit')->placeholder('MSC JGU')->toggleable(),
            TextColumn::make('event_date')->label('Tanggal')->date('d M Y')->sortable(),
            TextColumn::make('participations_count')->label('Terdaftar')->counts('participations'),
            TextColumn::make('certificates_count')->label('Penerima')->counts('certificates'),
            IconColumn::make('attendance_enabled')->label('Absensi')->boolean(),
            IconColumn::make('recipients_public')
                ->label('Daftar publik')
                ->boolean()
                ->tooltip(fn (CertificateEvent $record) => $record->recipientsArePublic()
                    ? 'Daftar penerima terbuka untuk umum'
                    : 'Daftar penerima tertutup'),
            TextColumn::make('status')->badge()->color(fn ($state) => match ($state) {
                'published' => 'success','archived' => 'gray',default => 'warning'
            }),
        ])->actions([
            Actions\ActionGroup::make(
                array_map(fn (AttendanceAction $action) => Actions\Action::make('poster'.ucfirst($action->value))
                    ->label('QR '.$action->getLabel())
                    ->icon('heroicon-o-qr-code')
                    ->url(fn (CertificateEvent $record) => route('attendance.poster', ['event' => $record, 'action' => $action->value]))
                    ->openUrlInNewTab()
                    ->visible(fn (CertificateEvent $record) => (bool) $record->attendanceToken($action)),
                    AttendanceAction::cases(),
                ),
            )->label('QR Absensi')->icon('heroicon-o-qr-code')->button(),
            // Buka-tutup tanpa perlu membuka formulirnya: keputusan ini
            // sering diambil mendadak, sebelum atau sesudah acara.
            Actions\Action::make('toggleRecipients')
                ->label(fn (CertificateEvent $record) => $record->recipients_public ? 'Tutup Daftar' : 'Buka Daftar')
                ->icon(fn (CertificateEvent $record) => $record->recipients_public ? 'heroicon-o-lock-closed' : 'heroicon-o-globe-alt')
                ->color(fn (CertificateEvent $record) => $record->recipients_public ? 'gray' : 'info')
                ->button()
                ->requiresConfirmation()
                ->modalHeading(fn (CertificateEvent $record) => $record->recipients_public
                    ? 'Tutup Daftar Penerima'
                    : 'Buka Daftar Penerima untuk Umum')
                ->modalDescription(fn (CertificateEvent $record) => $record->recipients_public
                    ? 'Daftarnya tidak lagi bisa dibuka siapa pun, dan tautannya menjadi tidak ditemukan.'
                    : 'Siapa pun tanpa perlu masuk dapat melihat nama, peran, dan nomor sertifikat seluruh penerima, '
                        .'serta membuka halaman verifikasi masing-masing. Alamat email tidak pernah ditampilkan.')
                ->modalSubmitActionLabel(fn (CertificateEvent $record) => $record->recipients_public ? 'Ya, Tutup' : 'Ya, Buka')
                ->visible(fn () => CertificatePermission::allowsIssuing())
                ->action(function (CertificateEvent $record): void {
                    $record->update(['recipients_public' => ! $record->recipients_public]);

                    Notification::make()
                        ->title($record->recipients_public ? 'Daftar penerima dibuka' : 'Daftar penerima ditutup')
                        ->body($record->recipients_public
                            ? 'Tautannya: '.$record->publicRecipientsUrl()
                            : 'Daftarnya tidak lagi dapat dibuka siapa pun.')
                        ->success()
                        ->send();
                }),

            Actions\Action::make('openRecipients')
                ->label('Lihat Daftar')
                ->icon('heroicon-o-arrow-top-right-on-square')
                ->url(fn (CertificateEvent $record) => $record->publicRecipientsUrl())
                ->openUrlInNewTab()
                ->visible(fn (CertificateEvent $record) => $record->recipientsArePublic()),

            Actions\EditAction::make(),
            Actions\DeleteAction::make(),
        ]);
    }

    public static function getRelations(): array
    {
        // Satu tabel saja: peserta dan sertifikatnya adalah satu perjalanan,
        // dan memisahkannya membuat orang yang sama muncul dua kali.
        return [ParticipationsRelationManager::class];
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ListCertificateEvents::route('/'), 'create' => Pages\CreateCertificateEvent::route('/create'), 'edit' => Pages\EditCertificateEvent::route('/{record}/edit')];
    }
}

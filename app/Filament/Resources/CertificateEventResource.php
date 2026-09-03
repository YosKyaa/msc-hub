<?php

namespace App\Filament\Resources;

use App\Enums\AttendanceAction;
use App\Enums\EligibilityRule;
use App\Filament\Concerns\AuthorizesCertificateModule;
use App\Filament\Resources\CertificateEventResource\Pages;
use App\Filament\Resources\CertificateEventResource\RelationManagers\CertificatesRelationManager;
use App\Filament\Resources\CertificateEventResource\RelationManagers\ParticipationsRelationManager;
use App\Models\CertificateEvent;
use BackedEnum;
use Filament\Actions;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Actions as FormActions;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
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
                            TextInput::make('organizer')->label('Penyelenggara'),
                        ])->columns(2),

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
            TextColumn::make('template.name')->label('Template'),
            TextColumn::make('event_date')->label('Tanggal')->date('d M Y')->sortable(),
            TextColumn::make('participations_count')->label('Terdaftar')->counts('participations'),
            TextColumn::make('certificates_count')->label('Penerima')->counts('certificates'),
            IconColumn::make('attendance_enabled')->label('Absensi')->boolean(),
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
            Actions\EditAction::make(),
            Actions\DeleteAction::make(),
        ]);
    }

    public static function getRelations(): array
    {
        return [ParticipationsRelationManager::class, CertificatesRelationManager::class];
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ListCertificateEvents::route('/'), 'create' => Pages\CreateCertificateEvent::route('/create'), 'edit' => Pages\EditCertificateEvent::route('/{record}/edit')];
    }
}

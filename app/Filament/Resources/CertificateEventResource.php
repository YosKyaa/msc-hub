<?php

namespace App\Filament\Resources;

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
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
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
            Section::make('Informasi Kegiatan')->schema([
                TextInput::make('name')->label('Nama kegiatan')->required()->live(onBlur: true)->afterStateUpdated(fn ($state, $set, $record) => $record ?: $set('slug', Str::slug($state))),
                TextInput::make('slug')->required()->unique(ignoreRecord: true),
                Select::make('certificate_template_id')->label('Template')->relationship('template', 'name', fn ($query) => $query->where('is_active', true))->required()->searchable()->preload(),
                DatePicker::make('event_date')->label('Tanggal kegiatan')->required()->native(false),
                TextInput::make('organizer')->label('Penyelenggara'),
                Select::make('status')->options(['draft' => 'Draft', 'published' => 'Dipublikasikan', 'archived' => 'Diarsipkan'])->default('draft')->required()->helperText('QR dinyatakan valid hanya saat status Dipublikasikan.'),
            ])->columns(2),

            Section::make('Penandatangan')->schema([
                TextInput::make('signatory_name')->label('Nama penandatangan'),
                TextInput::make('signatory_title')->label('Jabatan'),
            ])->columns(2),

            Section::make('Absensi Peserta')
                ->description('Peserta mencatat kehadiran lewat QR statis, login Google JGU, dalam rentang waktu yang Anda tentukan.')
                ->schema([
                    Toggle::make('attendance_enabled')
                        ->label('Aktifkan absensi untuk kegiatan ini')
                        ->helperText('Tautan dan QR dibuat otomatis saat pertama kali diaktifkan, lalu tidak berubah.')
                        ->live()
                        ->columnSpanFull(),

                    DateTimePicker::make('attendance_open_at')
                        ->label('Absensi dibuka')
                        ->native(false)
                        ->seconds(false)
                        ->helperText('Kosongkan bila tidak ada batas awal.')
                        ->visible(fn (Get $get) => (bool) $get('attendance_enabled')),

                    DateTimePicker::make('attendance_close_at')
                        ->label('Absensi ditutup')
                        ->native(false)
                        ->seconds(false)
                        ->after('attendance_open_at')
                        ->validationMessages(['after' => 'Waktu tutup harus setelah waktu buka.'])
                        ->helperText('Kosongkan bila tidak ada batas akhir.')
                        ->visible(fn (Get $get) => (bool) $get('attendance_enabled')),

                    Select::make('eligibility_rule')
                        ->label('Aturan kelayakan sertifikat')
                        ->options(EligibilityRule::options())
                        ->default(EligibilityRule::MANUAL->value)
                        ->required()
                        ->helperText(fn ($state) => (EligibilityRule::tryFrom((string) $state) ?? EligibilityRule::MANUAL)->getDescription())
                        ->live()
                        ->columnSpanFull(),

                    TextInput::make('attendance_url')
                        ->label('Tautan absensi')
                        ->disabled()
                        ->dehydrated(false)
                        ->formatStateUsing(fn (?CertificateEvent $record) => $record?->attendanceUrl())
                        ->helperText('Bagikan tautan ini atau cetak QR-nya untuk ditempel di lokasi kegiatan.')
                        ->suffixAction(
                            Actions\Action::make('openPoster')
                                ->label('Tampilkan QR layar penuh')
                                ->icon('heroicon-o-qr-code')
                                ->url(fn (?CertificateEvent $record) => $record ? route('attendance.poster', $record) : null)
                                ->openUrlInNewTab()
                                ->visible(fn (?CertificateEvent $record) => (bool) $record?->attendance_token),
                        )
                        ->visible(fn (?CertificateEvent $record) => (bool) $record?->attendance_token)
                        ->columnSpanFull(),
                ])->columns(2),
        ]);
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
            TextColumn::make('status')->badge()->color(fn ($state) => match($state) {'published'=>'success','archived'=>'gray',default=>'warning'}),
        ])->actions([
            Actions\Action::make('poster')
                ->label('QR Absensi')
                ->icon('heroicon-o-qr-code')
                ->url(fn (CertificateEvent $record) => route('attendance.poster', $record))
                ->openUrlInNewTab()
                ->visible(fn (CertificateEvent $record) => $record->attendance_enabled && $record->attendance_token),
            Actions\EditAction::make(),
            Actions\DeleteAction::make(),
        ]);
    }

    public static function getRelations(): array { return [ParticipationsRelationManager::class, CertificatesRelationManager::class]; }
    public static function getPages(): array { return ['index'=>Pages\ListCertificateEvents::route('/'),'create'=>Pages\CreateCertificateEvent::route('/create'),'edit'=>Pages\EditCertificateEvent::route('/{record}/edit')]; }
}

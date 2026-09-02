<?php

namespace App\Filament\Resources\CertificateEventResource\RelationManagers;

use App\Enums\ParticipantRole;
use App\Enums\ParticipantSource;
use App\Filament\Actions\ImportParticipantsAction;
use App\Models\CertificateEvent;
use App\Models\CertificateEventParticipant;
use App\Services\Certificates\ParticipantRegistry;
use App\Support\CertificatePermission;
use Filament\Actions;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ParticipationsRelationManager extends RelationManager
{
    protected static string $relationship = 'participations';

    protected static ?string $title = 'Peserta, Panitia & Pengisi Acara';

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('role')
                ->label('Peran')
                ->options(ParticipantRole::options())
                ->default(ParticipantRole::PARTICIPANT->value)
                ->required(),
            TextInput::make('role_label')
                ->label('Label khusus')
                ->helperText('Contoh: Ketua Pelaksana, Koordinator Acara.'),
            Select::make('attendance_status')
                ->label('Kehadiran')
                ->options([
                    'registered' => 'Terdaftar', 'approved' => 'Disetujui', 'attended' => 'Hadir',
                    'absent' => 'Tidak hadir', 'cancelled' => 'Dibatalkan',
                ])
                ->default('registered')
                ->required(),
            DateTimePicker::make('eligible_at')
                ->label('Berhak mendapat sertifikat')
                ->seconds(false)
                ->native(false)
                ->helperText('Isi ketika orang ini berhak menerima sertifikat.'),
            TextInput::make('certificate_number')
                ->label('Nomor sertifikat khusus')
                ->helperText('Kosongkan agar nomor dibuat otomatis saat penerbitan.'),
            KeyValue::make('variables')->label('Variabel tambahan')->columnSpanFull(),
        ])->columns(2);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('id')
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['participant', 'certificate']))
            ->columns([
                TextColumn::make('participant.name')->label('Nama')->searchable()->sortable(),
                TextColumn::make('participant.email')->label('Email')->searchable()->toggleable(),
                TextColumn::make('participant.type')->label('Tipe')->badge()->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('role')->label('Peran')->badge()->formatStateUsing(fn (CertificateEventParticipant $record) => $record->resolvedRoleLabel()),
                TextColumn::make('source')
                    ->label('Asal')
                    ->badge()
                    ->formatStateUsing(fn (?ParticipantSource $state) => $state?->getLabel() ?? '—')
                    ->color(fn (?ParticipantSource $state) => $state?->getColor() ?? 'gray'),
                TextColumn::make('checked_in_at')->label('Check-in')->dateTime('d M H:i')->placeholder('—')->sortable(),
                TextColumn::make('checked_out_at')->label('Check-out')->dateTime('d M H:i')->placeholder('—')->sortable(),
                IconColumn::make('eligible')->label('Eligible')->state(fn (CertificateEventParticipant $record) => $record->isEligible())->boolean(),
                IconColumn::make('issued')->label('Sertifikat')->state(fn (CertificateEventParticipant $record) => $record->certificate !== null)->boolean(),
            ])
            ->filters([
                SelectFilter::make('source')->label('Asal pendaftaran')->options(ParticipantSource::options()),
                SelectFilter::make('role')->label('Peran')->options(ParticipantRole::options()),
                TernaryFilter::make('eligible')
                    ->label('Kelayakan')
                    ->placeholder('Semua')
                    ->trueLabel('Sudah eligible')
                    ->falseLabel('Belum eligible')
                    ->queries(
                        true: fn (Builder $query) => $query->whereNotNull('eligible_at'),
                        false: fn (Builder $query) => $query->whereNull('eligible_at'),
                    ),
            ])
            ->headerActions([
                Actions\Action::make('addParticipant')
                    ->label('Tambah Peserta')
                    ->icon('heroicon-o-user-plus')
                    ->visible(fn () => CertificatePermission::allows('create'))
                    ->schema([
                        TextInput::make('email')
                            ->label('Email')
                            ->email()
                            ->required()
                            ->helperText('Dipakai sebagai kunci pencarian; peserta yang sudah ada akan dipakai ulang.'),
                        TextInput::make('name')->label('Nama yang dicetak')->required(),
                        Select::make('role')->label('Peran')->options(ParticipantRole::options())->default(ParticipantRole::PARTICIPANT->value)->required(),
                        TextInput::make('role_label')->label('Label khusus'),
                        TextInput::make('institutional_id')->label('NIM/NIP'),
                    ])
                    ->action(fn (array $data) => $this->addParticipantManually($data)),

                ImportParticipantsAction::make(),
            ])
            ->actions([
                Actions\Action::make('toggleEligible')
                    ->label(fn (CertificateEventParticipant $record) => $record->isEligible() ? 'Batalkan Eligible' : 'Jadikan Eligible')
                    ->icon(fn (CertificateEventParticipant $record) => $record->isEligible() ? 'heroicon-o-x-circle' : 'heroicon-o-check-circle')
                    ->color(fn (CertificateEventParticipant $record) => $record->isEligible() ? 'gray' : 'success')
                    ->visible(fn () => CertificatePermission::allows('edit'))
                    ->action(fn (CertificateEventParticipant $record) => $record->update(
                        $record->isEligible()
                            ? ['eligible_at' => null]
                            : ['eligible_at' => now(), 'attendance_status' => 'attended'],
                    )),
                Actions\Action::make('correctName')
                    ->label('Koreksi Nama')
                    ->icon('heroicon-o-pencil-square')
                    ->visible(fn () => CertificatePermission::allows('edit'))
                    ->fillForm(fn (CertificateEventParticipant $record) => [
                        'name' => $record->participant?->name,
                        'google_display_name' => $record->participant?->google_display_name,
                    ])
                    ->schema([
                        TextInput::make('name')->label('Nama yang dicetak di sertifikat')->required(),
                        TextInput::make('google_display_name')->label('Nama dari akun Google')->disabled()->dehydrated(false),
                    ])
                    ->action(fn (CertificateEventParticipant $record, array $data) => $record->participant?->update(['name' => $data['name']])),
                Actions\EditAction::make(),
                Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Actions\BulkAction::make('markEligible')
                    ->label('Tandai Hadir & Eligible')
                    ->icon('heroicon-o-check-circle')
                    ->requiresConfirmation()
                    ->visible(fn () => CertificatePermission::allows('edit'))
                    ->action(fn ($records) => $records->each->update(['attendance_status' => 'attended', 'eligible_at' => now()])),
                Actions\DeleteBulkAction::make(),
            ]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function addParticipantManually(array $data): void
    {
        /** @var CertificateEvent $event */
        $event = $this->getOwnerRecord();

        $participant = app(ParticipantRegistry::class)->findOrCreateByEmail(
            $data['email'],
            [
                'name' => $data['name'],
                'institutional_id' => $data['institutional_id'] ?: null,
                'source' => 'admin',
            ],
            ['institutional_id' => $data['institutional_id'] ?: null],
        );

        $existing = $event->participations()
            ->where('participant_id', $participant->id)
            ->where('role', $data['role'])
            ->exists();

        if ($existing) {
            Notification::make()
                ->title('Peserta sudah terdaftar pada kegiatan ini dengan peran yang sama.')
                ->warning()
                ->send();

            return;
        }

        $event->participations()->create([
            'participant_id' => $participant->id,
            'role' => $data['role'],
            'role_label' => $data['role_label'] ?: null,
            'source' => ParticipantSource::MANUAL->value,
        ]);

        Notification::make()->title('Peserta berhasil ditambahkan.')->success()->send();
    }
}

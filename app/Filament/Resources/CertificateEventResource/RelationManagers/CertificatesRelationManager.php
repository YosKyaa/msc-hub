<?php

namespace App\Filament\Resources\CertificateEventResource\RelationManagers;

use Filament\Actions;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Select;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use App\Support\CertificatePermission;
use Illuminate\Support\Str;

class CertificatesRelationManager extends RelationManager
{
    protected static string $relationship = 'certificates';
    protected static ?string $title = 'Penerima Sertifikat';

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('recipient_name')->label('Nama penerima')->required()->maxLength(255),
            TextInput::make('recipient_email')->label('Email')->email(),
            Select::make('recipient_role')->label('Peran')->options([
                'participant' => 'Peserta', 'committee' => 'Panitia', 'speaker' => 'Pembicara',
                'moderator' => 'Moderator', 'organizer' => 'Penyelenggara', 'judge' => 'Juri',
                'mentor' => 'Mentor', 'volunteer' => 'Relawan', 'other' => 'Lainnya',
            ])->default('participant')->required(),
            TextInput::make('recipient_role_label')->label('Label peran khusus')->helperText('Opsional, misalnya Ketua Pelaksana atau Koordinator Acara.'),
            TextInput::make('certificate_number')->label('Nomor sertifikat')->required()->unique(ignoreRecord: true)
                ->default(fn () => 'CERT-'.now()->format('Ymd').'-'.strtoupper(Str::random(6))),
            KeyValue::make('variables')->label('Variabel tambahan')->keyLabel('Nama variabel')->valueLabel('Nilai')->helperText('Digunakan untuk elemen custom pada template.'),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('certificate_number')->label('Nomor')->searchable()->copyable(),
            TextColumn::make('recipient_name')->label('Penerima')->searchable(),
            TextColumn::make('recipient_role')->label('Peran')->badge()->formatStateUsing(fn ($record) => $record->recipient_role_label ?: ucfirst($record->recipient_role)),
            TextColumn::make('recipient_email')->label('Email')->toggleable(),
            IconColumn::make('valid')->label('Valid')->state(fn ($record) => $record->isValid())->boolean(),
            TextColumn::make('issued_at')->label('Terbit')->dateTime('d M Y H:i'),
        ])->headerActions([
            Actions\CreateAction::make()->label('Tambah Penerima')->visible(fn () => CertificatePermission::allows('create')),
        ])
        ->actions([
            Actions\Action::make('verify')->label('Verifikasi')->icon('heroicon-o-qr-code')->url(fn ($record) => route('certificates.verify', $record->verification_token))->openUrlInNewTab(),
            Actions\Action::make('download')->label('PDF')->icon('heroicon-o-arrow-down-tray')->url(fn ($record) => route('certificates.download', $record->verification_token))->openUrlInNewTab()->visible(fn ($record) => $record->isValid()),
            Actions\Action::make('revoke')->label('Cabut')->icon('heroicon-o-no-symbol')->color('danger')->requiresConfirmation()
                ->form([Textarea::make('reason')->label('Alasan pencabutan')->required()])
                ->action(fn ($record, array $data) => $record->update(['revoked_at' => now(), 'revocation_reason' => $data['reason']]))
                ->visible(fn ($record) => $record->revoked_at === null),
            Actions\Action::make('restore')->label('Pulihkan')->icon('heroicon-o-arrow-path')->color('success')->requiresConfirmation()
                ->action(fn ($record) => $record->update(['revoked_at' => null, 'revocation_reason' => null]))
                ->visible(fn ($record) => $record->revoked_at !== null),
            Actions\EditAction::make(), Actions\DeleteAction::make(),
        ])->bulkActions([Actions\DeleteBulkAction::make()]);
    }
}

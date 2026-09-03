<?php

namespace App\Filament\Resources\CertificateEventResource\RelationManagers;

use App\Enums\ParticipantRole;
use App\Jobs\SendCertificateEmailJob;
use App\Models\Certificate;
use App\Support\CertificatePermission;
use Filament\Actions;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
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
            Select::make('recipient_role')->label('Peran')->options(ParticipantRole::options())
                ->default(ParticipantRole::PARTICIPANT->value)->required(),
            TextInput::make('recipient_role_label')->label('Label peran khusus')
                ->helperText('Opsional, misalnya Ketua Pelaksana atau Koordinator Acara.'),
            TextInput::make('certificate_number')->label('Nomor sertifikat')->required()->unique(ignoreRecord: true)
                ->default(fn () => 'CERT-'.now()->format('Ymd').'-'.strtoupper(Str::random(6))),
            KeyValue::make('variables')->label('Variabel tambahan')->keyLabel('Nama variabel')->valueLabel('Nilai')
                ->helperText('Digunakan untuk elemen custom pada template.'),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('recipient_name')
                    ->label('Penerima')
                    ->description(fn (Certificate $record) => $record->recipient_email)
                    ->searchable(),
                TextColumn::make('certificate_number')->label('Nomor')->searchable()->copyable(),
                TextColumn::make('recipient_role')->label('Peran')->badge()
                    ->formatStateUsing(fn (Certificate $record) => $record->recipient_role_label ?: ucfirst($record->recipient_role)),
                IconColumn::make('valid')->label('Valid')->state(fn (Certificate $record) => $record->isValid())->boolean()->alignCenter(),
                TextColumn::make('emailed_at')->label('Email')->dateTime('d M H:i')->placeholder('Belum'),
                TextColumn::make('email_error')->label('Kendala email')->wrap()->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('issued_at')->label('Terbit')->dateTime('d M H:i')->toggleable(),
            ])
            ->filters([
                TernaryFilter::make('emailed')
                    ->label('Status email')
                    ->placeholder('Semua')
                    ->trueLabel('Sudah terkirim')
                    ->falseLabel('Belum terkirim')
                    ->queries(
                        true: fn (Builder $query) => $query->whereNotNull('emailed_at'),
                        false: fn (Builder $query) => $query->whereNull('emailed_at'),
                    ),
            ])
            ->headerActions([
                Actions\CreateAction::make()->label('Tambah Penerima')
                    ->visible(fn () => CertificatePermission::allows('create')),
            ])
            // Tombol ikon inline, alasan yang sama seperti pada tabel peserta.
            ->actions([
                Actions\Action::make('verify')->iconButton()->tooltip('Buka halaman verifikasi')
                    ->icon('heroicon-o-qr-code')
                    ->url(fn (Certificate $record) => $record->verificationUrl())->openUrlInNewTab(),
                Actions\Action::make('download')->iconButton()->tooltip('Unduh PDF')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->url(fn (Certificate $record) => $record->downloadUrl())->openUrlInNewTab()
                    ->visible(fn (Certificate $record) => $record->isValid()),
                Actions\Action::make('resendEmail')->iconButton()->tooltip('Kirim ulang email')
                    ->icon('heroicon-o-envelope')
                    ->color('info')
                    ->requiresConfirmation()
                    ->modalHeading('Kirim Ulang Email Sertifikat')
                    ->modalDescription('Penanda pengiriman direset lalu email diantrekan ulang.')
                    ->visible(fn (Certificate $record) => CertificatePermission::allowsIssuing() && filled($record->recipient_email))
                    ->action(fn (Certificate $record) => $this->resendEmail($record)),
                Actions\Action::make('revoke')->iconButton()->tooltip('Cabut sertifikat')
                    ->icon('heroicon-o-no-symbol')->color('danger')->requiresConfirmation()
                    ->modalHeading('Cabut Sertifikat')
                    ->schema([Textarea::make('reason')->label('Alasan pencabutan')->required()])
                    ->visible(fn (Certificate $record) => CertificatePermission::allowsIssuing() && $record->revoked_at === null)
                    ->action(fn (Certificate $record, array $data) => $record->update([
                        'revoked_at' => now(),
                        'revocation_reason' => $data['reason'],
                    ])),
                Actions\Action::make('restore')->iconButton()->tooltip('Pulihkan sertifikat')
                    ->icon('heroicon-o-arrow-path')->color('success')->requiresConfirmation()
                    ->modalHeading('Pulihkan Sertifikat')
                    ->visible(fn (Certificate $record) => CertificatePermission::allowsIssuing() && $record->revoked_at !== null)
                    ->action(fn (Certificate $record) => $record->update(['revoked_at' => null, 'revocation_reason' => null])),
                Actions\EditAction::make()->iconButton()->tooltip('Ubah penerima'),
                Actions\DeleteAction::make()->iconButton()->tooltip('Hapus sertifikat'),
            ])
            ->bulkActions([Actions\DeleteBulkAction::make()]);
    }

    /**
     * Kirim ulang tetap idempotent: penanda dibersihkan lebih dulu agar job
     * yang antre tidak menghasilkan dua email untuk satu permintaan.
     */
    private function resendEmail(Certificate $certificate): void
    {
        if (! $certificate->isValid()) {
            Notification::make()
                ->title('Email tidak dikirim')
                ->body('Sertifikat belum valid. Pastikan kegiatan sudah dipublikasikan dan sertifikat tidak dicabut.')
                ->warning()
                ->send();

            return;
        }

        $certificate->forceFill(['emailed_at' => null, 'email_failed_at' => null, 'email_error' => null])->save();
        SendCertificateEmailJob::dispatch($certificate);

        Notification::make()->title('Email sertifikat diantrekan ulang.')->success()->send();
    }
}

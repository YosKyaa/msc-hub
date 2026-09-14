<?php

namespace App\Filament\Resources\CertificateEventResource\RelationManagers;

use App\Enums\ParticipantRole;
use App\Jobs\SendCertificateEmailJob;
use App\Models\Certificate;
use App\Services\Certificates\CertificateBatchException;
use App\Services\Certificates\CertificateBatchMailer;
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
use Illuminate\Support\Collection;
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
                // Dua tahap itu harus terbaca sekilas: apa yang sudah terbit
                // belum tentu sudah dikirim.
                TextColumn::make('email_status')
                    ->label('Email')
                    ->badge()
                    ->state(fn (Certificate $record) => match (true) {
                        $record->emailed_at !== null => 'Terkirim',
                        $record->email_failed_at !== null => 'Gagal',
                        blank($record->recipient_email) => 'Tanpa email',
                        default => 'Belum dikirim',
                    })
                    ->color(fn (string $state) => match ($state) {
                        'Terkirim' => 'success',
                        'Gagal' => 'danger',
                        'Tanpa email' => 'gray',
                        default => 'warning',
                    })
                    ->description(fn (Certificate $record) => $record->emailed_at?->format('d M H:i')),
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

                // Tahap kedua: sertifikat sudah terbit, kini dikirim.
                Actions\Action::make('sendPendingEmails')
                    ->label('Kirim Email ke Semua')
                    ->icon('heroicon-o-paper-airplane')
                    ->color('info')
                    ->requiresConfirmation()
                    ->modalHeading('Kirim Email Sertifikat')
                    ->modalDescription(fn () => $this->pendingEmailSummary())
                    ->modalSubmitActionLabel('Ya, Kirim')
                    ->visible(fn () => CertificatePermission::allowsIssuing())
                    ->action(fn () => $this->dispatchEmails()),
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
                Actions\Action::make('resendEmail')->iconButton()
                    ->tooltip(fn (Certificate $record) => $record->emailed_at === null ? 'Kirim email' : 'Kirim ulang email')
                    ->icon(fn (Certificate $record) => $record->emailed_at === null ? 'heroicon-o-paper-airplane' : 'heroicon-o-envelope')
                    ->color('info')
                    ->requiresConfirmation()
                    ->modalHeading(fn (Certificate $record) => $record->emailed_at === null ? 'Kirim Email Sertifikat' : 'Kirim Ulang Email Sertifikat')
                    ->modalDescription(fn (Certificate $record) => $record->emailed_at === null
                        ? 'Sertifikat ini sudah terbit. Emailnya dikirim sekarang.'
                        : 'Penanda pengiriman direset lalu email diantrekan ulang.')
                    ->modalSubmitActionLabel('Ya, Kirim')
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
            ->bulkActions([
                Actions\BulkAction::make('sendEmails')
                    ->label('Kirim Email')
                    ->icon('heroicon-o-paper-airplane')
                    ->color('info')
                    ->requiresConfirmation()
                    ->modalHeading('Kirim Email Sertifikat')
                    ->modalDescription('Hanya penerima yang belum pernah dikirimi yang akan diproses.')
                    ->modalSubmitActionLabel('Ya, Kirim')
                    ->visible(fn () => CertificatePermission::allowsIssuing())
                    ->action(fn (Collection $records) => $this->dispatchEmails($records)),
                Actions\DeleteBulkAction::make(),
            ]);
    }

    /**
     * Antrekan pengiriman email. Penolakannya dijelaskan langsung agar admin
     * tahu apa yang harus dibereskan lebih dulu.
     *
     * @param  Collection<int, Certificate>|null  $records
     */
    private function dispatchEmails(?Collection $records = null): void
    {
        try {
            $batch = app(CertificateBatchMailer::class)->dispatchFor(
                $this->getOwnerRecord(),
                $records,
                auth()->user(),
            );
        } catch (CertificateBatchException $exception) {
            Notification::make()->title('Pengiriman dibatalkan')->body($exception->getMessage())->warning()->send();

            return;
        }

        Notification::make()
            ->title('Email diantrekan')
            ->body("{$batch->totalJobs} email sertifikat sedang dikirim di latar belakang.")
            ->success()
            ->send();
    }

    private function pendingEmailSummary(): string
    {
        $menunggu = app(CertificateBatchMailer::class)->pendingCountFor($this->getOwnerRecord());

        return $menunggu === 0
            ? 'Semua sertifikat yang punya alamat email sudah pernah dikirim.'
            : "{$menunggu} sertifikat belum pernah dikirimi email. Penerima yang sudah menerima tidak dikirimi ulang.";
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

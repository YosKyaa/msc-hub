<?php

namespace App\Filament\Resources\CertificateEventResource\RelationManagers;

use App\Enums\ParticipantRole;
use App\Enums\ParticipantSource;
use App\Filament\Actions\ImportParticipantsAction;
use App\Jobs\SendCertificateEmailJob;
use App\Models\Certificate;
use App\Models\CertificateEvent;
use App\Models\CertificateEventParticipant;
use App\Services\Certificates\CertificateBatchException;
use App\Services\Certificates\CertificateBatchIssuer;
use App\Services\Certificates\CertificateBatchMailer;
use App\Services\Certificates\ParticipantRegistry;
use App\Support\CertificatePermission;
use App\Support\CertificateStage;
use App\Support\QueueHealth;
use Filament\Actions;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Satu tabel untuk seluruh perjalanan sertifikat.
 *
 * Dulu orang yang sama muncul dua kali — sekali sebagai peserta, sekali lagi
 * sebagai penerima sertifikat di tab lain — sehingga admin harus berpindah
 * bolak-balik dan merangkai sendiri keadaannya dari beberapa kolom terpisah.
 * Sekarang satu baris per orang, dengan satu kolom tahap yang menjelaskan
 * sudah sampai mana dan apa langkah berikutnya.
 */
class ParticipationsRelationManager extends RelationManager
{
    protected static string $relationship = 'participations';

    protected static ?string $title = 'Peserta & Sertifikat';

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
            ->description($this->progressSummary())
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['participant', 'certificate']))
            ->columns([
                TextColumn::make('participant.name')
                    ->label('Nama')
                    ->description(fn (CertificateEventParticipant $record) => $record->participant?->email ?: 'Tanpa email')
                    ->searchable(query: fn (Builder $query, string $search) => $query->whereHas(
                        'participant',
                        fn (Builder $participant) => $participant
                            ->where('name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%"),
                    ))
                    ->sortable(),

                TextColumn::make('role')
                    ->label('Peran')
                    ->badge()
                    ->formatStateUsing(fn (CertificateEventParticipant $record) => $record->resolvedRoleLabel()),

                // Menggantikan kolom Eligible, Sertifikat, dan Email yang dulu
                // terpisah: satu tahap bernama, dengan langkah berikutnya
                // tertulis di bawahnya.
                TextColumn::make('stage')
                    ->label('Status Sertifikat')
                    ->badge()
                    ->state(fn (CertificateEventParticipant $record) => CertificateStage::for($record)->getLabel())
                    ->color(fn (CertificateEventParticipant $record) => CertificateStage::for($record)->getColor())
                    ->description(fn (CertificateEventParticipant $record) => CertificateStage::for($record)->getHint())
                    ->wrap(),

                TextColumn::make('certificate.certificate_number')
                    ->label('Nomor')
                    ->placeholder('Belum ada')
                    ->copyable()
                    ->searchable(),

                TextColumn::make('source')
                    ->label('Asal')
                    ->badge()
                    ->formatStateUsing(fn (?ParticipantSource $state) => $state?->getLabel() ?? '—')
                    ->color(fn (?ParticipantSource $state) => $state?->getColor() ?? 'gray')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('checked_in_at')->label('Check-in')->dateTime('d M H:i')->placeholder('—')->sortable()->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('checked_out_at')->label('Check-out')->dateTime('d M H:i')->placeholder('—')->sortable()->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('certificate.emailed_at')->label('Dikirim')->dateTime('d M H:i')->placeholder('—')->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('certificate.email_error')->label('Kendala email')->wrap()->placeholder('—')->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('stage')
                    ->label('Status sertifikat')
                    ->options(CertificateStage::options())
                    ->query(fn (Builder $query, array $data) => $this->filterByStage($query, $data['value'] ?? null)),
                SelectFilter::make('role')->label('Peran')->options(ParticipantRole::options()),
                SelectFilter::make('source')->label('Asal pendaftaran')->options(ParticipantSource::options()),
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

                // Dua tahap, dua tombol, berdampingan dan berurutan.
                Actions\Action::make('issueAllEligible')
                    ->label('1. Terbitkan Digital')
                    ->icon('heroicon-o-academic-cap')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading('Terbitkan Sertifikat Secara Digital')
                    ->modalDescription('Nomor diberikan dan halaman verifikasinya langsung aktif, sehingga sertifikat bisa diunduh dan dicek keasliannya. Email BELUM dikirim — periksa hasilnya dulu, lalu tekan Kirim Email.')
                    ->modalSubmitActionLabel('Ya, Terbitkan')
                    ->visible(fn () => CertificatePermission::allowsIssuing())
                    ->action(fn () => $this->dispatchIssuing()),

                Actions\Action::make('sendPendingEmails')
                    ->label('2. Kirim Email')
                    ->icon('heroicon-o-paper-airplane')
                    ->color('info')
                    ->requiresConfirmation()
                    ->modalHeading('Kirim Email Sertifikat')
                    ->modalDescription(fn () => $this->pendingEmailSummary())
                    ->modalSubmitActionLabel('Ya, Kirim')
                    ->visible(fn () => CertificatePermission::allowsIssuing())
                    ->action(fn () => $this->dispatchEmails()),
            ])
            // Tombol ikon inline, bukan dropdown: panel ActionGroup Filament
            // dirender tanpa modifier `.flip`, sehingga selalu membuka ke bawah
            // dan terpotong tepi layar pada baris terakhir tabel.
            ->actions([
                Actions\Action::make('toggleEligible')
                    ->iconButton()
                    ->tooltip(fn (CertificateEventParticipant $record) => $record->isEligible() ? 'Batalkan hak sertifikat' : 'Tandai berhak menerima')
                    ->icon(fn (CertificateEventParticipant $record) => $record->isEligible() ? 'heroicon-o-x-circle' : 'heroicon-o-check-circle')
                    ->color(fn (CertificateEventParticipant $record) => $record->isEligible() ? 'gray' : 'success')
                    // Setelah terbit, haknya tidak lagi bisa dicabut lewat sini:
                    // yang berlaku adalah pencabutan sertifikatnya.
                    ->visible(fn (CertificateEventParticipant $record) => CertificatePermission::allows('edit') && $record->certificate === null)
                    ->action(fn (CertificateEventParticipant $record) => $record->update(
                        $record->isEligible()
                            ? ['eligible_at' => null]
                            : ['eligible_at' => now(), 'attendance_status' => 'attended'],
                    )),

                Actions\Action::make('correctName')
                    ->iconButton()
                    ->tooltip('Koreksi nama yang dicetak')
                    ->icon('heroicon-o-pencil-square')
                    ->color('warning')
                    ->modalHeading('Koreksi Nama Peserta')
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

                Actions\Action::make('verify')
                    ->iconButton()
                    ->tooltip('Buka halaman verifikasi')
                    ->icon('heroicon-o-qr-code')
                    ->url(fn (CertificateEventParticipant $record) => $record->certificate?->verificationUrl())
                    ->openUrlInNewTab()
                    ->visible(fn (CertificateEventParticipant $record) => $record->certificate !== null),

                Actions\Action::make('download')
                    ->iconButton()
                    ->tooltip('Unduh PDF')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->url(fn (CertificateEventParticipant $record) => $record->certificate?->downloadUrl())
                    ->openUrlInNewTab()
                    ->visible(fn (CertificateEventParticipant $record) => $record->certificate?->isValid() === true),

                Actions\Action::make('sendEmail')
                    ->iconButton()
                    ->tooltip(fn (CertificateEventParticipant $record) => $record->certificate?->emailed_at === null ? 'Kirim email' : 'Kirim ulang email')
                    ->icon(fn (CertificateEventParticipant $record) => $record->certificate?->emailed_at === null ? 'heroicon-o-paper-airplane' : 'heroicon-o-envelope')
                    ->color('info')
                    ->requiresConfirmation()
                    ->modalHeading(fn (CertificateEventParticipant $record) => $record->certificate?->emailed_at === null ? 'Kirim Email Sertifikat' : 'Kirim Ulang Email Sertifikat')
                    ->modalDescription(fn (CertificateEventParticipant $record) => $record->certificate?->emailed_at === null
                        ? 'Sertifikat ini sudah terbit. Emailnya dikirim sekarang.'
                        : 'Penanda pengiriman direset lalu email diantrekan ulang.')
                    ->modalSubmitActionLabel('Ya, Kirim')
                    ->visible(fn (CertificateEventParticipant $record) => CertificatePermission::allowsIssuing()
                        && $record->certificate !== null
                        && filled($record->certificate->recipient_email))
                    ->action(fn (CertificateEventParticipant $record) => $this->resendEmail($record->certificate)),

                Actions\Action::make('revoke')
                    ->iconButton()
                    ->tooltip('Cabut sertifikat')
                    ->icon('heroicon-o-no-symbol')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading('Cabut Sertifikat')
                    ->schema([Textarea::make('reason')->label('Alasan pencabutan')->required()])
                    ->visible(fn (CertificateEventParticipant $record) => CertificatePermission::allowsIssuing()
                        && $record->certificate !== null
                        && $record->certificate->revoked_at === null)
                    ->action(fn (CertificateEventParticipant $record, array $data) => $record->certificate?->update([
                        'revoked_at' => now(),
                        'revocation_reason' => $data['reason'],
                    ])),

                Actions\Action::make('restore')
                    ->iconButton()
                    ->tooltip('Pulihkan sertifikat')
                    ->icon('heroicon-o-arrow-path')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading('Pulihkan Sertifikat')
                    ->visible(fn (CertificateEventParticipant $record) => CertificatePermission::allowsIssuing()
                        && $record->certificate?->revoked_at !== null)
                    ->action(fn (CertificateEventParticipant $record) => $record->certificate?->update([
                        'revoked_at' => null,
                        'revocation_reason' => null,
                    ])),

                Actions\EditAction::make()->iconButton()->tooltip('Ubah keikutsertaan'),

                // Menghapus keikutsertaan hanya melepaskan sertifikatnya dari
                // pemiliknya (kunci asingnya nullOnDelete), sehingga dokumen
                // yang sudah terbit menghilang dari tabel tanpa benar-benar
                // hilang. Pencabutan yang dipakai untuk itu.
                Actions\DeleteAction::make()
                    ->iconButton()
                    ->tooltip('Hapus dari kegiatan')
                    ->visible(fn (CertificateEventParticipant $record) => $record->certificate === null),
            ])
            ->bulkActions([
                Actions\BulkAction::make('markEligible')
                    ->label('Tandai Hadir & Berhak')
                    ->icon('heroicon-o-check-circle')
                    ->requiresConfirmation()
                    ->visible(fn () => CertificatePermission::allows('edit'))
                    ->action(fn ($records) => $records->each->update(['attendance_status' => 'attended', 'eligible_at' => now()])),

                Actions\BulkAction::make('issueCertificates')
                    ->label('Terbitkan Digital')
                    ->icon('heroicon-o-academic-cap')
                    ->requiresConfirmation()
                    ->modalHeading('Terbitkan Sertifikat Secara Digital')
                    ->modalDescription('Email belum dikirim. Periksa hasilnya dulu, lalu tekan Kirim Email.')
                    ->modalSubmitActionLabel('Ya, Terbitkan')
                    ->visible(fn () => CertificatePermission::allowsIssuing())
                    ->action(fn (Collection $records) => $this->dispatchIssuing($records)),

                Actions\BulkAction::make('sendEmails')
                    ->label('Kirim Email')
                    ->icon('heroicon-o-paper-airplane')
                    ->requiresConfirmation()
                    ->modalHeading('Kirim Email Sertifikat')
                    ->modalDescription('Hanya penerima yang belum pernah dikirimi yang akan diproses.')
                    ->modalSubmitActionLabel('Ya, Kirim')
                    ->visible(fn () => CertificatePermission::allowsIssuing())
                    ->action(fn (Collection $records) => $this->dispatchEmails(
                        $records->map(fn (CertificateEventParticipant $record) => $record->certificate)->filter()->values(),
                    )),

                Actions\DeleteBulkAction::make(),
            ]);
    }

    /**
     * Ringkasan di atas tabel, supaya keadaan seluruh kegiatan terbaca tanpa
     * perlu menghitung baris satu per satu.
     */
    private function progressSummary(): string
    {
        /** @var CertificateEvent $event */
        $event = $this->getOwnerRecord();

        $participations = $event->participations()->with('certificate')->get();
        $jumlah = [];

        foreach ($participations as $participation) {
            $label = CertificateStage::for($participation)->getLabel();
            $jumlah[$label] = ($jumlah[$label] ?? 0) + 1;
        }

        if ($jumlah === []) {
            return 'Belum ada peserta. Tambahkan satu per satu atau impor dari berkas.';
        }

        $bagian = [];

        foreach ($jumlah as $label => $total) {
            $bagian[] = "{$total} {$label}";
        }

        $ringkasan = $participations->count().' orang: '.implode(' · ', $bagian);

        // Sertifikat yang kehilangan pemiliknya tidak muncul di tabel ini,
        // jadi keberadaannya harus tetap diberitahukan.
        $yatim = Certificate::where('certificate_event_id', $event->id)
            ->whereNull('event_participant_id')
            ->count();

        return $yatim > 0
            ? $ringkasan." — perhatian: {$yatim} sertifikat tidak lagi terhubung ke peserta mana pun."
            : $ringkasan;
    }

    private function filterByStage(Builder $query, ?string $stage): Builder
    {
        return match (CertificateStage::tryFrom((string) $stage)) {
            CertificateStage::NOT_ELIGIBLE => $query->whereNull('eligible_at'),
            CertificateStage::READY => $query->whereNotNull('eligible_at')->whereDoesntHave('certificate'),
            CertificateStage::ISSUED => $query->whereHas('certificate', fn (Builder $c) => $c
                ->whereNull('revoked_at')->whereNull('emailed_at')->whereNull('email_failed_at')->whereNotNull('recipient_email')),
            CertificateStage::ISSUED_WITHOUT_EMAIL => $query->whereHas('certificate', fn (Builder $c) => $c
                ->whereNull('revoked_at')->whereNull('emailed_at')->whereNull('recipient_email')),
            CertificateStage::SENT => $query->whereHas('certificate', fn (Builder $c) => $c
                ->whereNull('revoked_at')->whereNotNull('emailed_at')),
            CertificateStage::FAILED => $query->whereHas('certificate', fn (Builder $c) => $c
                ->whereNull('revoked_at')->whereNull('emailed_at')->whereNotNull('email_failed_at')),
            CertificateStage::REVOKED => $query->whereHas('certificate', fn (Builder $c) => $c->whereNotNull('revoked_at')),
            default => $query,
        };
    }

    /**
     * Antrekan penerbitan sertifikat. Validasi ringan dilakukan langsung agar
     * admin segera tahu bila tidak ada yang bisa diterbitkan.
     *
     * @param  Collection<int, CertificateEventParticipant>|null  $records
     */
    private function dispatchIssuing(?Collection $records = null): void
    {
        try {
            $outcome = app(CertificateBatchIssuer::class)->issueFor(
                $this->getOwnerRecord(),
                $records,
                auth()->user(),
            );
        } catch (CertificateBatchException $exception) {
            Notification::make()->title('Penerbitan dibatalkan')->body($exception->getMessage())->warning()->send();

            return;
        }

        Notification::make()
            ->title($outcome->title())
            ->body($outcome->body())
            ->status($outcome->isSuccessful() ? 'success' : 'warning')
            ->send();
    }

    /**
     * Antrekan pengiriman email. Penolakannya dijelaskan langsung agar admin
     * tahu apa yang harus dibereskan lebih dulu.
     *
     * @param  Collection<int, Certificate>|null  $certificates
     */
    private function dispatchEmails(?Collection $certificates = null): void
    {
        try {
            $batch = app(CertificateBatchMailer::class)->dispatchFor(
                $this->getOwnerRecord(),
                $certificates,
                auth()->user(),
            );
        } catch (CertificateBatchException $exception) {
            Notification::make()->title('Pengiriman dibatalkan')->body($exception->getMessage())->warning()->send();

            return;
        }

        // Pengiriman email tetap lewat antrean karena SMTP lambat, jadi
        // admin harus tahu bila antreannya ternyata tidak dikerjakan.
        $peringatan = QueueHealth::warning();

        Notification::make()
            ->title('Email diantrekan')
            ->body("{$batch->totalJobs} email sertifikat sedang dikirim di latar belakang."
                .($peringatan === null ? '' : ' '.$peringatan))
            ->status($peringatan === null ? 'success' : 'warning')
            ->persistent($peringatan !== null)
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
    private function resendEmail(?Certificate $certificate): void
    {
        if ($certificate === null) {
            return;
        }

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

    /**
     * Pendaftaran manual memakai registry yang sama dengan import, sehingga
     * aturan dedup peserta hanya hidup di satu tempat.
     *
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

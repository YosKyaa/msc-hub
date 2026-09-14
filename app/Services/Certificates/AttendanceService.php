<?php

namespace App\Services\Certificates;

use App\Enums\AttendanceAction;
use App\Enums\ParticipantRole;
use App\Enums\ParticipantSource;
use App\Models\CertificateEvent;
use App\Models\CertificateEventParticipant;
use App\Models\Participant;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * Mencatat check-in dan check-out peserta dari halaman absensi publik.
 *
 * Check-in dan check-out adalah dua aksi terpisah dengan QR dan window waktu
 * masing-masing, sehingga peserta tidak bisa langsung check-out sesaat setelah
 * check-in. Seluruh mutasi dibungkus transaksi dan aman terhadap pengiriman
 * ganda.
 */
class AttendanceService
{
    public function __construct(private readonly ParticipantRegistry $participants) {}

    /**
     * @param  array{email: string, name?: string|null, google_id?: string|null}  $googleProfile
     * @param  string|null  $declaredName  Nama lengkap yang diketik peserta; hanya
     *                                     dipakai ketika ia belum ada di master.
     */
    public function record(
        CertificateEvent $event,
        AttendanceAction $action,
        array $googleProfile,
        ?string $declaredName = null,
    ): AttendanceOutcome {
        return DB::transaction(function () use ($event, $action, $googleProfile, $declaredName) {
            if ($action === AttendanceAction::CHECK_OUT) {
                return $this->recordCheckOut($event, $googleProfile['email']);
            }

            return $this->recordCheckIn($event, $googleProfile, $declaredName);
        });
    }

    public function knownParticipant(string $email): ?Participant
    {
        return Participant::where('email', ParticipantRegistry::normaliseEmail($email))->first();
    }

    /**
     * Peserta berhak mengetik namanya sendiri ketika ia belum tercatat pada
     * kegiatan mana pun dan record masternya memang lahir dari jalur absensi.
     *
     * Menghapus keikutsertaan lewat panel mengembalikan peserta ke keadaan ini,
     * sehingga panitia dapat menyuruhnya check-in ulang untuk memperbaiki nama.
     * Nama yang diketik admin (input manual, import, master participant) tidak
     * ikut terbuka, agar koreksi resmi tidak tertimpa peserta.
     */
    public function mayDeclareName(?Participant $participant): bool
    {
        if ($participant === null) {
            return true;
        }

        return $participant->source === ParticipantSource::ATTENDANCE->value
            && $participant->participations()->doesntExist();
    }

    /**
     * Keikutsertaan dicari lintas peran: seseorang yang sudah terdaftar sebagai
     * pembicara tidak boleh menghasilkan baris peserta kedua saat scan QR.
     */
    public function findParticipation(CertificateEvent $event, string $email): ?CertificateEventParticipant
    {
        $participant = $this->knownParticipant($email);

        if ($participant === null) {
            return null;
        }

        return $this->participationQuery($event, $participant->id)->first();
    }

    /**
     * @param  array{email: string, name?: string|null, google_id?: string|null}  $googleProfile
     */
    private function recordCheckIn(
        CertificateEvent $event,
        array $googleProfile,
        ?string $declaredName,
    ): AttendanceOutcome {
        $participant = $this->resolveParticipant($googleProfile, $declaredName);
        $participation = $this->resolveParticipation($event, $participant);

        if ($participation->checked_in_at !== null) {
            return new AttendanceOutcome(AttendanceOutcome::ALREADY_CHECKED_IN, $participation);
        }

        $participation->forceFill([
            'checked_in_at' => now(),
            'attendance_status' => 'attended',
        ])->save();

        $participation->setRelation('event', $event);
        $participation->syncEligibility();

        return new AttendanceOutcome(AttendanceOutcome::CHECKED_IN, $participation);
    }

    private function recordCheckOut(CertificateEvent $event, string $email): AttendanceOutcome
    {
        $participant = $this->knownParticipant($email);
        $participation = $participant
            ? $this->participationQuery($event, $participant->id)->lockForUpdate()->first()
            : null;

        // Check-out hanya sah sebagai penutup check-in yang sudah tercatat.
        if ($participation === null || $participation->checked_in_at === null) {
            return new AttendanceOutcome(AttendanceOutcome::NOT_CHECKED_IN, $participation);
        }

        if ($participation->checked_out_at !== null) {
            return new AttendanceOutcome(AttendanceOutcome::ALREADY_CHECKED_OUT, $participation);
        }

        $participation->forceFill(['checked_out_at' => now()])->save();

        $participation->setRelation('event', $event);
        $participation->syncEligibility();

        return new AttendanceOutcome(AttendanceOutcome::CHECKED_OUT, $participation);
    }

    /**
     * @param  array{email: string, name?: string|null, google_id?: string|null}  $googleProfile
     */
    private function resolveParticipant(array $googleProfile, ?string $declaredName): Participant
    {
        $email = ParticipantRegistry::normaliseEmail($googleProfile['email']);
        $googleName = trim((string) ($googleProfile['name'] ?? ''));
        $declaredName = trim((string) $declaredName);
        $printedName = $declaredName ?: ($googleName ?: $email);

        $participant = $this->participants->findOrCreateByEmail(
            $email,
            [
                'name' => $printedName,
                'google_display_name' => $googleName ?: null,
                'source' => ParticipantSource::ATTENDANCE->value,
            ],
            ['google_display_name' => $googleName ?: null],
        );

        // Nama hanya boleh diperbarui selama peserta masih berhak mengisinya;
        // di luar itu record master menang dan koreksi menjadi tugas admin.
        if (! $participant->wasRecentlyCreated && $declaredName !== '' && $this->mayDeclareName($participant)) {
            $participant->forceFill(['name' => $declaredName])->save();
        }

        return $participant;
    }

    private function resolveParticipation(CertificateEvent $event, Participant $participant): CertificateEventParticipant
    {
        $existing = $this->participationQuery($event, $participant->id)->lockForUpdate()->first();

        if ($existing) {
            return $existing;
        }

        $attributes = [
            'certificate_event_id' => $event->id,
            'participant_id' => $participant->id,
            'role' => ParticipantRole::PARTICIPANT->value,
            'source' => ParticipantSource::ATTENDANCE->value,
        ];

        try {
            return CertificateEventParticipant::create($attributes);
        } catch (QueryException $exception) {
            // Dua permintaan bersamaan: unique (event, participant, role) menang,
            // baris yang sudah ada dipakai bersama.
            $winner = CertificateEventParticipant::where($attributes)->first();

            if ($winner === null) {
                throw $exception;
            }

            return $winner;
        }
    }

    private function participationQuery(CertificateEvent $event, int $participantId): \Illuminate\Database\Eloquent\Builder
    {
        return CertificateEventParticipant::where('certificate_event_id', $event->id)
            ->where('participant_id', $participantId)
            ->orderBy('id');
    }
}

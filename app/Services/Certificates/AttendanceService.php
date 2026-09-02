<?php

namespace App\Services\Certificates;

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
 * Satu URL melayani dua aksi: state peserta yang menentukan aksi berikutnya
 * (keputusan D2). Seluruh mutasi dibungkus transaksi dan aman terhadap
 * pengiriman ganda.
 */
class AttendanceService
{
    public function __construct(private readonly ParticipantRegistry $participants) {}

    /**
     * @param  array{email: string, name?: string|null, google_id?: string|null}  $googleProfile
     */
    public function record(CertificateEvent $event, array $googleProfile): AttendanceOutcome
    {
        return DB::transaction(function () use ($event, $googleProfile) {
            $participant = $this->resolveParticipant($googleProfile);
            $participation = $this->resolveParticipation($event, $participant);

            $outcome = $this->advance($participation);

            $participation->setRelation('event', $event);
            $participation->syncEligibility();

            return $outcome;
        });
    }

    /**
     * Keikutsertaan dicari lintas peran: seseorang yang sudah terdaftar sebagai
     * pembicara tidak boleh menghasilkan baris peserta kedua saat scan QR.
     */
    public function findParticipation(CertificateEvent $event, string $email): ?CertificateEventParticipant
    {
        $participantId = Participant::where('email', ParticipantRegistry::normaliseEmail($email))->value('id');

        if ($participantId === null) {
            return null;
        }

        return CertificateEventParticipant::where('certificate_event_id', $event->id)
            ->where('participant_id', $participantId)
            ->orderBy('id')
            ->first();
    }

    /**
     * @param  array{email: string, name?: string|null, google_id?: string|null}  $googleProfile
     */
    private function resolveParticipant(array $googleProfile): Participant
    {
        $email = ParticipantRegistry::normaliseEmail($googleProfile['email']);
        $displayName = trim((string) ($googleProfile['name'] ?? '')) ?: $email;

        return $this->participants->findOrCreateByEmail(
            $email,
            [
                'name' => $displayName,
                'google_display_name' => $displayName,
                'source' => 'attendance',
            ],
            // Nama formal yang mungkin sudah dikoreksi admin tidak boleh tertimpa.
            ['google_display_name' => $displayName],
        );
    }

    private function resolveParticipation(CertificateEvent $event, Participant $participant): CertificateEventParticipant
    {
        $existing = CertificateEventParticipant::where('certificate_event_id', $event->id)
            ->where('participant_id', $participant->id)
            ->orderBy('id')
            ->lockForUpdate()
            ->first();

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

    private function advance(CertificateEventParticipant $participation): AttendanceOutcome
    {
        if ($participation->checked_in_at === null) {
            $participation->forceFill([
                'checked_in_at' => now(),
                'attendance_status' => 'attended',
            ])->save();

            return new AttendanceOutcome(AttendanceOutcome::CHECKED_IN, $participation);
        }

        if ($participation->checked_out_at === null) {
            $participation->forceFill(['checked_out_at' => now()])->save();

            return new AttendanceOutcome(AttendanceOutcome::CHECKED_OUT, $participation);
        }

        return new AttendanceOutcome(AttendanceOutcome::ALREADY_COMPLETE, $participation);
    }
}

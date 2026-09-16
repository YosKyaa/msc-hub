<?php

namespace App\Services\Certificates;

use App\Models\Certificate;
use App\Models\CertificateEvent;
use App\Models\CertificateEventParticipant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * Penerbitan sertifikat untuk satu keikutsertaan.
 *
 * Dipakai bersama oleh aksi panel dan IssueCertificateJob agar penomoran serta
 * aturan idempotensi hanya hidup di satu tempat.
 */
class CertificateIssuer
{
    public function __construct(private readonly CertificateNumberGenerator $numbers) {}

    /**
     * Terbitkan sertifikat, atau kembalikan yang sudah ada. Aman dipanggil ulang.
     */
    public function issue(CertificateEventParticipant $participation): Certificate
    {
        $participation->loadMissing(['participant', 'event', 'certificate']);

        return DB::transaction(function () use ($participation) {
            if ($participation->certificate) {
                return $participation->certificate;
            }

            try {
                return $this->create($participation);
            } catch (QueryException $exception) {
                // Job yang dijalankan ulang bersamaan: unique index menang,
                // sertifikat yang sudah ada dipakai bersama.
                $existing = Certificate::where('event_participant_id', $participation->id)->first();

                if ($existing === null) {
                    throw $exception;
                }

                return $existing;
            }
        });
    }

    /**
     * Keikutsertaan yang berhak menerima sertifikat tetapi belum memilikinya.
     */
    public function pendingFor(CertificateEvent $event): Builder
    {
        return $event->participations()->getQuery()->eligible()->withoutCertificate();
    }

    private function create(CertificateEventParticipant $participation): Certificate
    {
        $person = $participation->participant;

        return Certificate::create([
            'certificate_event_id' => $participation->certificate_event_id,
            'participant_id' => $person?->id,
            'event_participant_id' => $participation->id,
            'certificate_number' => $this->resolveNumber($participation),
            'recipient_name' => $person?->name ?? '—',
            'recipient_email' => $person?->email,
            'recipient_role' => $participation->role,
            'recipient_role_label' => $participation->resolvedRoleLabel(),
            'variables' => $participation->variables,
        ]);
    }

    /**
     * Nomor yang sudah ditetapkan manual — lewat import atau diketik admin —
     * selalu menang. Selebihnya dibuat dari pola penomoran yang berlaku.
     */
    private function resolveNumber(CertificateEventParticipant $participation): string
    {
        if (filled($participation->certificate_number)) {
            return $participation->certificate_number;
        }

        return $this->numbers->next($participation->event);
    }
}

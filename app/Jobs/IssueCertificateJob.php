<?php

namespace App\Jobs;

use App\Models\CertificateEventParticipant;
use App\Services\Certificates\CertificateIssuer;
use Illuminate\Bus\Batchable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Menerbitkan satu sertifikat lalu mengantrekan emailnya.
 * Idempotent: keikutsertaan yang sudah punya sertifikat dilewati.
 */
class IssueCertificateJob implements ShouldQueue
{
    use Batchable;
    use Queueable;

    public int $tries = 3;

    /** @var array<int, int> */
    public array $backoff = [10, 30, 60];

    public function __construct(public CertificateEventParticipant $participation) {}

    public function handle(CertificateIssuer $issuer): void
    {
        if ($this->batch()?->cancelled()) {
            return;
        }

        $participation = $this->participation->fresh(['participant', 'event', 'certificate']);

        if ($participation === null || ! $participation->isEligible()) {
            return;
        }

        SendCertificateEmailJob::dispatch($issuer->issue($participation));
    }

    public function failed(Throwable $exception): void
    {
        Log::error('Gagal menerbitkan sertifikat.', [
            'event_participant_id' => $this->participation->id,
            'certificate_event_id' => $this->participation->certificate_event_id,
            'exception' => $exception,
        ]);
    }
}

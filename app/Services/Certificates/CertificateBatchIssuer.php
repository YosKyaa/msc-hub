<?php

namespace App\Services\Certificates;

use App\Jobs\IssueCertificateJob;
use App\Models\CertificateEvent;
use App\Models\CertificateEventParticipant;
use App\Models\User;
use Filament\Notifications\Notification;
use Illuminate\Bus\Batch;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Bus;
use Throwable;

/**
 * Mengantrekan penerbitan sertifikat sebagai satu batch.
 *
 * Validasi ringan dilakukan synchronous di sini; pekerjaan berat (render,
 * penomoran, email) dijalankan oleh IssueCertificateJob di antrean.
 */
class CertificateBatchIssuer
{
    public function __construct(private readonly CertificateIssuer $issuer) {}

    /**
     * @param  Collection<int, CertificateEventParticipant>|null  $participations
     *        Kosongkan untuk menerbitkan bagi seluruh peserta eligible.
     *
     * @throws CertificateBatchException
     */
    public function dispatchFor(
        CertificateEvent $event,
        ?Collection $participations = null,
        ?User $notify = null,
    ): Batch {
        $this->guardTemplate($event);

        $pending = ($participations ?? $this->issuer->pendingFor($event)->get())
            ->filter(fn (CertificateEventParticipant $participation) => $participation->isEligible() && $participation->certificate === null)
            ->values();

        if ($pending->isEmpty()) {
            throw CertificateBatchException::nothingToIssue();
        }

        return Bus::batch($pending->map(fn (CertificateEventParticipant $participation) => new IssueCertificateJob($participation))->all())
            ->name("Penerbitan sertifikat: {$event->name}")
            ->allowFailures()
            ->finally(fn (Batch $batch) => $this->announce($batch, $notify?->id))
            ->dispatch();
    }

    private function guardTemplate(CertificateEvent $event): void
    {
        $event->loadMissing('template');

        if ($event->template === null || ! $event->template->is_active) {
            throw CertificateBatchException::inactiveTemplate();
        }
    }

    /**
     * Umpan balik ringkas ke panel setelah batch selesai (tanpa UI real-time).
     */
    private function announce(Batch $batch, ?int $userId): void
    {
        if ($userId === null) {
            return;
        }

        try {
            $recipient = User::find($userId);

            if ($recipient === null) {
                return;
            }

            $issued = $batch->totalJobs - $batch->failedJobs;

            Notification::make()
                ->title('Penerbitan sertifikat selesai')
                ->body("{$issued} sertifikat diterbitkan, {$batch->failedJobs} gagal.")
                ->status($batch->failedJobs > 0 ? 'warning' : 'success')
                ->sendToDatabase($recipient);
        } catch (Throwable) {
            // Notifikasi bersifat informatif; kegagalannya tidak boleh
            // menggagalkan batch yang sudah selesai.
        }
    }
}

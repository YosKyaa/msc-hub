<?php

namespace App\Services\Certificates;

use App\Enums\CertificateDesign;
use App\Jobs\IssueCertificateJob;
use App\Models\CertificateEvent;
use App\Models\CertificateEventParticipant;
use App\Models\User;
use Filament\Notifications\Notification;
use Illuminate\Bus\Batch;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Menerbitkan sertifikat untuk satu kegiatan.
 *
 * Penerbitannya sendiri ringan — nomor diberikan lalu satu baris disimpan;
 * PDF baru dibentuk ketika diunduh. Karena itu jumlah yang wajar dikerjakan
 * langsung, supaya tombolnya benar-benar menerbitkan alih-alih menitipkan
 * pekerjaan ke antrean yang belum tentu ada pekerjanya. Hanya rombongan
 * besar yang dilempar ke latar belakang.
 */
class CertificateBatchIssuer
{
    /** Di atas jumlah ini penerbitan dikerjakan di latar belakang. */
    private const INLINE_LIMIT = 100;

    public function __construct(private readonly CertificateIssuer $issuer) {}

    /**
     * Terbitkan sekarang bila jumlahnya wajar, antrekan bila banyak.
     *
     * @param  Collection<int, CertificateEventParticipant>|null  $participations
     *
     * @throws CertificateBatchException
     */
    public function issueFor(
        CertificateEvent $event,
        ?Collection $participations = null,
        ?User $notify = null,
        CertificateDesign $design = CertificateDesign::TEMPLATE,
    ): CertificateIssueOutcome {
        // Sertifikat desain sendiri tidak pernah digambar sistem, jadi
        // kegiatannya boleh tanpa template.
        if ($design === CertificateDesign::TEMPLATE) {
            $this->guardTemplate($event);
        }

        $pending = $this->pending($event, $participations);

        if ($pending->isEmpty()) {
            throw CertificateBatchException::nothingToIssue();
        }

        if ($pending->count() > $this->inlineLimit()) {
            $this->dispatchBatch($event, $pending, $notify, $design);

            return CertificateIssueOutcome::queued($pending->count(), $design);
        }

        $issued = 0;
        $failed = 0;

        foreach ($pending as $participation) {
            try {
                $this->issuer->issue($participation, $design);
                $issued++;
            } catch (Throwable $exception) {
                $failed++;

                Log::error('Gagal menerbitkan sertifikat.', [
                    'event_participant_id' => $participation->id,
                    'certificate_event_id' => $event->id,
                    'exception' => $exception,
                ]);
            }
        }

        return CertificateIssueOutcome::completed($issued, $failed, $design);
    }

    /**
     * Terbitkan nomor dan QR lebih dulu bagi penerima yang dipilih admin,
     * misalnya pemenang dan pembicara, yang sertifikatnya didesain di luar.
     *
     * Memilih mereka adalah keputusan admin bahwa mereka berhak, jadi yang
     * belum ditandai berhak ditandai sekarang — QR-nya memang perlu ada jauh
     * sebelum kehadiran mana pun tercatat.
     *
     * @param  Collection<int, CertificateEventParticipant>  $participations
     *
     * @throws CertificateBatchException
     */
    public function issueOwnDesignFor(
        CertificateEvent $event,
        Collection $participations,
        ?User $notify = null,
    ): CertificateIssueOutcome {
        $belumTerbit = $participations->filter(fn (CertificateEventParticipant $participation) => $participation->certificate === null);

        if ($belumTerbit->isEmpty()) {
            throw CertificateBatchException::alreadyIssued();
        }

        $belumTerbit
            ->reject(fn (CertificateEventParticipant $participation) => $participation->isEligible())
            ->each(fn (CertificateEventParticipant $participation) => $participation->update(['eligible_at' => now()]));

        return $this->issueFor($event, $belumTerbit->values(), $notify, CertificateDesign::EXTERNAL);
    }

    private function inlineLimit(): int
    {
        return max(1, (int) config('msc.certificates.inline_issue_limit', self::INLINE_LIMIT));
    }

    /**
     * Kosongkan $participations untuk menerbitkan bagi seluruh peserta eligible.
     *
     * @param  Collection<int, CertificateEventParticipant>|null  $participations
     *
     * @throws CertificateBatchException
     */
    public function dispatchFor(
        CertificateEvent $event,
        ?Collection $participations = null,
        ?User $notify = null,
    ): Batch {
        $this->guardTemplate($event);

        $pending = $this->pending($event, $participations);

        if ($pending->isEmpty()) {
            throw CertificateBatchException::nothingToIssue();
        }

        return $this->dispatchBatch($event, $pending, $notify);
    }

    /**
     * Keikutsertaan yang benar-benar masih perlu diterbitkan.
     *
     * @param  Collection<int, CertificateEventParticipant>|null  $participations
     * @return Collection<int, CertificateEventParticipant>
     */
    private function pending(CertificateEvent $event, ?Collection $participations): Collection
    {
        return ($participations ?? $this->issuer->pendingFor($event)->get())
            ->filter(fn (CertificateEventParticipant $participation) => $participation->isEligible() && $participation->certificate === null)
            ->values();
    }

    /**
     * @param  Collection<int, CertificateEventParticipant>  $pending
     */
    private function dispatchBatch(
        CertificateEvent $event,
        Collection $pending,
        ?User $notify,
        CertificateDesign $design = CertificateDesign::TEMPLATE,
    ): Batch {
        return Bus::batch($pending->map(fn (CertificateEventParticipant $participation) => new IssueCertificateJob($participation, $design))->all())
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

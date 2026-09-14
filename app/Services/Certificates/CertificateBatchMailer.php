<?php

namespace App\Services\Certificates;

use App\Jobs\SendCertificateEmailJob;
use App\Models\Certificate;
use App\Models\CertificateEvent;
use App\Models\User;
use Filament\Notifications\Notification;
use Illuminate\Bus\Batch;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Bus;
use Throwable;

/**
 * Mengantrekan pengiriman email sertifikat sebagai satu batch.
 *
 * Penerbitan dan pengiriman sengaja dipisah: sertifikat terbit lebih dulu
 * secara digital — bisa diverifikasi dan diunduh — lalu emailnya menyusul
 * hanya ketika admin memintanya, supaya penerbitan yang keliru tidak
 * terlanjur mendarat di kotak masuk peserta.
 */
class CertificateBatchMailer
{
    /**
     * Kosongkan $certificates untuk mengirim ke seluruh penerima yang masih
     * menunggu.
     *
     * @param  Collection<int, Certificate>|null  $certificates
     *
     * @throws CertificateBatchException
     */
    public function dispatchFor(
        CertificateEvent $event,
        ?Collection $certificates = null,
        ?User $notify = null,
    ): Batch {
        // Tautan verifikasi di dalam email baru berfungsi setelah kegiatan
        // dipublikasikan, jadi lebih baik ditolak di sini daripada mengirim
        // email berisi tautan mati.
        if (! $event->isPublished()) {
            throw CertificateBatchException::eventNotPublished();
        }

        $pending = ($certificates ?? $event->certificates()->notYetEmailed()->get())
            ->filter(fn (Certificate $certificate) => $certificate->awaitsEmail())
            ->values();

        if ($pending->isEmpty()) {
            throw CertificateBatchException::nothingToEmail();
        }

        return Bus::batch($pending->map(fn (Certificate $certificate) => new SendCertificateEmailJob($certificate))->all())
            ->name("Pengiriman email sertifikat: {$event->name}")
            ->allowFailures()
            ->finally(fn (Batch $batch) => $this->announce($batch, $notify?->id))
            ->dispatch();
    }

    /**
     * Sertifikat yang sudah terbit tetapi emailnya belum dikirim. Dipakai
     * panel untuk menunjukkan berapa yang masih menunggu.
     */
    public function pendingCountFor(CertificateEvent $event): int
    {
        return $event->certificates()->notYetEmailed()->count();
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

            $sent = $batch->totalJobs - $batch->failedJobs;

            Notification::make()
                ->title('Pengiriman email sertifikat selesai')
                ->body("{$sent} email terkirim, {$batch->failedJobs} gagal.")
                ->status($batch->failedJobs > 0 ? 'warning' : 'success')
                ->sendToDatabase($recipient);
        } catch (Throwable) {
            // Notifikasi bersifat informatif; kegagalannya tidak boleh
            // menggagalkan batch yang sudah selesai.
        }
    }
}

<?php

namespace App\Jobs;

use App\Models\Certificate;
use App\Notifications\CertificateIssued;
use Illuminate\Bus\Batchable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Throwable;

/**
 * Mengirim satu email sertifikat. Idempotent lewat kolom `emailed_at`,
 * sehingga aman diulang oleh mekanisme retry antrean.
 */
class SendCertificateEmailJob implements ShouldQueue
{
    use Batchable;
    use Queueable;

    public int $tries = 3;

    /** @var array<int, int> */
    public array $backoff = [30, 120, 300];

    public function __construct(public Certificate $certificate) {}

    public function handle(): void
    {
        if ($this->batch()?->cancelled()) {
            return;
        }

        $certificate = $this->certificate->fresh(['event']);

        // Sudah terkirim, tidak punya alamat, dicabut, atau kegiatan belum
        // dipublikasikan — email ditunda sampai syaratnya terpenuhi.
        if ($certificate === null || ! $certificate->awaitsEmail()) {
            return;
        }

        Notification::route('mail', $certificate->recipient_email)
            ->notify(new CertificateIssued($certificate));

        $certificate->forceFill([
            'emailed_at' => now(),
            'email_failed_at' => null,
            'email_error' => null,
        ])->save();
    }

    public function failed(Throwable $exception): void
    {
        $this->certificate->forceFill([
            'email_failed_at' => now(),
            'email_error' => Str::limit($exception->getMessage(), 250),
        ])->save();

        Log::warning('Gagal mengirim email sertifikat.', [
            'certificate_id' => $this->certificate->id,
            'certificate_number' => $this->certificate->certificate_number,
            'exception' => $exception,
        ]);
    }
}

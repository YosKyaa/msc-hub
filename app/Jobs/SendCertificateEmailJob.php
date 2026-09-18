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

        try {
            Notification::route('mail', $certificate->recipient_email)
                ->notify(new CertificateIssued($certificate));
        } catch (Throwable $exception) {
            // Dicatat sekarang juga, bukan menunggu percobaan terakhir.
            // failed() baru berjalan setelah ketiga percobaan habis — tujuh
            // menit kemudian — dan tidak pernah berjalan sama sekali bila
            // antreannya memang tidak ada yang mengerjakan. Selama itu panel
            // hanya berkata "belum terkirim" tanpa menyebut sebabnya.
            $this->record($certificate, $exception, terakhir: false);

            throw $exception;
        }

        $certificate->forceFill([
            'emailed_at' => now(),
            'email_failed_at' => null,
            'email_error' => null,
        ])->save();
    }

    public function failed(Throwable $exception): void
    {
        $this->record($this->certificate, $exception, terakhir: true);
    }

    /**
     * Simpan sebabnya di sertifikatnya sekaligus di log.
     *
     * Kolomnya dibaca panel — admin melihat alasannya di tabel peserta tanpa
     * perlu membuka berkas log — sedangkan lognya yang dipakai menelusuri
     * kejadiannya di server.
     */
    private function record(Certificate $certificate, Throwable $exception, bool $terakhir): void
    {
        $pesan = Str::limit($exception->getMessage(), 250);

        $certificate->forceFill([
            'email_failed_at' => now(),
            'email_error' => $pesan,
        ])->save();

        $konteks = [
            'certificate_id' => $certificate->id,
            'certificate_number' => $certificate->certificate_number,
            'recipient_email' => $certificate->recipient_email,
            'attempt' => $this->attempts(),
            'error' => $pesan,
        ];

        // Percobaan yang masih akan diulang cukup dicatat sebagai peringatan;
        // yang benar-benar menyerah dicatat sebagai galat agar terlihat saat
        // log dipindai.
        $terakhir
            ? Log::error('Email sertifikat gagal terkirim dan tidak dicoba lagi.', $konteks + ['exception' => $exception])
            : Log::warning('Percobaan mengirim email sertifikat gagal.', $konteks);
    }
}

<?php

namespace App\Services\Certificates;

use App\Jobs\SendCertificateEmailJob;
use App\Models\Certificate;
use App\Models\CertificateEvent;
use App\Models\User;
use Filament\Notifications\Notification;
use Illuminate\Bus\Batch;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
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
    /** Awalan nama batch, dipakai juga untuk menghitung kiriman yang masih antre. */
    private const BATCH_PREFIX = 'Pengiriman email sertifikat: ';

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
    ): CertificateEmailDispatch {
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

        [$dikirim, $ditahan] = $this->withinDailyQuota($pending);

        $batch = Bus::batch($this->spacedJobs($dikirim))
            ->name(self::BATCH_PREFIX.$event->name)
            ->allowFailures()
            ->finally(fn (Batch $batch) => $this->announce($batch, $notify?->id))
            ->dispatch();

        return new CertificateEmailDispatch($batch, $ditahan->count(), $this->dailyLimit());
    }

    /**
     * Pisahkan yang masih muat dalam kuota harian dari yang harus menunggu.
     *
     * Yang ditahan tidak diapa-apakan: penanda kirimnya tetap kosong, jadi ia
     * tetap berstatus menunggu kirim dan ikut terambil saat tombol Kirim
     * Email ditekan lagi.
     *
     * @param  Collection<int, Certificate>  $pending
     * @return array{0: Collection<int, Certificate>, 1: Collection<int, Certificate>}
     *
     * @throws CertificateBatchException
     */
    private function withinDailyQuota(Collection $pending): array
    {
        $sisa = $this->remainingToday();

        if ($sisa === null) {
            return [$pending, collect()];
        }

        if ($sisa === 0) {
            throw CertificateBatchException::dailyLimitReached($this->dailyLimit(), $this->quotaFreesAtLabel());
        }

        return [$pending->take($sisa)->values(), $pending->slice($sisa)->values()];
    }

    /**
     * Batas email per 24 jam bagi akun pengirim, atau null bila tanpa batas.
     */
    public function dailyLimit(): ?int
    {
        $batas = (int) config('msc.certificates.daily_email_limit', 1800);

        return $batas > 0 ? $batas : null;
    }

    /**
     * Sisa kuota dalam 24 jam bergulir, atau null bila tanpa batas.
     */
    public function remainingToday(): ?int
    {
        $batas = $this->dailyLimit();

        return $batas === null ? null : max(0, $batas - $this->usedToday());
    }

    /**
     * Kuota yang sudah terpakai: yang terkirim dalam 24 jam terakhir, ditambah
     * yang sudah diantrekan tetapi belum berangkat.
     *
     * Yang masih antre harus ikut dihitung. Tanpa itu, menekan Kirim Email di
     * dua kegiatan berturut-turut melihat kuota yang sama masih utuh, padahal
     * kiriman yang pertama belum sempat mengisi `emailed_at`.
     *
     * Hanya batch yang dibuat dalam sehari terakhir yang dihitung, supaya
     * antrean yang telanjur dikosongkan dengan tangan tidak menyumbat kuota
     * selamanya.
     */
    public function usedToday(): int
    {
        $terkirim = Certificate::query()
            ->where('emailed_at', '>=', now()->subDay())
            ->count();

        $antre = DB::table('job_batches')
            ->where('name', 'like', self::BATCH_PREFIX.'%')
            ->whereNull('finished_at')
            ->whereNull('cancelled_at')
            ->where('created_at', '>=', now()->subDay()->getTimestamp())
            ->sum(DB::raw('pending_jobs - failed_jobs'));

        return $terkirim + (int) $antre;
    }

    /**
     * Kapan kuota mulai terbuka lagi, dalam kalimat yang bisa langsung dibaca.
     *
     * Jendelanya bergulir, bukan direset tengah malam: kuota pertama yang
     * terbuka adalah milik email tertua dalam 24 jam terakhir.
     */
    public function quotaFreesAtLabel(): string
    {
        $tertua = Certificate::query()
            ->where('emailed_at', '>=', now()->subDay())
            ->min('emailed_at');

        if ($tertua === null) {
            return 'setelah pengiriman yang sedang berjalan selesai';
        }

        return 'sekitar '.Carbon::parse($tertua)->addDay()->translatedFormat('j F Y, H:i');
    }

    /**
     * Jarakkan kirimannya agar penyedia SMTP tidak menolak.
     *
     * Batch yang dilepas sekaligus akan dikerjakan pekerja antrean secepat
     * yang ia bisa, dan hampir semua penyedia menolak kiriman serapat itu —
     * milik kampus menjawab "550 Too many emails per second" lalu
     * menggugurkan sisanya. Menahan tiap email beberapa detik membuat
     * seluruhnya sampai, meski totalnya lebih lama.
     *
     * @param  Collection<int, Certificate>  $pending
     * @return list<SendCertificateEmailJob>
     */
    private function spacedJobs(Collection $pending): array
    {
        $perMenit = max(1, (int) config('msc.certificates.emails_per_minute', 20));
        $jeda = 60 / $perMenit;

        return $pending
            ->values()
            ->map(fn (Certificate $certificate, int $urutan) => (new SendCertificateEmailJob($certificate))
                ->delay(now()->addSeconds((int) round($urutan * $jeda))))
            ->all();
    }

    /**
     * Perkiraan lama pengiriman seluruhnya, supaya admin tahu apakah harus
     * menunggu semenit atau setengah jam.
     */
    public function estimatedMinutesFor(int $jumlah): int
    {
        $perMenit = max(1, (int) config('msc.certificates.emails_per_minute', 20));

        return (int) max(1, ceil($jumlah / $perMenit));
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

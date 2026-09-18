<?php

namespace App\Services\Certificates;

use App\Models\Certificate;
use App\Models\CertificateEvent;
use App\Notifications\CertificateIssued;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Throwable;

/**
 * Kirim satu email percobaan, langsung, tanpa antrean.
 *
 * Tanpa ini satu-satunya cara memastikan email benar-benar jalan adalah
 * menerbitkan sertifikat sungguhan lalu menekan kirim — dan bila SMTP-nya
 * ternyata salah setel, kegagalannya baru ketahuan setelah seluruh peserta
 * seharusnya sudah menerima. Percobaan ini menempuh jalur yang sama persis:
 * templat, kop penerbit, dan sambungan SMTP yang sama.
 *
 * Dijalankan seketika dan bukan lewat antrean, justru supaya galatnya bisa
 * ditangkap dan ditunjukkan apa adanya kepada admin.
 */
class CertificateMailProbe
{
    /**
     * @return string Pesan galat dari server email, atau null bila berhasil.
     */
    public function send(CertificateEvent $event, string $email): ?string
    {
        try {
            Notification::route('mail', $email)
                ->notify(new CertificateIssued($this->sample($event, $email)));
        } catch (Throwable $exception) {
            Log::error('Email percobaan sertifikat gagal terkirim.', [
                'certificate_event_id' => $event->id,
                'recipient_email' => $email,
                'error' => $exception->getMessage(),
                'exception' => $exception,
            ]);

            return Str::limit($exception->getMessage(), 400);
        }

        Log::info('Email percobaan sertifikat terkirim.', [
            'certificate_event_id' => $event->id,
            'recipient_email' => $email,
        ]);

        return null;
    }

    /**
     * Sertifikat sungguhan bila kegiatannya sudah punya, supaya tautan unduh
     * dan verifikasinya ikut bisa dicoba. Bila belum ada, dipakai contoh yang
     * tidak disimpan — cukup untuk membuktikan templat dan SMTP-nya jalan.
     */
    private function sample(CertificateEvent $event, string $email): Certificate
    {
        $nyata = $event->certificates()->whereNull('revoked_at')->latest('id')->first();

        if ($nyata !== null) {
            return $nyata->setRelation('event', $event);
        }

        $contoh = new Certificate([
            'certificate_event_id' => $event->id,
            'certificate_number' => 'CONTOH/UJI-COBA/0001',
            'recipient_name' => 'Contoh Penerima',
            'recipient_email' => $email,
            'recipient_role' => 'participant',
            'recipient_role_label' => 'Peserta',
            'issued_at' => now(),
        ]);

        // Tokennya diperlukan untuk menyusun tautan unduh dan verifikasi.
        // Tautannya memang belum menuju ke mana-mana — yang sedang diuji
        // adalah sambungan email dan tampilan templatnya, bukan sertifikatnya.
        $contoh->verification_token = 'contoh-'.Str::random(24);

        return $contoh->setRelation('event', $event);
    }
}

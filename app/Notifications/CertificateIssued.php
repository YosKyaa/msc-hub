<?php

namespace App\Notifications;

use App\Models\Certificate;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Pemberitahuan sertifikat siap diunduh.
 *
 * Notifikasi ini sengaja TIDAK ShouldQueue: pengirimannya sudah dijalankan dari
 * dalam SendCertificateEmailJob yang antre, sehingga `emailed_at` baru diisi
 * setelah email benar-benar terkirim.
 */
class CertificateIssued extends Notification
{
    public function __construct(public Certificate $certificate) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $certificate = $this->certificate;
        $event = $certificate->event;
        $issuer = $event->resolvedIssuer();

        return (new MailMessage)
            ->subject("[MSC Hub] Sertifikat {$event->name} Sudah Terbit")
            ->view('emails.notification', [
                // Kop email mengikuti penerbit, sehingga sertifikat mitra tidak
                // sampai ke peserta sebagai terbitan JGU.
                'brandName' => $issuer?->name,
                'brandLogo' => $issuer?->logoUrl(),
                'brandTagline' => 'PENERBIT SERTIFIKAT',
                'brandFooter' => $issuer?->name,
                'badge' => 'Sertifikat terbit',
                'title' => 'Sertifikat Anda sudah dapat diunduh',
                'greeting' => "Halo, {$certificate->recipient_name}",
                'intro' => "Terima kasih atas partisipasi Anda pada {$event->name}. Sertifikat Anda telah diterbitkan dan dapat diunduh melalui tautan di bawah ini.",
                'details' => [
                    'Nomor sertifikat' => $certificate->certificate_number,
                    'Nama penerima' => $certificate->recipient_name,
                    'Peran' => $certificate->recipient_role_label ?: $certificate->recipient_role,
                    'Kegiatan' => $event->name,
                    'Tanggal kegiatan' => $event->event_date->translatedFormat('d F Y'),
                    'Penyelenggara' => $event->organizer ?: $issuer?->name,
                    'Diterbitkan oleh' => $issuer?->name,
                ],
                'status' => 'Sertifikat aktif',
                'statusTone' => 'success',
                'note' => 'Keaslian sertifikat dapat diperiksa kapan saja melalui QR pada dokumen atau tautan verifikasi berikut: '.$certificate->verificationUrl(),
                'actionUrl' => $certificate->downloadUrl(),
                'actionText' => 'Unduh sertifikat',
                'secondaryActionUrl' => $certificate->verificationUrl(),
                'secondaryActionText' => 'Verifikasi keaslian',
            ]);
    }
}

<?php

namespace App\Services;

use App\Models\Certificate;
use App\Models\CertificateTemplate;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class CertificateRenderService
{
    public function __construct(private readonly QrCodeGenerator $qrCodes) {}

    public function variables(Certificate $certificate): array
    {
        $event = $certificate->event;

        return [
            'recipient_name' => $certificate->recipient_name,
            'recipient_role' => $certificate->recipient_role_label ?: ucfirst($certificate->recipient_role),
            'certificate_number' => $certificate->certificate_number,
            'event_name' => $event->name,
            'event_date' => $event->event_date->locale('id')->translatedFormat('d F Y'),
            // Penyelenggara yang dicetak; bila dikosongkan memakai nama penerbit.
            'organizer' => $event->organizer ?: $event->resolvedIssuer()?->name,
            'signatory_name' => $event->signatory_name,
            'signatory_title' => $event->signatory_title,
            'verification_url' => route('certificates.verify', $certificate->verification_token),
            ...($certificate->variables ?? []),
        ];
    }

    public function qrDataUri(Certificate $certificate): string
    {
        return $this->qrCodes->dataUri(route('certificates.verify', $certificate->verification_token));
    }

    public function pdf(Certificate $certificate)
    {
        $certificate->loadMissing('event.template');
        $template = $certificate->event?->template;

        if ($template === null) {
            throw new RuntimeException('Kegiatan ini tidak lagi memakai template sertifikat, sehingga PDF-nya tidak bisa dicetak.');
        }

        return Pdf::loadView('certificates.pdf', [
            'certificate' => $certificate,
            'template' => $template,
            'values' => $this->variables($certificate),
            'qrDataUri' => $this->qrDataUri($certificate),
            'backgroundDataUri' => $this->backgroundDataUri($template),
        ])->setPaper([0, 0, $template->canvas_width * .75, $template->canvas_height * .75]);
    }

    /**
     * Desain latarnya hiasan, bukan isinya: nama, nomor, dan QR tetap sah
     * tanpa gambar itu. Berkas yang hilang — misalnya karena storage tidak
     * ikut terbawa saat rilis — karena itu dicatat lalu dilewati, bukan
     * membuat setiap unduhan gagal.
     */
    private function backgroundDataUri(CertificateTemplate $template): ?string
    {
        $path = (string) $template->background_path;

        if ($path === '' || ! Storage::disk('public')->exists($path)) {
            Log::warning('Desain latar sertifikat tidak ditemukan; PDF dicetak tanpa latar.', [
                'certificate_template_id' => $template->id,
                'background_path' => $path,
            ]);

            return null;
        }

        $absolute = Storage::disk('public')->path($path);
        $mime = mime_content_type($absolute) ?: 'image/png';

        return 'data:'.$mime.';base64,'.base64_encode((string) file_get_contents($absolute));
    }
}

<?php

namespace App\Services;

use App\Models\Certificate;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;

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
            'event_date' => $event->event_date->translatedFormat('d F Y'),
            'organizer' => $event->organizer,
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
        $template = $certificate->event->template;
        $backgroundPath = Storage::disk('public')->path($template->background_path);
        $backgroundMime = mime_content_type($backgroundPath) ?: 'image/png';

        return Pdf::loadView('certificates.pdf', [
            'certificate' => $certificate,
            'template' => $template,
            'values' => $this->variables($certificate),
            'qrDataUri' => $this->qrDataUri($certificate),
            'backgroundDataUri' => 'data:'.$backgroundMime.';base64,'.base64_encode(file_get_contents($backgroundPath)),
        ])->setPaper([0, 0, $template->canvas_width * .75, $template->canvas_height * .75]);
    }
}

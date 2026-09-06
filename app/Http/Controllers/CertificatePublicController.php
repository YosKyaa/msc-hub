<?php

namespace App\Http\Controllers;

use App\Models\Certificate;
use App\Services\CertificateRenderService;

class CertificatePublicController extends Controller
{
    public function verify(string $token, CertificateRenderService $renderer)
    {
        $certificate = Certificate::with(['event.template', 'event.issuer'])
            ->where('verification_token', $token)
            ->firstOrFail();

        // Pratinjau memakai variabel dan QR yang sama dengan PDF, sehingga yang
        // terlihat di halaman verifikasi identik dengan berkas yang diunduh.
        return view('certificates.verify', [
            'certificate' => $certificate,
            'issuer' => $certificate->event->resolvedIssuer(),
            'values' => $renderer->variables($certificate),
            'qrDataUri' => $renderer->qrDataUri($certificate),
        ]);
    }

    public function download(string $token, CertificateRenderService $renderer)
    {
        $certificate = Certificate::with('event.template')
            ->where('verification_token', $token)
            ->firstOrFail();

        abort_unless($certificate->isValid(), 404);

        return $renderer->pdf($certificate)->download($certificate->certificate_number.'.pdf');
    }
}

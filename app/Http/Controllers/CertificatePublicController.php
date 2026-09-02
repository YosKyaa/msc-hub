<?php

namespace App\Http\Controllers;

use App\Models\Certificate;
use App\Services\CertificateRenderService;

class CertificatePublicController extends Controller
{
    public function verify(string $token)
    {
        $certificate = Certificate::with('event.template')->where('verification_token', $token)->firstOrFail();

        return view('certificates.verify', compact('certificate'));
    }

    public function download(string $token, CertificateRenderService $renderer)
    {
        $certificate = Certificate::with('event.template')->where('verification_token', $token)->firstOrFail();

        abort_unless($certificate->isValid(), 404);

        return $renderer->pdf($certificate)->download($certificate->certificate_number.'.pdf');
    }
}

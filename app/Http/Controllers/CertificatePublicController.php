<?php

namespace App\Http\Controllers;

use App\Models\Certificate;
use App\Services\CertificateRenderService;
use App\Services\Certificates\CertificateDesignFile;
use Illuminate\Support\Facades\Storage;

class CertificatePublicController extends Controller
{
    public function verify(string $token, CertificateRenderService $renderer)
    {
        $certificate = Certificate::with(['event.template', 'event.issuer'])
            ->where('verification_token', $token)
            ->firstOrFail();

        // Pratinjau memakai variabel dan QR yang sama dengan PDF, sehingga yang
        // terlihat di halaman verifikasi identik dengan berkas yang diunduh.
        // Sertifikat desain sendiri ditampilkan dari berkas jadinya.
        return view('certificates.verify', [
            'certificate' => $certificate,
            'issuer' => $certificate->event->resolvedIssuer(),
            'values' => $renderer->variables($certificate),
            'qrDataUri' => $renderer->qrDataUri($certificate),
            'designUrl' => $certificate->isOwnDesign() && CertificateDesignFile::exists($certificate)
                ? route('certificates.design', $certificate->verification_token)
                : null,
        ]);
    }

    public function download(string $token, CertificateRenderService $renderer)
    {
        $certificate = Certificate::with('event.template')
            ->where('verification_token', $token)
            ->firstOrFail();

        abort_unless($certificate->isDeliverable(), 404);

        if ($certificate->isOwnDesign()) {
            abort_unless(CertificateDesignFile::exists($certificate), 404);

            return Storage::disk(CertificateDesignFile::DISK)
                ->download($certificate->design_file_path, $certificate->downloadFileName());
        }

        return $renderer->pdf($certificate)->download($certificate->downloadFileName());
    }

    /**
     * Berkas jadi sertifikat desain sendiri, untuk ditampilkan di halaman
     * verifikasinya. Seperti pratinjau template, ia tetap bisa dilihat walau
     * sertifikatnya tidak berlaku — halamannya menampilkannya pudar dengan cap
     * Tidak Berlaku — tetapi hanya sertifikat yang berlaku yang bisa diunduh.
     */
    public function design(string $token)
    {
        $certificate = Certificate::where('verification_token', $token)->firstOrFail();

        abort_unless($certificate->isOwnDesign() && CertificateDesignFile::exists($certificate), 404);

        return Storage::disk(CertificateDesignFile::DISK)->response(
            $certificate->design_file_path,
            $certificate->downloadFileName(),
            ['Cache-Control' => 'private, max-age=300', 'X-Robots-Tag' => 'noindex'],
        );
    }
}

<?php

namespace App\Services;

use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;

/**
 * Satu tempat konfigurasi QR agar sertifikat dan poster absensi konsisten.
 */
class QrCodeGenerator
{
    public function dataUri(string $content, int $scale = 6): string
    {
        $options = new QROptions([
            'outputType' => QRCode::OUTPUT_IMAGE_PNG,
            'outputBase64' => true,
            'scale' => $scale,
            'imageTransparent' => false,
        ]);

        return (new QRCode($options))->render($content);
    }
}

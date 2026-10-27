<?php

namespace App\Services;

use chillerlan\QRCode\Common\EccLevel;
use chillerlan\QRCode\Output\QROutputInterface;
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

    /**
     * PNG untuk ditempel di desain buatan sendiri: cukup besar untuk dicetak
     * tajam, dan memakai koreksi galat tingkat M, yang lebih tahan terhadap
     * cetakan buram atau goresan daripada bawaannya.
     */
    public function printablePng(string $content, int $scale = 32): string
    {
        return (new QRCode(new QROptions([
            'outputType' => QROutputInterface::GDIMAGE_PNG,
            'outputBase64' => false,
            'scale' => $scale,
            'eccLevel' => EccLevel::M,
            'imageTransparent' => false,
        ])))->render($content);
    }

    /**
     * SVG berlatar putih, termasuk tepi kosongnya: vektor, jadi tetap tajam
     * di ukuran berapa pun di Canva, Illustrator, maupun Photoshop.
     */
    public function printableSvg(string $content): string
    {
        $svg = (new QRCode(new QROptions([
            'outputType' => QROutputInterface::MARKUP_SVG,
            'outputBase64' => false,
            'eccLevel' => EccLevel::M,
            'drawLightModules' => true,
            'svgAddXmlHeader' => true,
        ])))->render($content);

        // Tanpa ukuran, aplikasi desain menaruhnya sekecil kotak modulnya.
        // Tiga sentimeter sudah di atas batas aman terkecil untuk dipindai.
        return (string) preg_replace('/<svg\b/', '<svg width="30mm" height="30mm"', $svg, 1);
    }
}

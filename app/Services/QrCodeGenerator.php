<?php

namespace App\Services;

use chillerlan\QRCode\Common\EccLevel;
use chillerlan\QRCode\Output\QROutputInterface;
use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;
use GdImage;

/**
 * Satu tempat konfigurasi QR agar sertifikat dan poster absensi konsisten.
 */
class QrCodeGenerator
{
    /**
     * Lambang JGU yang ditaruh di tengah QR, seperti QR buatan S JGU.
     * Dipangkas dari img/jgusolo.png, supaya tidak perlu dipangkas setiap
     * kali QR dibuat.
     */
    private const LOGO_PATH = 'img/jgu-qr-logo.png';

    /**
     * Lebar bidang logo dibanding lebar QR tanpa tepi kosongnya. Bidang itu
     * menutup sekitar 7% modul, jauh di bawah 30% yang sanggup dipulihkan
     * koreksi galat tingkat H.
     */
    private const LOGO_SHARE = 0.24;

    private ?GdImage $logo = null;

    /**
     * QR berlogo JGU untuk sertifikat, halaman verifikasi, dan poster absensi.
     *
     * Logo menutup sebagian modul, jadi koreksi galatnya dinaikkan ke tingkat
     * H, satu-satunya tingkat yang diizinkan pustakanya untuk logo. QR-nya
     * sedikit lebih rapat, tetapi isinya tetap tautan yang sama, sehingga QR
     * pada sertifikat yang sudah tercetak tetap berlaku.
     */
    public function dataUri(string $content, int $scale = 6): string
    {
        $qrcode = new QRCode(new QROptions([
            'outputType' => QROutputInterface::GDIMAGE_PNG,
            'eccLevel' => EccLevel::H,
            'scale' => $scale,
            'imageTransparent' => false,
            'returnResource' => true,
        ]));

        $matrix = $qrcode->addByteSegment($content)->getQRMatrix();

        // Ganjil, supaya bidangnya tepat di tengah.
        $bidang = (int) round($matrix->getVersion()->getDimension() * self::LOGO_SHARE);
        $bidang += $bidang % 2 === 0 ? 1 : 0;
        $matrix->setLogoSpace($bidang);

        $image = $qrcode->renderMatrix($matrix);
        $this->drawLogo($image, ($bidang - 2) * $scale);

        ob_start();
        imagepng($image);

        return 'data:image/png;base64,'.base64_encode((string) ob_get_clean());
    }

    /**
     * Logo di tengah bidang yang sudah dikosongkan, dengan sisa satu modul
     * putih di sekelilingnya supaya tidak menempel pada modul QR.
     */
    private function drawLogo(GdImage $image, int $ruang): void
    {
        $logo = $this->logo ??= imagecreatefrompng(public_path(self::LOGO_PATH));
        $skala = min($ruang / imagesx($logo), $ruang / imagesy($logo));
        $lebar = (int) round(imagesx($logo) * $skala);
        $tinggi = (int) round(imagesy($logo) * $skala);

        imagecopyresampled(
            $image, $logo,
            intdiv(imagesx($image) - $lebar, 2), intdiv(imagesy($image) - $tinggi, 2),
            0, 0,
            $lebar, $tinggi,
            imagesx($logo), imagesy($logo),
        );
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

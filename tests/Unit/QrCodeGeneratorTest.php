<?php

namespace Tests\Unit;

use App\Services\QrCodeGenerator;
use chillerlan\QRCode\Common\EccLevel;
use chillerlan\QRCode\QRCode;
use Tests\TestCase;

/**
 * QR di sertifikat, halaman verifikasi, dan poster absensi membawa logo JGU
 * di tengahnya, seperti QR buatan S JGU, dan tetap terbaca sebagai tautan
 * yang sama.
 */
class QrCodeGeneratorTest extends TestCase
{
    private const URL = 'https://msc.jgu.ac.id/verify/certificate/9b2f6c1e-3d4a-4f5b-8c7d-1a2b3c4d5e6f';

    private function png(): string
    {
        $uri = app(QrCodeGenerator::class)->dataUri(self::URL);

        $this->assertStringStartsWith('data:image/png;base64,', $uri);

        return (string) base64_decode(substr($uri, strlen('data:image/png;base64,')));
    }

    private function read(string $png): string
    {
        return (new QRCode)->readFromBlob($png)->data;
    }

    public function test_the_qr_reads_back_as_the_same_link(): void
    {
        $this->assertSame(self::URL, $this->read($this->png()));
    }

    public function test_the_jgu_logo_sits_in_the_centre(): void
    {
        $image = imagecreatefromstring($this->png());
        $tengah = intdiv(imagesx($image), 2);
        $merah = 0;

        // QR polos hanya hitam dan putih; merah di sekitar titik tengahnya
        // berasal dari lambang JGU.
        for ($x = $tengah - 10; $x <= $tengah + 10; $x++) {
            for ($y = $tengah - 10; $y <= $tengah + 10; $y++) {
                $warna = imagecolorat($image, $x, $y);

                if (($warna >> 16 & 0xFF) > 180 && ($warna >> 8 & 0xFF) < 90 && ($warna & 0xFF) < 90) {
                    $merah++;
                }
            }
        }

        $this->assertGreaterThan(50, $merah, 'Logo JGU tidak tergambar di tengah QR.');
    }

    /**
     * Logo menutup sebagian modul, dan hanya koreksi galat tingkat H yang
     * cukup longgar untuk memulihkannya sambil tetap tahan cetakan buram.
     */
    public function test_the_logo_is_backed_by_the_highest_error_correction(): void
    {
        $this->assertSame(EccLevel::H, (new QRCode)->readFromBlob($this->png())->eccLevel->getLevel());
    }

    /**
     * QR berlogo lebih rapat daripada QR polos. Diperkecil sampai 160 piksel,
     * kurang dari tiga piksel per modul, ia harus tetap terbaca.
     */
    public function test_it_still_reads_back_when_shown_small(): void
    {
        $kecil = imagescale(imagecreatefromstring($this->png()), 160, 160, IMG_BICUBIC);

        ob_start();
        imagepng($kecil);

        $this->assertSame(self::URL, $this->read((string) ob_get_clean()));
    }
}

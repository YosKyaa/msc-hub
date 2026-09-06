<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * Logo kop surat untuk dokumen PDF.
 *
 * Berkas aslinya beresolusi tinggi, sementara di kertas hanya dicetak selebar
 * ~54 px. Menyematkannya apa adanya membuat setiap bukti booking membengkak
 * ratusan kilobyte, jadi logo diperkecil sekali lalu disimpan di cache.
 */
class PdfLetterhead
{
    private const CACHE_KEY = 'pdf.letterhead-logo';

    private const FOOTER_CACHE_KEY = 'pdf.letterhead-footer';

    /** Lebar cukup untuk cetak tajam pada 300 dpi. */
    private const TARGET_WIDTH = 220;

    /** Pita kaki surat membentang selebar kertas, jadi perlu lebih lebar. */
    private const FOOTER_WIDTH = 1000;

    public static function logoDataUri(): ?string
    {
        return Cache::rememberForever(self::CACHE_KEY, function (): ?string {
            $path = public_path('img/jgu.png');

            if (! is_file($path)) {
                return null;
            }

            $encoded = static::downscale($path, self::TARGET_WIDTH) ?? file_get_contents($path);

            return 'data:image/png;base64,'.base64_encode($encoded);
        });
    }

    /**
     * Pita kaki surat resmi JGU: alamat kampus dan baris kontak.
     */
    public static function footerDataUri(): ?string
    {
        return Cache::rememberForever(self::FOOTER_CACHE_KEY, function (): ?string {
            $path = public_path('img/jgu-letterhead-footer.png');

            if (! is_file($path)) {
                return null;
            }

            $encoded = static::downscale($path, self::FOOTER_WIDTH) ?? file_get_contents($path);

            return 'data:image/png;base64,'.base64_encode($encoded);
        });
    }

    public static function forget(): void
    {
        Cache::forget(self::CACHE_KEY);
        Cache::forget(self::FOOTER_CACHE_KEY);
    }

    /**
     * Kembalikan PNG yang sudah diperkecil, atau null bila GD tidak sanggup
     * memprosesnya sehingga pemanggil memakai berkas aslinya.
     */
    private static function downscale(string $path, int $targetWidth): ?string
    {
        if (! function_exists('imagecreatefrompng')) {
            return null;
        }

        try {
            $source = @imagecreatefrompng($path);

            if ($source === false || imagesx($source) <= $targetWidth) {
                return null;
            }

            $resized = imagescale($source, $targetWidth);
            imagedestroy($source);

            if ($resized === false) {
                return null;
            }

            imagealphablending($resized, false);
            imagesavealpha($resized, true);

            ob_start();
            imagepng($resized, null, 9);
            imagedestroy($resized);

            return (string) ob_get_clean();
        } catch (Throwable) {
            return null;
        }
    }
}

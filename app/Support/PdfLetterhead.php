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

    /** Lebar cukup untuk cetak tajam pada 300 dpi. */
    private const TARGET_WIDTH = 220;

    public static function logoDataUri(): ?string
    {
        return Cache::rememberForever(self::CACHE_KEY, function (): ?string {
            $path = public_path('img/jgu.png');

            if (! is_file($path)) {
                return null;
            }

            $encoded = static::downscale($path) ?? file_get_contents($path);

            return 'data:image/png;base64,'.base64_encode($encoded);
        });
    }

    public static function forget(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    /**
     * Kembalikan PNG yang sudah diperkecil, atau null bila GD tidak sanggup
     * memprosesnya sehingga pemanggil memakai berkas aslinya.
     */
    private static function downscale(string $path): ?string
    {
        if (! function_exists('imagecreatefrompng')) {
            return null;
        }

        try {
            $source = @imagecreatefrompng($path);

            if ($source === false || imagesx($source) <= self::TARGET_WIDTH) {
                return null;
            }

            $resized = imagescale($source, self::TARGET_WIDTH);
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

<?php

namespace App\Support;

use App\Models\CertificateTemplate;
use Dompdf\FontMetrics;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Mengukur lebar teks sebagaimana dompdf akan menggambarnya.
 *
 * Diukur dengan metrik dompdf sendiri, bukan perkiraan: PDF-lah dokumen
 * resminya, dan hanya dompdf yang mengenal semua font yang bisa dipilih di
 * editor — DejaVu Sans, font bawaan PDF seperti Times dan Helvetica yang tidak
 * punya berkas TTF, maupun font yang diunggah per template.
 */
class CertificateTextMeasurer
{
    private ?FontMetrics $metrics = null;

    /** @var array<string, true> */
    private array $registered = [];

    /**
     * Lebar teks dalam satuan yang sama dengan ukuran hurufnya: diukur pada
     * ukuran 28 piksel kanvas, hasilnya piksel kanvas.
     */
    public function width(string $text, string $family, int $weight, float $size, ?CertificateTemplate $template = null): float
    {
        $metrics = $this->metrics();

        if ($template !== null) {
            $this->registerTemplateFonts($metrics, $template);
        }

        $subtype = $weight >= 600 ? 'bold' : 'normal';
        $font = $metrics->getFont($family, $subtype)
            ?? $metrics->getFont(CertificateElement::DEFAULT_FONT_FAMILY, $subtype);

        return $metrics->getTextWidth($text, $font, $size);
    }

    private function metrics(): FontMetrics
    {
        return $this->metrics ??= app('dompdf.wrapper')->getDomPDF()->getFontMetrics();
    }

    /**
     * Font unggahan baru dikenal dompdf setelah didaftarkan. PDF
     * mendaftarkannya lewat @font-face saat menggambar; pengukuran berjalan
     * sebelum itu, jadi didaftarkan di sini dengan nama dan berkas yang sama.
     */
    private function registerTemplateFonts(FontMetrics $metrics, CertificateTemplate $template): void
    {
        foreach ($template->fonts ?? [] as $path) {
            if (isset($this->registered[$path]) || ! Storage::disk('public')->exists($path)) {
                continue;
            }

            $this->registered[$path] = true;
            $family = pathinfo($path, PATHINFO_FILENAME);

            if ($metrics->getFamily($family) !== null) {
                continue;
            }

            try {
                $metrics->registerFont(
                    ['family' => $family, 'weight' => 'normal', 'style' => 'normal'],
                    'file:///'.str_replace('\\', '/', Storage::disk('public')->path($path)),
                );
            } catch (Throwable) {
                // Font yang rusak tidak boleh menggagalkan pencetakan: ukurannya
                // diukur dengan font bawaan, sama seperti dompdf akan menggambarnya.
            }
        }
    }
}

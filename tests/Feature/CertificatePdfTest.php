<?php

namespace Tests\Feature;

use App\Models\Certificate;
use App\Models\CertificateEvent;
use App\Models\CertificateTemplate;
use App\Services\CertificateRenderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Regresi desain sertifikat hilang.
 *
 * Dompdf gagal mem-parsing `url('data:...')` bertanda kutip tunggal di dalam
 * blok <style> dan diam-diam membuang seluruh aturan setelahnya. Akibatnya
 * `.element { position:absolute }` ikut hilang: latar tidak tergambar dan semua
 * elemen menumpuk di kiri atas halaman. Test ini memeriksa operator PDF yang
 * benar-benar dihasilkan, bukan sekadar bahwa PDF berhasil dibuat.
 */
class CertificatePdfTest extends TestCase
{
    use RefreshDatabase;

    /** Kanvas 1123x794 px pada 96 dpi menjadi 842.25 x 595.5 pt. */
    private const PAGE_WIDTH_PT = 842.25;

    private const PAGE_HEIGHT_PT = 595.5;

    private function certificate(): Certificate
    {
        Storage::fake('public');
        Storage::disk('public')->put('certificates/templates/uji.png', $this->pngBytes());

        $template = CertificateTemplate::factory()->create([
            'background_path' => 'certificates/templates/uji.png',
            'canvas_width' => 1123,
            'canvas_height' => 794,
            'elements' => [
                [
                    'variable' => 'recipient_name',
                    'x' => 325, 'y' => 313, 'width' => 449, 'height' => 60,
                    'align' => 'left', 'font_size' => 28,
                ],
                [
                    'variable' => 'qr_code',
                    'x' => 89, 'y' => 406, 'width' => 120, 'height' => 120,
                ],
            ],
        ]);

        $event = CertificateEvent::factory()->published()->create([
            'certificate_template_id' => $template->id,
        ]);

        return Certificate::factory()->create([
            'certificate_event_id' => $event->id,
            'recipient_name' => 'Budi Santoso',
        ]);
    }

    private function pngBytes(): string
    {
        $image = imagecreatetruecolor(240, 170);
        imagefill($image, 0, 0, imagecolorallocate($image, 12, 74, 160));

        ob_start();
        imagepng($image);

        return (string) ob_get_clean();
    }

    /**
     * Content stream halaman berisi operator penggambaran; isinya terkompresi.
     */
    private function pageContentStream(string $pdf): string
    {
        preg_match_all('/stream\r?\n(.*?)\r?\nendstream/s', $pdf, $streams);

        foreach ($streams[1] as $stream) {
            $plain = @gzuncompress($stream) ?: $stream;

            if (str_contains($plain, ' Do') && str_contains($plain, 'TJ')) {
                return $plain;
            }
        }

        return '';
    }

    public function test_the_background_fills_the_whole_page_and_is_painted_behind_the_text(): void
    {
        $content = $this->pageContentStream(
            app(CertificateRenderService::class)->pdf($this->certificate())->output()
        );

        $this->assertNotSame('', $content, 'Content stream halaman tidak ditemukan.');

        $background = sprintf(
            '/%s 0 0 %s 0\.000 0\.000 cm \/I\d+ Do/',
            preg_quote(number_format(self::PAGE_WIDTH_PT, 3), '/'),
            preg_quote(number_format(self::PAGE_HEIGHT_PT, 3), '/'),
        );

        $this->assertMatchesRegularExpression(
            $background,
            $content,
            'Latar template tidak digambar sepenuh halaman.',
        );

        // Latar harus tergambar lebih dulu agar teks berada di atasnya.
        $this->assertLessThan(
            strpos($content, 'TJ'),
            preg_match($background, $content, $match, PREG_OFFSET_CAPTURE) ? $match[0][1] : PHP_INT_MAX,
            'Latar digambar setelah teks sehingga menutupi isi sertifikat.',
        );
    }

    public function test_elements_are_drawn_at_their_configured_absolute_position(): void
    {
        $content = $this->pageContentStream(
            app(CertificateRenderService::class)->pdf($this->certificate())->output()
        );

        // Teks rata kiri mulai tepat di tepi kiri kotaknya: 325 px = 243.75 pt.
        $this->assertMatchesRegularExpression(
            '/BT 243\.750 [\d.]+ Td/',
            $content,
            'Nama penerima tidak berada di koordinat absolut yang diatur template.',
        );

        // QR: 89 px = 66.75 pt dari kiri, 120 px = 90 pt persegi.
        $this->assertMatchesRegularExpression(
            '/90\.000 0 0 90\.000 66\.750 [\d.]+ cm \/I\d+ Do/',
            $content,
            'Elemen QR tidak berada di koordinat absolut yang diatur template.',
        );
    }

    public function test_no_element_collapses_to_the_page_origin(): void
    {
        $content = $this->pageContentStream(
            app(CertificateRenderService::class)->pdf($this->certificate())->output()
        );

        // Gejala khas stylesheet yang dibuang: semua elemen menumpuk di x = 0.
        $this->assertDoesNotMatchRegularExpression(
            '/BT 0\.000 [\d.]+ Td/',
            $content,
            'Elemen menumpuk di kiri atas — aturan position:absolute tidak terpakai.',
        );
    }
}

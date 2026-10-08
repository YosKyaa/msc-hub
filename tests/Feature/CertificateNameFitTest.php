<?php

namespace Tests\Feature;

use App\Models\Certificate;
use App\Models\CertificateEvent;
use App\Models\CertificateTemplate;
use App\Services\CertificateRenderService;
use App\Support\CertificateElement;
use App\Support\CertificateTextMeasurer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Nama panjang di kotak satu baris.
 *
 * Template dirancang dengan nama contoh yang pendek. Nama sungguhan dari
 * daftar PKKMB, seperti "Muhammad Rajendra Belva Putra Indrayana", dulu turun
 * ke baris kedua, keluar dari kotaknya, dan menimpa elemen di bawahnya.
 * Hurufnya kini dikecilkan seperlunya sampai muat satu baris — dihitung sekali
 * dan dipakai PDF maupun halaman verifikasi.
 */
class CertificateNameFitTest extends TestCase
{
    use RefreshDatabase;

    private const NAMA_PANJANG = 'Muhammad Rajendra Belva Putra Indrayana';

    private const CANVAS_WIDTH = 1123;

    private const CANVAS_HEIGHT = 794;

    /**
     * @param  array<string, mixed>  $kotak
     */
    private function certificate(string $nama, array $kotak = []): Certificate
    {
        Storage::fake('public');

        $template = CertificateTemplate::factory()->create([
            'background_path' => 'certificates/templates/tidak-ada.png',
            'canvas_width' => self::CANVAS_WIDTH,
            'canvas_height' => self::CANVAS_HEIGHT,
            'elements' => [$this->nameBox($kotak)],
        ]);

        return Certificate::factory()->create([
            'certificate_event_id' => CertificateEvent::factory()->published()->create(['certificate_template_id' => $template->id])->id,
            'recipient_name' => $nama,
        ]);
    }

    /**
     * Kotak nama sebagaimana lazim dirancang: setinggi satu baris.
     *
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    private function nameBox(array $extra = []): array
    {
        return [
            'variable' => 'recipient_name',
            'x' => 300, 'y' => 300, 'width' => 520, 'height' => 60,
            'font_family' => 'DejaVu Sans', 'font_size' => 28, 'font_weight' => 700,
            ...$extra,
        ];
    }

    /**
     * Banyaknya baris teks yang sungguh tergambar di PDF: satu baris, satu
     * garis dasar.
     */
    private function linesInPdf(Certificate $certificate): int
    {
        $pdf = app(CertificateRenderService::class)->pdf($certificate)->output();

        preg_match_all('/stream\r?\n(.*?)\r?\nendstream/s', $pdf, $streams);

        foreach ($streams[1] as $stream) {
            $plain = @gzuncompress($stream) ?: $stream;

            if (preg_match_all('/BT [\d.]+ ([\d.]+) Td/', $plain, $cocok) > 0) {
                return count(array_unique($cocok[1]));
            }
        }

        $this->fail('Tidak ada teks yang tergambar di PDF.');
    }

    // ------------------------------------------------------------- PDF

    /**
     * Inti perbaikannya, diperiksa pada PDF yang sungguh dihasilkan.
     */
    public function test_a_long_name_stays_on_one_line_in_the_pdf(): void
    {
        $this->assertSame(1, $this->linesInPdf($this->certificate(self::NAMA_PANJANG)));
    }

    /**
     * Pembanding: kotak yang dirancang tinggi memang dibiarkan membungkus
     * teks. Tanpa pembanding ini, test di atas bisa lulus hanya karena cara
     * menghitung barisnya keliru.
     */
    public function test_a_tall_box_is_left_to_wrap(): void
    {
        $this->assertSame(2, $this->linesInPdf($this->certificate(self::NAMA_PANJANG, ['height' => 200])));
    }

    // ---------------------------------------------------------- ukurannya

    public function test_the_fitted_name_fits_its_box(): void
    {
        $kotak = CertificateElement::from($this->nameBox());
        $ukuran = $kotak->fittedFontSize(['recipient_name' => self::NAMA_PANJANG]);

        $this->assertLessThan(28, $ukuran);

        $lebar = app(CertificateTextMeasurer::class)->width(self::NAMA_PANJANG, 'DejaVu Sans', 700, $ukuran);
        $this->assertLessThanOrEqual(520 * CertificateElement::FIT_ROOM, $lebar);
    }

    public function test_a_short_name_keeps_its_designed_size(): void
    {
        $this->assertSame(28.0, CertificateElement::from($this->nameBox())->fittedFontSize(['recipient_name' => 'Budi Santoso']));
    }

    /**
     * Lebih baik dua baris yang terbaca daripada satu baris yang terlalu kecil.
     */
    public function test_the_size_never_drops_below_half(): void
    {
        $ukuran = CertificateElement::from($this->nameBox())
            ->fittedFontSize(['recipient_name' => str_repeat(self::NAMA_PANJANG.' ', 4)]);

        $this->assertSame(14.0, $ukuran);
    }

    public function test_qr_and_empty_elements_are_left_alone(): void
    {
        $this->assertSame(28.0, CertificateElement::from(['variable' => 'qr_code', 'width' => 50, 'height' => 50, 'font_size' => 28])
            ->fittedFontSize([]));

        $this->assertSame(28.0, CertificateElement::from($this->nameBox())->fittedFontSize(['recipient_name' => '']));
    }

    // ------------------------------------------------------------ paritas

    /**
     * PDF dan pratinjau di halaman verifikasi memakai ukuran yang sama, kalau
     * tidak penerima melihat dua sertifikat yang berbeda.
     */
    public function test_the_pdf_and_the_verification_page_use_the_same_size(): void
    {
        $sertifikat = $this->certificate(self::NAMA_PANJANG);
        $ukuran = CertificateElement::from($this->nameBox())->fittedFontSize(['recipient_name' => self::NAMA_PANJANG]);

        $render = app(CertificateRenderService::class);
        $pdfHtml = view('certificates.pdf', [
            'certificate' => $sertifikat,
            'template' => $sertifikat->event->template,
            'values' => $render->variables($sertifikat),
            'qrDataUri' => '',
            'backgroundDataUri' => null,
        ])->render();

        $this->assertStringContainsString("font-size:{$ukuran}px;", $pdfHtml);

        $cqw = round(($ukuran / self::CANVAS_WIDTH) * 100, 4).'cqw';
        $this->get($sertifikat->verificationUrl())
            ->assertOk()
            ->assertSee("font-size:{$cqw};", false);
    }
}

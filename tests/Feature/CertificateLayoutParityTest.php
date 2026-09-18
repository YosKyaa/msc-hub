<?php

namespace Tests\Feature;

use App\Filament\Resources\CertificateTemplateResource\Pages\EditCertificateLayout;
use App\Models\Certificate;
use App\Models\CertificateEvent;
use App\Models\CertificateTemplate;
use App\Models\User;
use App\Services\CertificateRenderService;
use App\Support\CertificateElement;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Sertifikat yang sama digambar tiga kali: di editor, di halaman verifikasi,
 * dan di dalam PDF.
 *
 * Ketiganya dulu menyusun CSS-nya sendiri-sendiri, jadi tidak pernah
 * benar-benar sama. Editor meratakan teks ke tengah secara tegak lalu
 * menyembunyikan yang meluber; dua lainnya menempelkan teks ke atas dan
 * membiarkannya meluber. Admin melihat satu baris rapi saat merancang, dan
 * penerima menerima dua baris bertumpuk.
 *
 * Test ini menjaga ketiganya tetap sepakat.
 */
class CertificateLayoutParityTest extends TestCase
{
    use RefreshDatabase;

    private const CANVAS_WIDTH = 1123;

    private const CANVAS_HEIGHT = 794;

    /**
     * @param  list<array<string, mixed>>  $elements
     */
    private function certificate(array $elements, string $name = 'Budi Santoso'): Certificate
    {
        Storage::fake('public');
        Storage::disk('public')->put('certificates/templates/uji.png', $this->pngBytes());

        $template = CertificateTemplate::factory()->create([
            'background_path' => 'certificates/templates/uji.png',
            'canvas_width' => self::CANVAS_WIDTH,
            'canvas_height' => self::CANVAS_HEIGHT,
            'elements' => $elements,
        ]);

        $event = CertificateEvent::factory()->published()->create([
            'certificate_template_id' => $template->id,
        ]);

        return Certificate::factory()->create([
            'certificate_event_id' => $event->id,
            'recipient_name' => $name,
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

    private function pageContentStream(string $pdf): string
    {
        preg_match_all('/stream\r?\n(.*?)\r?\nendstream/s', $pdf, $streams);

        foreach ($streams[1] as $stream) {
            $plain = @gzuncompress($stream) ?: $stream;

            if (str_contains($plain, 'TJ') || str_contains($plain, 'Tj')) {
                return $plain;
            }
        }

        return '';
    }

    /**
     * Tinggi baris teks pertama dalam titik, diukur dari dasar halaman —
     * makin besar angkanya, makin ke atas letaknya.
     */
    private function firstTextBaseline(Certificate $certificate): float
    {
        $content = $this->pageContentStream(
            app(CertificateRenderService::class)->pdf($certificate)->output()
        );

        $this->assertMatchesRegularExpression('/BT [\d.]+ [\d.]+ Td/', $content,
            'Tidak ada teks yang tergambar di PDF.');

        preg_match('/BT [\d.]+ ([\d.]+) Td/', $content, $cocok);

        return (float) $cocok[1];
    }

    private function nameElement(array $extra = []): array
    {
        return [[
            'variable' => 'recipient_name',
            'x' => 300, 'y' => 200, 'width' => 500, 'height' => 300,
            'font_size' => 28,
            ...$extra,
        ]];
    }

    // ---------------------------------------------------- perataan tegak

    /**
     * Inti perbaikannya: perataan tegak harus benar-benar menggeser teks di
     * PDF. Dompdf menerima banyak aturan gaya lalu diam-diam mengabaikannya,
     * jadi yang diperiksa adalah koordinat yang sungguh dihasilkan.
     */
    public function test_vertical_alignment_actually_moves_the_text_in_the_pdf(): void
    {
        $atas = $this->firstTextBaseline($this->certificate($this->nameElement(['valign' => 'top'])));
        $tengah = $this->firstTextBaseline($this->certificate($this->nameElement(['valign' => 'middle'])));
        $bawah = $this->firstTextBaseline($this->certificate($this->nameElement(['valign' => 'bottom'])));

        // Koordinat PDF diukur dari dasar halaman: rata atas berarti angka
        // terbesar.
        $this->assertGreaterThan($tengah, $atas, 'Rata atas tidak lebih tinggi daripada rata tengah.');
        $this->assertGreaterThan($bawah, $tengah, 'Rata tengah tidak lebih tinggi daripada rata bawah.');

        // Kotaknya setinggi 300 px = 225 pt; pergeserannya harus sepadan,
        // bukan sekadar beda beberapa titik.
        $this->assertGreaterThan(50, $atas - $bawah,
            'Perataan tegak hanya menggeser teks sedikit — kemungkinan diabaikan dompdf.');
    }

    public function test_horizontal_alignment_actually_moves_the_text_in_the_pdf(): void
    {
        $posisi = [];

        foreach (['left', 'center', 'right'] as $align) {
            $content = $this->pageContentStream(
                app(CertificateRenderService::class)
                    ->pdf($this->certificate($this->nameElement(['align' => $align])))
                    ->output()
            );

            preg_match('/BT ([\d.]+) [\d.]+ Td/', $content, $cocok);
            $posisi[$align] = (float) $cocok[1];
        }

        $this->assertLessThan($posisi['center'], $posisi['left']);
        $this->assertLessThan($posisi['right'], $posisi['center']);

        // Rata kiri tetap menempel tepi kotaknya: 300 px = 225 pt.
        $this->assertEqualsWithDelta(225.0, $posisi['left'], 0.5);
    }

    /**
     * Template lama tidak punya kunci `valign` sama sekali. Bawaannya harus
     * mengikuti apa yang selama ini ditampilkan editor, yaitu rata tengah —
     * kalau tidak, setiap sertifikat yang sudah terlanjur dirancang bergeser.
     */
    public function test_a_template_without_a_vertical_alignment_follows_the_editor(): void
    {
        $tanpaKunci = $this->firstTextBaseline($this->certificate($this->nameElement()));
        $tengah = $this->firstTextBaseline($this->certificate($this->nameElement(['valign' => 'middle'])));

        $this->assertEqualsWithDelta($tengah, $tanpaKunci, 0.5);
    }

    // ------------------------------------------------ kesepakatan bertiga

    /**
     * Halaman verifikasi dan PDF harus menyebut perataan yang sama untuk
     * elemen yang sama.
     */
    public function test_the_verification_page_states_the_same_alignment_as_the_pdf(): void
    {
        $certificate = $this->certificate($this->nameElement([
            'align' => 'right',
            'valign' => 'bottom',
        ]));

        $this->get(route('certificates.verify', $certificate->verification_token))
            ->assertOk()
            ->assertSee('vertical-align:bottom', false)
            ->assertSee('text-align:right', false);
    }

    /**
     * Editor menyimpan perataan tegak, bukan membuangnya saat disimpan.
     */
    public function test_the_editor_keeps_the_vertical_alignment_it_was_given(): void
    {
        $certificate = $this->certificate($this->nameElement(['valign' => 'bottom']));
        $template = $certificate->event->template;

        $this->assertSame('bottom', CertificateElement::collect($template->elements)[0]->verticalAlign());
    }

    /**
     * Blade editornya cukup padat, jadi satu tanda kutip yang salah membuat
     * seluruh halaman tumbang — dan itu baru ketahuan saat admin membukanya.
     */
    public function test_the_editor_page_still_renders(): void
    {
        $certificate = $this->certificate($this->nameElement());

        Livewire::actingAs($this->admin())
            ->test(EditCertificateLayout::class, ['record' => $certificate->event->template->id])
            ->assertSuccessful()
            ->assertSee('Perataan tegak')
            ->assertSee('Perataan mendatar');
    }

    private function admin(): User
    {
        $this->seed(RoleSeeder::class);

        $admin = User::factory()->create();
        $admin->assignRole('admin');

        return $admin;
    }

    /**
     * Validasi di sisi peladen mudah tertinggal saat isian baru ditambahkan:
     * yang tidak disebut akan dibuang diam-diam, dan admin mengira perataannya
     * tersimpan padahal tidak.
     */
    public function test_saving_from_the_editor_persists_the_vertical_alignment(): void
    {
        $certificate = $this->certificate($this->nameElement());
        $template = $certificate->event->template;

        Livewire::actingAs($this->admin())
            ->test(EditCertificateLayout::class, ['record' => $template->id])
            ->call('save', [[
                'label' => 'Nama penerima',
                'variable' => 'recipient_name',
                'text' => '',
                'x' => 300, 'y' => 200, 'width' => 500, 'height' => 300,
                'font_family' => 'DejaVu Sans',
                'font_size' => 28,
                'font_weight' => 700,
                'align' => 'right',
                'valign' => 'bottom',
                'color' => '#111827',
            ]])
            ->assertHasNoErrors();

        $tersimpan = CertificateElement::collect($template->fresh()->elements)[0];

        $this->assertSame('bottom', $tersimpan->verticalAlign());
        $this->assertSame('right', $tersimpan->align());
    }

    /**
     * Nilai yang tidak dikenal — misalnya sisa data lama — tidak boleh
     * menghasilkan CSS ngawur yang membuat elemennya hilang.
     */
    public function test_an_unknown_alignment_falls_back_instead_of_breaking_the_layout(): void
    {
        $element = CertificateElement::from([
            'variable' => 'recipient_name',
            'align' => 'justify-all',
            'valign' => 'super',
        ]);

        $this->assertSame(CertificateElement::DEFAULT_ALIGN, $element->align());
        $this->assertSame(CertificateElement::DEFAULT_VERTICAL_ALIGN, $element->verticalAlign());
    }

    /**
     * Editor menyusun gayanya di JavaScript, jadi ia gampang menyimpang dari
     * PHP tanpa ada yang menyadarinya. Yang diperiksa: daftar pilihan dan
     * tinggi barisnya masih sama persis.
     */
    public function test_the_editor_script_shares_the_same_layout_vocabulary(): void
    {
        $editor = file_get_contents(resource_path(
            'views/filament/resources/certificate-template-resource/pages/edit-certificate-layout.blade.php'
        ));

        foreach (CertificateElement::VERTICAL_ALIGNMENTS as $align) {
            $this->assertStringContainsString("'{$align}'", $editor,
                "Editor tidak menawarkan perataan tegak '{$align}'.");
        }

        foreach (CertificateElement::ALIGNMENTS as $align) {
            $this->assertStringContainsString("'{$align}'", $editor,
                "Editor tidak menawarkan perataan '{$align}'.");
        }

        $this->assertStringContainsString('line-height:'.CertificateElement::LINE_HEIGHT, $editor,
            'Tinggi baris di editor berbeda dengan yang dipakai PDF.');
    }

    /**
     * Sebab paling sering pratinjau dan hasil akhirnya berbeda: editor
     * memotong teks yang meluber sehingga admin tidak pernah melihat
     * masalahnya.
     */
    public function test_the_editor_no_longer_hides_text_that_overflows_its_box(): void
    {
        $editor = file_get_contents(resource_path(
            'views/filament/resources/certificate-template-resource/pages/edit-certificate-layout.blade.php'
        ));

        $this->assertStringNotContainsString('.cert-element-content{width:100%;overflow:hidden}', $editor,
            'Editor masih memotong teks yang meluber, sehingga admin tidak melihat apa yang diterima penerima.');
    }
}

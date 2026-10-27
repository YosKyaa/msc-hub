<?php

namespace Tests\Feature;

use App\Filament\Resources\CertificateTemplateResource;
use App\Filament\Resources\CertificateTemplateResource\Pages\CreateCertificateTemplate;
use App\Models\Certificate;
use App\Models\CertificateEvent;
use App\Models\CertificateTemplate;
use App\Models\User;
use App\Services\CertificateRenderService;
use App\Support\CertificateFont;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Livewire\Livewire;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Font kustom sertifikat.
 *
 * Unggahan font dulu selalu gagal dengan "validation.mimetypes": PHP
 * mengenali TTF sebagai font/sfnt, jenis yang tidak ada di daftar. Seandainya
 * lolos pun, font itu tidak pernah sampai ke PDF — dompdf tidak menemukan
 * alamat file:///E:/... di Windows dan tidak punya folder cache font — dan di
 * editor ia tampil dengan nama acak 40 huruf.
 */
class CertificateFontTest extends TestCase
{
    use RefreshDatabase;

    /** Font TrueType sungguhan yang pasti tersedia: bawaan dompdf. */
    private const TTF = 'vendor/dompdf/dompdf/lib/fonts/DejaVuSansMono.ttf';

    private string $cache;

    /** @var list<string> */
    private array $berkas = [];

    protected function setUp(): void
    {
        parent::setUp();

        // Cache font dompdf dipisah per tes dan sengaja belum ada: folder
        // yang hilang adalah salah satu sebab font tidak pernah sampai ke PDF.
        $this->cache = storage_path('framework/testing/dompdf-fonts-'.uniqid());
        config(['dompdf.options.font_dir' => $this->cache, 'dompdf.options.font_cache' => $this->cache]);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->cache);

        foreach ($this->berkas as $path) {
            @unlink($path);
        }

        parent::tearDown();
    }

    private function ttf(): string
    {
        return (string) file_get_contents(base_path(self::TTF));
    }

    /**
     * Isi font TrueType dengan empat bita pertamanya diganti: cara termudah
     * membuat font CFF, kumpulan font, atau WOFF tiruan.
     */
    private function fontWithHeader(string $kepala): string
    {
        return $kepala.substr($this->ttf(), 4);
    }

    private function temp(string $isi): string
    {
        $path = (string) tempnam(sys_get_temp_dir(), 'font');
        file_put_contents($path, $isi);

        return $this->berkas[] = $path;
    }

    private function png(): string
    {
        $gambar = imagecreatetruecolor(240, 170);
        imagefill($gambar, 0, 0, imagecolorallocate($gambar, 12, 74, 160));

        ob_start();
        imagepng($gambar);
        imagedestroy($gambar);

        return (string) ob_get_clean();
    }

    private function admin(): User
    {
        $this->seed(RoleSeeder::class);
        $user = User::factory()->create();
        $user->assignRole('admin');
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return $user->fresh();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function createTemplate(array $data)
    {
        return Livewire::test(CreateCertificateTemplate::class)
            ->fillForm([
                'name' => 'Sertifikat PKKMB 2026',
                'background_path' => UploadedFile::fake()->image('latar.png', 1123, 794),
                'canvas_width' => 1123,
                'canvas_height' => 794,
                ...$data,
            ])
            ->call('create');
    }

    /**
     * Font unggahan di disk publik tiruan, dengan nama keluarga baru di setiap
     * tes: dompdf mengingat letak setiap font di variabel statis sepanjang
     * proses, sedangkan folder cache tes dihapus setiap kali selesai.
     */
    private function uploadedFont(): string
    {
        $path = CertificateFont::DIRECTORY.'/'.CertificateFont::storedName('Huruf Uji.ttf');
        Storage::disk('public')->put($path, $this->ttf());

        return $path;
    }

    /**
     * Awalan berkas cache dompdf untuk sebuah font dan ketebalannya.
     */
    private function cachedCopies(string $fontPath, string $tebal): array
    {
        $awalan = preg_replace('/\W/', '_', strtolower(pathinfo($fontPath, PATHINFO_FILENAME)));

        return glob($this->cache.'/'.$awalan.'_'.$tebal.'_*.ufm') ?: [];
    }

    /**
     * Sertifikat yang namanya ditulis dengan font unggahan.
     */
    private function certificateUsing(string $fontPath): Certificate
    {
        Storage::disk('public')->put('certificates/templates/latar.png', $this->png());

        $template = CertificateTemplate::factory()->create([
            'background_path' => 'certificates/templates/latar.png',
            'fonts' => [$fontPath],
            'elements' => [[
                'variable' => 'recipient_name',
                'x' => 100, 'y' => 300, 'width' => 900, 'height' => 80,
                'font_family' => pathinfo($fontPath, PATHINFO_FILENAME),
                'font_size' => 40,
                'font_weight' => 700,
            ]],
        ]);

        $event = CertificateEvent::factory()->published()->create(['certificate_template_id' => $template->id]);

        return Certificate::factory()->create([
            'certificate_event_id' => $event->id,
            'recipient_name' => 'Budi Hartono',
        ]);
    }

    // ------------------------------------------------------------ berkas

    /**
     * Penyebab utamanya: jenis yang dilaporkan PHP untuk TTF sungguhan harus
     * ada di daftar yang diterima.
     */
    public function test_the_type_php_reports_for_a_real_ttf_is_accepted(): void
    {
        $font = new UploadedFile($this->temp($this->ttf()), 'aAccountantSignature.ttf', null, null, true);

        $this->assertContains($font->getMimeType(), CertificateFont::MIME_TYPES);
        $this->assertFalse(
            Validator::make(['font' => $font], ['font' => 'mimetypes:'.implode(',', CertificateFont::MIME_TYPES)])->fails()
        );
    }

    public function test_a_truetype_font_passes_the_content_check(): void
    {
        $this->assertNull(CertificateFont::problem(base_path(self::TTF), 'aAccountantSignature.ttf'));
        $this->assertNull(CertificateFont::problem(base_path(self::TTF), 'Huruf.OTF'));
    }

    /**
     * Font yang tidak bisa ditanam ke PDF ditolak sejak diunggah, dengan
     * alasan yang bisa ditindaklanjuti admin.
     */
    public function test_fonts_that_cannot_reach_the_pdf_are_refused_with_a_reason(): void
    {
        $kasus = [
            'OpenType CFF' => ['BebasNeue.otf', $this->fontWithHeader('OTTO')],
            'kumpulan beberapa font' => ['Koleksi.ttf', $this->fontWithHeader('ttcf')],
            'Font web (WOFF)' => ['Web.ttf', $this->fontWithHeader('wOFF')],
            'bukan font TrueType' => ['Gambar.ttf', $this->png()],
            'rusak' => ['Rusak.ttf', "\x00\x01\x00\x00".str_repeat("\x00", 64)],
            '.ttf atau .otf' => ['Font.woff2', $this->ttf()],
        ];

        foreach ($kasus as $alasan => [$nama, $isi]) {
            $this->assertStringContainsString($alasan, (string) CertificateFont::problem($this->temp($isi), $nama), $nama);
        }
    }

    /**
     * Nama berkas menjadi nama font di editor.
     */
    public function test_stored_names_stay_readable_and_never_collide(): void
    {
        Storage::fake('public');

        $nama = CertificateFont::storedName('aAccountantSignature.ttf');

        $this->assertMatchesRegularExpression('/^aAccountantSignature--[a-z0-9]{6}\.ttf$/', $nama);
        $this->assertNotSame($nama, CertificateFont::storedName('aAccountantSignature.ttf'));
        $this->assertSame('aAccountantSignature', (new CertificateFont('certificates/fonts/'.$nama))->label());

        $this->assertMatchesRegularExpression('/^Great-Vibes-Regular--[a-z0-9]{6}\.ttf$/', CertificateFont::storedName('Great Vibes (Regular).TTF'));
        $this->assertMatchesRegularExpression('/^font-1942-Report--[a-z0-9]{6}\.otf$/', CertificateFont::storedName('1942 Report.otf'));
        $this->assertMatchesRegularExpression('/^font--[a-z0-9]{6}\.ttf$/', CertificateFont::storedName('!!!.ttf'));
    }

    // -------------------------------------------------------------- panel

    public function test_an_uploaded_font_is_saved_under_its_own_name(): void
    {
        Storage::fake('public');
        $this->actingAs($this->admin());

        $this->createTemplate([
            'fonts' => [UploadedFile::fake()->createWithContent('aAccountantSignature.ttf', $this->ttf())],
        ])->assertHasNoFormErrors();

        $font = CertificateFont::forTemplate(CertificateTemplate::sole())[0];

        $this->assertMatchesRegularExpression('#^certificates/fonts/aAccountantSignature--[a-z0-9]{6}\.ttf$#', $font->path);
        $this->assertSame('aAccountantSignature', $font->label());
        Storage::disk('public')->assertExists($font->path);
    }

    public function test_the_form_refuses_a_font_that_cannot_reach_the_pdf(): void
    {
        Storage::fake('public');
        $this->actingAs($this->admin());

        // Jenisnya disebut sendiri, sebagaimana finfo membaca font CFF
        // sungguhan. Tebakan dari ekstensi .otf yang dipakai berkas tiruan
        // justru "OpenDocument formula template".
        $font = UploadedFile::fake()
            ->createWithContent('BebasNeue.otf', $this->fontWithHeader('OTTO'))
            ->mimeType('application/vnd.ms-opentype');

        $this->createTemplate(['fonts' => [$font]])->assertHasFormErrors([
            'fonts' => fn (array $aturan, array $pesan): bool => str_contains(implode(' ', $pesan), 'OpenType CFF'),
        ]);

        $this->assertSame(0, CertificateTemplate::count());
    }

    public function test_the_editor_lists_the_font_by_its_readable_name(): void
    {
        Storage::fake('public');
        $template = CertificateTemplate::factory()->create(['fonts' => ['certificates/fonts/aAccountantSignature--k3f9q2.ttf']]);
        $this->actingAs($this->admin());

        $this->get(CertificateTemplateResource::getUrl('editor', ['record' => $template]))
            ->assertOk()
            ->assertSee('<option value="aAccountantSignature--k3f9q2">aAccountantSignature</option>', false)
            ->assertSee("font-family:'aAccountantSignature--k3f9q2'", false)
            // Satu berkas untuk semua ketebalan, seperti di PDF; tanpa ini
            // peramban menebalkannya sendiri.
            ->assertSee('font-weight:100 900', false);
    }

    public function test_the_form_warns_when_the_server_cannot_store_fonts_for_the_pdf(): void
    {
        $this->actingAs($this->admin());

        $this->get(CertificateTemplateResource::getUrl('create'))
            ->assertOk()
            ->assertSee('.otf berbasis TrueType', false)
            ->assertDontSee('tidak dapat ditulisi', false);

        // Folder yang mustahil dibuat: induknya berupa berkas.
        $bukanFolder = $this->temp('');
        config(['dompdf.options.font_dir' => $bukanFolder.'/fonts', 'dompdf.options.font_cache' => $bukanFolder.'/fonts']);

        $this->get(CertificateTemplateResource::getUrl('create'))
            ->assertOk()
            ->assertSee('storage/fonts di server tidak dapat ditulisi', false);
    }

    // ---------------------------------------------------------------- PDF

    /**
     * Inti perbaikannya: font unggahan benar-benar tertanam di PDF, bukan
     * diam-diam diganti font cadangan. Namanya bergaya Bold, bawaan editor
     * untuk nama penerima, yang dulu tercetak dengan Times-Bold.
     */
    public function test_the_certificate_pdf_embeds_the_uploaded_font(): void
    {
        Storage::fake('public');

        $pdf = app(CertificateRenderService::class)
            ->pdf($this->certificateUsing($this->uploadedFont()))
            ->output();

        $this->assertMatchesRegularExpression('#/BaseFont\s*/(?:[A-Z]{6}\+)?DejaVuSansMono\b#', $pdf, 'PDF memakai font cadangan, bukan font unggahan.');
        $this->assertStringContainsString('/FontFile2', $pdf);
        $this->assertDirectoryExists($this->cache);
    }

    /**
     * Pengukur teks dan PDF mendaftarkan font dengan alamat yang sama. Alamat
     * yang berbeda membuat dompdf membaca ulang fontnya di setiap sertifikat.
     */
    public function test_measuring_and_printing_share_one_cached_copy_of_the_font(): void
    {
        Storage::fake('public');
        $font = $this->uploadedFont();
        $sertifikat = $this->certificateUsing($font);

        app(CertificateRenderService::class)->pdf($sertifikat)->output();
        app(CertificateRenderService::class)->pdf($sertifikat->fresh())->output();

        $this->assertCount(1, $this->cachedCopies($font, 'normal'));
        $this->assertCount(1, $this->cachedCopies($font, 'bold'));
    }

    public function test_a_missing_font_file_falls_back_instead_of_breaking_the_pdf(): void
    {
        Storage::fake('public');

        $pdf = app(CertificateRenderService::class)
            ->pdf($this->certificateUsing('certificates/fonts/Hilang--abc123.ttf'))
            ->output();

        $this->assertStringStartsWith('%PDF', $pdf);
    }
}

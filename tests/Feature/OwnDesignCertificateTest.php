<?php

namespace Tests\Feature;

use App\Enums\CertificateDesign;
use App\Enums\ParticipantRole;
use App\Filament\Resources\CertificateEventResource\Pages\EditCertificateEvent;
use App\Filament\Resources\CertificateEventResource\RelationManagers\ParticipationsRelationManager;
use App\Models\Certificate;
use App\Models\CertificateEvent;
use App\Models\CertificateEventParticipant;
use App\Models\Participant;
use App\Models\User;
use App\Services\Certificates\CertificateBatchException;
use App\Services\Certificates\CertificateBatchIssuer;
use App\Services\Certificates\CertificateBatchMailer;
use App\Services\Certificates\CertificateDesignFile;
use App\Services\Certificates\CertificateIssuer;
use App\Services\Certificates\CertificateQrPack;
use App\Support\CertificateStage;
use chillerlan\QRCode\QRCode;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;
use ZipArchive;

/**
 * Sertifikat desain sendiri: nomor dan QR lebih dulu.
 *
 * Untuk pemenang dan pembicara, sertifikatnya didesain di luar sistem dengan
 * kualitas penuh. Sistem menerbitkan nomor dan QR verifikasinya lebih dulu,
 * memberikannya dalam satu paket untuk desainer, lalu menerima berkas jadinya
 * kembali supaya bisa diverifikasi, diunduh, dan dikirim.
 */
class OwnDesignCertificateTest extends TestCase
{
    use RefreshDatabase;

    /** @var list<string> */
    private array $berkas = [];

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
    }

    protected function tearDown(): void
    {
        foreach ($this->berkas as $path) {
            @unlink($path);
        }

        parent::tearDown();
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
     * Kegiatan tanpa template: seluruh sertifikatnya didesain sendiri.
     */
    private function event(bool $published = true): CertificateEvent
    {
        $factory = CertificateEvent::factory();

        return ($published ? $factory->published() : $factory)->create(['certificate_template_id' => null]);
    }

    private function speaker(CertificateEvent $event, string $nama, ?string $email = null): CertificateEventParticipant
    {
        return CertificateEventParticipant::factory()->create([
            'certificate_event_id' => $event->id,
            'participant_id' => Participant::factory()->create(['name' => $nama, 'email' => $email]),
            'role' => ParticipantRole::SPEAKER->value,
        ]);
    }

    private function issued(CertificateEventParticipant $participation): Certificate
    {
        return app(CertificateIssuer::class)->issue($participation, CertificateDesign::EXTERNAL);
    }

    private function table(CertificateEvent $event): Testable
    {
        return Livewire::test(ParticipationsRelationManager::class, [
            'ownerRecord' => $event,
            'pageClass' => EditCertificateEvent::class,
        ]);
    }

    private function png(int $lebar = 2000, int $tinggi = 1414): string
    {
        $gambar = imagecreatetruecolor($lebar, $tinggi);
        imagefill($gambar, 0, 0, imagecolorallocate($gambar, 250, 240, 200));

        ob_start();
        imagepng($gambar);
        imagedestroy($gambar);

        return (string) ob_get_clean();
    }

    private function pdf(): string
    {
        return "%PDF-1.4\n1 0 obj << /Type /Catalog >> endobj\ntrailer << /Root 1 0 R >>\n%%EOF\n";
    }

    private function temp(string $isi): string
    {
        $path = (string) tempnam(sys_get_temp_dir(), 'desain');
        file_put_contents($path, $isi);

        return $this->berkas[] = $path;
    }

    // ---------------------------------------------------------- penerbitan

    public function test_numbers_and_qr_are_issued_without_a_template(): void
    {
        $event = $this->event();
        $budi = $this->speaker($event, 'Budi Hartono');

        app(CertificateBatchIssuer::class)->issueOwnDesignFor($event, collect([$budi]));

        $sertifikat = $budi->fresh('certificate')->certificate;

        $this->assertNotNull($sertifikat);
        $this->assertTrue($sertifikat->isOwnDesign());
        $this->assertNotEmpty($sertifikat->certificate_number);

        // Memilihnya adalah keputusan admin bahwa ia berhak: QR-nya perlu ada
        // jauh sebelum kehadiran mana pun tercatat.
        $this->assertTrue($budi->fresh()->isEligible());

        // Sah, tetapi belum ada dokumen yang bisa diunduh atau dikirim.
        $this->assertTrue($sertifikat->isValid());
        $this->assertFalse($sertifikat->isDeliverable());
        $this->assertSame(CertificateStage::AWAITING_DESIGN, CertificateStage::for($budi->fresh('certificate')));
    }

    public function test_template_issuing_still_requires_a_template(): void
    {
        $event = $this->event();
        $budi = $this->speaker($event, 'Budi Hartono');
        $budi->update(['eligible_at' => now()]);

        $this->expectException(CertificateBatchException::class);
        $this->expectExceptionMessage('Desain Sendiri');

        app(CertificateBatchIssuer::class)->issueFor($event, collect([$budi->fresh()]));
    }

    /**
     * QR yang sudah tercetak di desain tidak boleh berubah.
     */
    public function test_an_issued_number_and_qr_never_change(): void
    {
        $event = $this->event();
        $budi = $this->speaker($event, 'Budi Hartono');
        $sertifikat = $this->issued($budi);

        try {
            app(CertificateBatchIssuer::class)->issueOwnDesignFor($event, collect([$budi->fresh('certificate')]));
            $this->fail('Penerbitan ulang seharusnya ditolak.');
        } catch (CertificateBatchException $e) {
            $this->assertStringContainsString('sudah punya sertifikat', $e->getMessage());
        }

        $sekarang = $sertifikat->fresh();
        $this->assertSame($sertifikat->verification_token, $sekarang->verification_token);
        $this->assertSame($sertifikat->certificate_number, $sekarang->certificate_number);
    }

    public function test_the_panel_issues_only_the_selected_recipients(): void
    {
        $this->actingAs($this->admin());
        $event = $this->event();
        $pemenang = $this->speaker($event, 'Siti Rahma');
        $peserta = $this->speaker($event, 'Andi Pratama');

        $this->table($event)
            ->callTableBulkAction('issueOwnDesign', [$pemenang])
            ->assertNotified('Sertifikat berhasil diterbitkan');

        $this->assertTrue($pemenang->fresh('certificate')->certificate->isOwnDesign());
        $this->assertNull($peserta->fresh('certificate')->certificate);
    }

    /**
     * Penerbitannya lewat aksi massal, jadi menu Desain Sendiri yang
     * menunjukkan jalannya. Paket dan unggahan baru muncul setelah ada
     * sertifikat desain sendiri.
     */
    public function test_the_menu_explains_the_flow_before_anything_is_issued(): void
    {
        $this->actingAs($this->admin());
        $event = $this->event();

        $this->table($event)
            ->assertTableActionHidden('downloadQrPack')
            ->assertTableActionHidden('uploadDesigns')
            ->mountTableAction('ownDesignGuide')
            ->assertSee('Terbitkan Nomor &amp; QR (Desain Sendiri)', false);

        $this->issued($this->speaker($event, 'Budi Hartono'));

        $this->table($event)
            ->assertTableActionVisible('downloadQrPack')
            ->assertTableActionVisible('uploadDesigns');
    }

    // ------------------------------------------------------------ paket QR

    public function test_the_qr_pack_holds_a_scannable_qr_and_a_list_per_recipient(): void
    {
        $event = $this->event();
        $budi = $this->issued($this->speaker($event, 'Budi Hartono', 'budi@jgu.ac.id'));
        $budi->forceFill(['certificate_number' => '0012/CERT/MSC-JGU/IX/2026'])->save();
        $siti = $this->issued($this->speaker($event, 'Siti Rahma'));

        $zip = new ZipArchive;
        $paket = $this->berkas[] = app(CertificateQrPack::class)->build($event, CertificateQrPack::certificatesFor($event));
        $this->assertTrue($zip->open($paket));

        $nama = '0012-CERT-MSC-JGU-IX-2026 - Budi Hartono';
        $this->assertSame($nama, CertificateQrPack::fileStem($budi->fresh()));

        // QR-nya benar-benar terbaca sebagai tautan verifikasi orang itu.
        $png = (string) $zip->getFromName("QR/{$nama}.png");
        $this->assertSame($budi->verificationUrl(), (new QRCode)->readFromBlob($png)->data);
        $this->assertGreaterThanOrEqual(1000, getimagesizefromstring($png)[0]);

        $svg = (string) $zip->getFromName("QR/{$nama}.svg");
        $this->assertStringContainsString('<svg width="30mm" height="30mm"', $svg);

        $this->assertNotFalse($zip->getFromName('QR/'.CertificateQrPack::fileStem($siti).'.png'));
        $this->assertStringContainsString('2,5 x 2,5 cm', (string) $zip->getFromName('PETUNJUK.txt'));

        // Daftarnya menyimpan nomor sebagai teks, apa adanya.
        $daftar = $this->temp((string) $zip->getFromName('Daftar QR.xlsx'));
        $lembar = IOFactory::load($daftar)->getActiveSheet();
        $this->assertSame('0012/CERT/MSC-JGU/IX/2026', $lembar->getCell('B2')->getValue());
        $this->assertSame('s', $lembar->getCell('B2')->getDataType());
        $this->assertSame('Budi Hartono', $lembar->getCell('C2')->getValue());
        $this->assertSame($budi->verificationUrl(), $lembar->getCell('F2')->getValue());

        $zip->close();
    }

    /**
     * Nomor yang hanya berisi angka tetap utuh dengan nol di depannya.
     */
    public function test_a_digits_only_number_keeps_its_leading_zeros(): void
    {
        $event = $this->event();
        $budi = $this->issued($this->speaker($event, 'Budi Hartono'));
        $budi->forceFill(['certificate_number' => '0012'])->save();

        $zip = new ZipArchive;
        $this->assertTrue($zip->open($this->berkas[] = app(CertificateQrPack::class)->build($event, CertificateQrPack::certificatesFor($event))));
        $lembar = IOFactory::load($this->temp((string) $zip->getFromName('Daftar QR.xlsx')))->getActiveSheet();
        $zip->close();

        $this->assertSame('0012', $lembar->getCell('B2')->getValue());
    }

    public function test_the_pack_leaves_out_template_and_revoked_certificates(): void
    {
        $event = $this->event();
        $sendiri = $this->issued($this->speaker($event, 'Budi Hartono'));
        $dicabut = $this->issued($this->speaker($event, 'Siti Rahma'));
        $dicabut->update(['revoked_at' => now()]);
        $template = Certificate::factory()->create(['certificate_event_id' => $event->id]);

        $isi = CertificateQrPack::certificatesFor($event)->pluck('id')->all();

        $this->assertSame([$sendiri->id], $isi);
        $this->assertNotContains($template->id, $isi);
    }

    public function test_the_panel_downloads_the_qr_pack(): void
    {
        $this->actingAs($this->admin());
        $event = $this->event();
        $this->issued($this->speaker($event, 'Budi Hartono'));

        $this->table($event)
            ->callTableAction('downloadQrPack')
            ->assertFileDownloaded(CertificateQrPack::downloadName($event));
    }

    // ------------------------------------------------------ berkas jadinya

    public function test_design_files_are_checked_by_their_content(): void
    {
        $this->assertNull(CertificateDesignFile::problem($this->temp($this->png()), 'Sertifikat.png'));
        $this->assertNull(CertificateDesignFile::problem($this->temp($this->pdf()), 'Sertifikat.pdf'));

        $this->assertStringContainsString('terlalu kecil', (string) CertificateDesignFile::problem($this->temp($this->png(800, 566)), 'Kecil.png'));
        $this->assertStringContainsString('bukan PDF', (string) CertificateDesignFile::problem($this->temp('bukan pdf'), 'Palsu.pdf'));
        $this->assertStringContainsString('bukan gambar', (string) CertificateDesignFile::problem($this->temp('bukan gambar'), 'Palsu.jpg'));
        $this->assertSame(CertificateDesignFile::WRONG_TYPE_MESSAGE, CertificateDesignFile::problem($this->temp($this->pdf()), 'Desain.docx'));
    }

    public function test_files_are_matched_by_number_first_then_by_a_unique_name(): void
    {
        $event = $this->event();
        $satu = $this->issued($this->speaker($event, 'Budi Hartono'));
        $sebelas = $this->issued($this->speaker($event, 'Siti Rahma'));
        $satu->forceFill(['certificate_number' => '1/X/2026'])->save();
        $sebelas->forceFill(['certificate_number' => '11/X/2026'])->save();
        $semua = CertificateQrPack::certificatesFor($event);

        $this->assertSame($sebelas->id, CertificateDesignFile::match('11-X-2026 - Siti Rahma.pdf', $semua)?->id);
        $this->assertSame($satu->id, CertificateDesignFile::match('1-X-2026.png', $semua)?->id);
        $this->assertSame($satu->id, CertificateDesignFile::match('Sertifikat Budi Hartono.pdf', $semua)?->id);
        $this->assertNull(CertificateDesignFile::match('Desain final (3).pdf', $semua));
    }

    public function test_the_panel_attaches_a_batch_of_designs_to_their_recipients(): void
    {
        $this->actingAs($this->admin());
        $event = $this->event();
        $budi = $this->issued($this->speaker($event, 'Budi Hartono'));
        $siti = $this->issued($this->speaker($event, 'Siti Rahma'));

        $this->table($event)
            ->callTableAction('uploadDesigns', data: ['berkas' => [
                UploadedFile::fake()->createWithContent(CertificateQrPack::fileStem($budi).'.pdf', $this->pdf()),
                UploadedFile::fake()->createWithContent('Siti Rahma.png', $this->png()),
                UploadedFile::fake()->createWithContent('Desain final (3).pdf', $this->pdf()),
            ]])
            ->assertHasNoTableActionErrors()
            ->assertNotified('2 berkas desain terpasang');

        foreach ([$budi, $siti] as $sertifikat) {
            $sertifikat = $sertifikat->fresh();
            $this->assertNotNull($sertifikat->design_uploaded_at);
            Storage::disk('local')->assertExists($sertifikat->design_file_path);
            $this->assertTrue($sertifikat->isDeliverable());
        }

        $this->assertSame('png', pathinfo($siti->fresh()->design_file_path, PATHINFO_EXTENSION));
    }

    public function test_one_design_can_be_uploaded_from_its_own_row(): void
    {
        $this->actingAs($this->admin());
        $event = $this->event();
        $budi = $this->speaker($event, 'Budi Hartono');
        $this->issued($budi);

        // Nama berkasnya sengaja tidak memuat nomor maupun nama: unggahan
        // dari baris penerimanya tidak perlu dicocokkan.
        $this->table($event)
            ->callTableAction('uploadDesign', $budi->fresh('certificate'), data: [
                'berkas' => UploadedFile::fake()->createWithContent('final-v3.pdf', $this->pdf()),
            ])
            ->assertHasNoTableActionErrors()
            ->assertNotified('Berkas desain terpasang');

        $this->assertTrue($budi->fresh('certificate')->certificate->isDeliverable());
    }

    public function test_the_panel_refuses_a_design_that_would_print_badly(): void
    {
        $this->actingAs($this->admin());
        $event = $this->event();
        $budi = $this->speaker($event, 'Budi Hartono');
        $this->issued($budi);

        $this->table($event)
            ->callTableAction('uploadDesign', $budi->fresh('certificate'), data: [
                'berkas' => UploadedFile::fake()->createWithContent('Budi.png', $this->png(640, 452)),
            ])
            ->assertHasTableActionErrors(['berkas']);

        $this->assertNull($budi->fresh('certificate')->certificate->design_file_path);
    }

    public function test_replacing_a_design_removes_the_old_file(): void
    {
        $event = $this->event();
        $sertifikat = $this->issued($this->speaker($event, 'Budi Hartono'));

        CertificateDesignFile::attach($sertifikat, $this->temp($this->pdf()), 'Lama.pdf');
        $lama = $sertifikat->fresh()->design_file_path;

        CertificateDesignFile::attach($sertifikat->fresh(), $this->temp($this->png()), 'Baru.png');

        Storage::disk('local')->assertMissing($lama);
        Storage::disk('local')->assertExists($sertifikat->fresh()->design_file_path);
    }

    // ------------------------------------------------ verifikasi dan unduh

    public function test_before_the_design_arrives_the_page_asks_to_compare_the_details(): void
    {
        $sertifikat = $this->issued($this->speaker($this->event(), 'Budi Hartono'));

        $this->get($sertifikat->verificationUrl())
            ->assertOk()
            ->assertSee('Sertifikat valid')
            ->assertSee('didesain tersendiri oleh penerbitnya')
            ->assertSee($sertifikat->certificate_number)
            ->assertDontSee('Unduh sertifikat');

        $this->get($sertifikat->downloadUrl())->assertNotFound();
    }

    public function test_an_uploaded_image_is_shown_and_downloadable(): void
    {
        $sertifikat = $this->issued($this->speaker($this->event(), 'Budi Hartono'));
        CertificateDesignFile::attach($sertifikat, $this->temp($this->png()), 'Budi.png');
        $sertifikat = $sertifikat->fresh();

        $this->get($sertifikat->verificationUrl())
            ->assertOk()
            ->assertSee(route('certificates.design', $sertifikat->verification_token), false)
            ->assertSee('Unduh sertifikat');

        $this->get(route('certificates.design', $sertifikat->verification_token))
            ->assertOk()
            ->assertHeader('Content-Type', 'image/png');

        $this->get($sertifikat->downloadUrl())
            ->assertOk()
            ->assertDownload($sertifikat->downloadFileName());

        $this->assertStringEndsWith('.png', $sertifikat->downloadFileName());
    }

    public function test_a_pdf_design_is_offered_as_a_link(): void
    {
        $sertifikat = $this->issued($this->speaker($this->event(), 'Budi Hartono'));
        CertificateDesignFile::attach($sertifikat, $this->temp($this->pdf()), 'Budi.pdf');

        $this->get($sertifikat->verificationUrl())->assertSee('Lihat sertifikat (PDF)');
    }

    /**
     * QR bisa dicetak jauh hari: sebelum kegiatan dipublikasikan, halamannya
     * menyatakan belum berlaku dan berkasnya tidak bisa diunduh.
     */
    public function test_nothing_is_downloadable_before_publishing_or_after_revoking(): void
    {
        $draf = $this->issued($this->speaker($this->event(published: false), 'Budi Hartono'));
        CertificateDesignFile::attach($draf, $this->temp($this->png()), 'Budi.png');

        $this->get($draf->verificationUrl())->assertSee('Sertifikat tidak berlaku')->assertSee('Tidak Berlaku');
        $this->get($draf->downloadUrl())->assertNotFound();

        $dicabut = $this->issued($this->speaker($this->event(), 'Siti Rahma'));
        CertificateDesignFile::attach($dicabut, $this->temp($this->png()), 'Siti.png');
        $dicabut->update(['revoked_at' => now(), 'revocation_reason' => 'Salah penerima']);

        $this->get($dicabut->downloadUrl())->assertNotFound();
    }

    public function test_a_template_certificate_has_no_design_route(): void
    {
        $sertifikat = Certificate::factory()->create();

        $this->get(route('certificates.design', $sertifikat->verification_token))->assertNotFound();
    }

    // ----------------------------------------------------------------- email

    public function test_the_email_waits_until_the_design_is_uploaded(): void
    {
        $event = $this->event();
        $sertifikat = $this->issued($this->speaker($event, 'Budi Hartono', 'budi@jgu.ac.id'));
        $mailer = app(CertificateBatchMailer::class);

        $this->assertFalse($sertifikat->awaitsEmail());
        $this->assertSame(0, $mailer->pendingCountFor($event));

        CertificateDesignFile::attach($sertifikat, $this->temp($this->pdf()), 'Budi.pdf');

        $this->assertTrue($sertifikat->fresh()->awaitsEmail());
        $this->assertSame(1, $mailer->pendingCountFor($event));
    }

    // ------------------------------------------------------------ kegiatan

    public function test_the_template_stays_required_once_template_certificates_exist(): void
    {
        $this->actingAs($this->admin());

        $sendiri = $this->event();
        $this->issued($this->speaker($sendiri, 'Budi Hartono'));

        Livewire::test(EditCertificateEvent::class, ['record' => $sendiri->getRouteKey()])
            ->fillForm(['certificate_template_id' => null])
            ->call('save')
            ->assertHasNoFormErrors(['certificate_template_id']);

        $bertemplate = CertificateEvent::factory()->published()->create();
        Certificate::factory()->create(['certificate_event_id' => $bertemplate->id]);

        Livewire::test(EditCertificateEvent::class, ['record' => $bertemplate->getRouteKey()])
            ->fillForm(['certificate_template_id' => null])
            ->call('save')
            ->assertHasFormErrors(['certificate_template_id' => 'required']);
    }
}

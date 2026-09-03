<?php

namespace Tests\Feature;

use App\Models\Certificate;
use App\Models\CertificateEvent;
use App\Models\CertificateTemplate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Halaman verifikasi publik menampilkan pratinjau sertifikat, bukan sekadar
 * tabel data, sehingga pemeriksa langsung melihat dokumen yang dimaksud.
 */
class CertificateVerificationPageTest extends TestCase
{
    use RefreshDatabase;

    private function certificate(string $status = 'published'): Certificate
    {
        Storage::fake('public');
        Storage::disk('public')->put('certificates/templates/uji.png', 'gambar-palsu');

        $template = CertificateTemplate::factory()->create([
            'background_path' => 'certificates/templates/uji.png',
            'canvas_width' => 1123,
            'canvas_height' => 794,
            'elements' => [
                ['variable' => 'recipient_name', 'x' => 325, 'y' => 313, 'width' => 449, 'height' => 60, 'font_size' => 28],
                ['variable' => 'qr_code', 'x' => 89, 'y' => 406, 'width' => 120, 'height' => 120],
            ],
        ]);

        $event = CertificateEvent::factory()->create([
            'certificate_template_id' => $template->id,
            'status' => $status,
            'name' => 'Pelatihan Jurnalistik',
        ]);

        return Certificate::factory()->create([
            'certificate_event_id' => $event->id,
            'recipient_name' => 'Budi Santoso, S.Kom.',
        ]);
    }

    public function test_a_valid_certificate_is_previewed_with_its_template_and_values(): void
    {
        $certificate = $this->certificate();

        $response = $this->get($certificate->verificationUrl())->assertOk();

        $response->assertSee('Sertifikat valid');
        $response->assertSee('Budi Santoso, S.Kom.');
        $response->assertSee('Pelatihan Jurnalistik');
        $response->assertSee($certificate->certificate_number);

        // Latar template dan kanvasnya ikut dirender.
        $response->assertSee(Storage::disk('public')->url('certificates/templates/uji.png'), false);
        $response->assertSee('aspect-ratio: 1123 / 794', false);

        // Elemen ditempatkan relatif terhadap kanvas: 325 / 1123 = 28.9403%.
        $response->assertSee('left:28.9403%', false);
        $response->assertSee('data:image/png;base64,', false);
    }

    public function test_the_download_button_only_appears_for_a_valid_certificate(): void
    {
        $valid = $this->certificate();
        $this->get($valid->verificationUrl())
            ->assertOk()
            ->assertSee('Unduh sertifikat');

        $draft = $this->certificate('draft');
        $this->get($draft->verificationUrl())
            ->assertOk()
            ->assertDontSee('Unduh sertifikat');
    }

    public function test_an_invalid_certificate_is_previewed_with_a_watermark(): void
    {
        $certificate = $this->certificate('draft');

        $this->get($certificate->verificationUrl())
            ->assertOk()
            ->assertSee('Sertifikat tidak berlaku')
            ->assertSee('Tidak Berlaku')
            ->assertSee('belum dipublikasikan');
    }

    public function test_a_revoked_certificate_says_when_it_was_revoked(): void
    {
        $certificate = $this->certificate();
        $certificate->update(['revoked_at' => now(), 'revocation_reason' => 'Kesalahan data']);

        $this->get($certificate->verificationUrl())
            ->assertOk()
            ->assertSee('Sertifikat tidak berlaku')
            ->assertSee('dicabut pada');
    }

    public function test_an_unknown_token_is_not_found(): void
    {
        $this->get(route('certificates.verify', 'token-tidak-dikenal'))->assertNotFound();
    }
}

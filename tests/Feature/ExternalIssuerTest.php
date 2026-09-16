<?php

namespace Tests\Feature;

use App\Enums\CertificateNumberReset;
use App\Models\Certificate;
use App\Models\CertificateEvent;
use App\Models\CertificateEventParticipant;
use App\Models\Issuer;
use App\Models\Participant;
use App\Notifications\CertificateIssued;
use App\Services\Certificates\CertificateIssuer;
use App\Services\Certificates\CertificateNumberFormat;
use App\Support\AppSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * MSC dapat menerbitkan sertifikat untuk pihak di luar JGU.
 *
 * Sertifikat mitra harus tampil sebagai terbitan mitra — bukan JGU — dan
 * memakai urutan nomornya sendiri, tanpa mengubah apa pun bagi kegiatan lama
 * yang tidak menyebut penerbit.
 */
class ExternalIssuerTest extends TestCase
{
    use RefreshDatabase;

    private function partner(array $attributes = []): Issuer
    {
        return Issuer::factory()->create([
            'name' => 'Lembaga Pengembangan Perbankan Indonesia',
            'code' => 'LPPI',
            'address_line' => 'Jakarta Selatan',
            ...$attributes,
        ]);
    }

    private function eligible(CertificateEvent $event): CertificateEventParticipant
    {
        return CertificateEventParticipant::factory()->eligible()->create([
            'certificate_event_id' => $event->id,
            'participant_id' => Participant::factory(),
        ]);
    }

    // --------------------------------------------------------- penerbit rumah

    public function test_the_house_issuer_is_created_by_the_migration(): void
    {
        $house = Issuer::house();

        $this->assertNotNull($house);
        $this->assertTrue($house->is_house);
        $this->assertSame('MSC-JGU', $house->code);
    }

    public function test_an_event_without_an_issuer_falls_back_to_the_house(): void
    {
        $event = CertificateEvent::factory()->create(['issuer_id' => null]);

        $this->assertTrue($event->resolvedIssuer()->is_house);
    }

    // ------------------------------------------------------------- penomoran

    public function test_each_issuer_keeps_its_own_running_sequence(): void
    {
        $this->travelTo(now()->setDate(2026, 9, 6));
        AppSetting::set(CertificateNumberFormat::SETTING_PATTERN, '{nomor:4}/CERT/{kode_unit}/{tahun}');

        $jgu = CertificateEvent::factory()->published()->create(['issuer_id' => null]);
        $partner = CertificateEvent::factory()->published()->create(['issuer_id' => $this->partner()->id]);

        $issuer = app(CertificateIssuer::class);

        $this->assertSame('0001/CERT/MSC-JGU/2026', $issuer->issue($this->eligible($jgu))->certificate_number);
        // Sertifikat mitra mulai dari 1 lagi, tidak menggerus urutan JGU.
        $this->assertSame('0001/CERT/LPPI/2026', $issuer->issue($this->eligible($partner))->certificate_number);
        $this->assertSame('0002/CERT/MSC-JGU/2026', $issuer->issue($this->eligible($jgu))->certificate_number);
        $this->assertSame('0002/CERT/LPPI/2026', $issuer->issue($this->eligible($partner))->certificate_number);
    }

    public function test_an_issuer_may_carry_its_own_pattern_and_reset(): void
    {
        AppSetting::set(CertificateNumberFormat::SETTING_PATTERN, '{nomor:4}/CERT/{kode_unit}/{tahun}');

        $partner = $this->partner([
            'number_pattern' => '{nomor:3}/PLT/{kode_kegiatan}',
            'number_reset' => CertificateNumberReset::PER_EVENT->value,
        ]);

        $first = CertificateEvent::factory()->published()->create(['issuer_id' => $partner->id, 'certificate_code' => 'SATU']);
        $second = CertificateEvent::factory()->published()->create(['issuer_id' => $partner->id, 'certificate_code' => 'DUA']);
        $issuer = app(CertificateIssuer::class);

        $issuer->issue($this->eligible($first));
        $this->assertSame('002/PLT/SATU', $issuer->issue($this->eligible($first))->certificate_number);
        $this->assertSame('001/PLT/DUA', $issuer->issue($this->eligible($second))->certificate_number);
    }

    public function test_the_event_pattern_still_beats_the_issuer_pattern(): void
    {
        $partner = $this->partner(['number_pattern' => '{nomor:3}/PLT/{kode_unit}']);

        $event = CertificateEvent::factory()->published()->create([
            'issuer_id' => $partner->id,
            'certificate_number_format' => 'KHUSUS-{nomor:2}',
        ]);

        $this->assertSame(
            'KHUSUS-01',
            app(CertificateIssuer::class)->issue($this->eligible($event))->certificate_number,
        );
    }

    // -------------------------------------------------------------- branding

    public function test_the_verification_page_carries_the_partner_identity(): void
    {
        Storage::fake('public');
        $partner = $this->partner();
        $event = CertificateEvent::factory()->published()->create(['issuer_id' => $partner->id]);
        $certificate = Certificate::factory()->create(['certificate_event_id' => $event->id]);

        $response = $this->get($certificate->verificationUrl())->assertOk();

        $response->assertSee('Lembaga Pengembangan Perbankan Indonesia');
        $response->assertSee('Jakarta Selatan');
        // Peran MSC disebut sebagai fasilitator, bukan penerbit.
        $response->assertSee('difasilitasi Media &amp; Strategic Communications Jakarta Global University', false);
    }

    public function test_a_house_certificate_keeps_the_original_wording(): void
    {
        $event = CertificateEvent::factory()->published()->create(['issuer_id' => null]);
        $certificate = Certificate::factory()->create(['certificate_event_id' => $event->id]);

        $this->get($certificate->verificationUrl())
            ->assertOk()
            ->assertSee('Media &amp; Strategic Communications, Jakarta Global University', false)
            ->assertDontSee('difasilitasi');
    }

    public function test_a_custom_verification_note_overrides_the_default_wording(): void
    {
        $partner = $this->partner(['verification_note' => 'Sertifikat resmi LPPI, tercatat pada sistem MSC JGU.']);
        $event = CertificateEvent::factory()->published()->create(['issuer_id' => $partner->id]);
        $certificate = Certificate::factory()->create(['certificate_event_id' => $event->id]);

        $this->get($certificate->verificationUrl())
            ->assertOk()
            ->assertSee('Sertifikat resmi LPPI, tercatat pada sistem MSC JGU.');
    }

    public function test_the_email_header_follows_the_issuer(): void
    {
        $partner = $this->partner();
        $event = CertificateEvent::factory()->published()->create(['issuer_id' => $partner->id]);
        $certificate = Certificate::factory()->create([
            'certificate_event_id' => $event->id,
            'recipient_email' => 'budi@student.jgu.ac.id',
        ]);

        $rendered = (new CertificateIssued($certificate->fresh('event.issuer')))->toMail($certificate)->render();

        $this->assertStringContainsString('Lembaga Pengembangan Perbankan Indonesia', $rendered);
        $this->assertStringContainsString('Diterbitkan oleh', $rendered);
    }

    // ------------------------------------------------------------- sertifikat

    public function test_the_printed_organizer_falls_back_to_the_issuer_name(): void
    {
        $partner = $this->partner();
        $event = CertificateEvent::factory()->published()->create([
            'issuer_id' => $partner->id,
            'organizer' => null,
        ]);
        $certificate = Certificate::factory()->create(['certificate_event_id' => $event->id]);

        $values = app(\App\Services\CertificateRenderService::class)->variables($certificate->fresh('event.issuer'));

        $this->assertSame('Lembaga Pengembangan Perbankan Indonesia', $values['organizer']);
    }

    public function test_an_explicit_organizer_is_never_replaced(): void
    {
        $partner = $this->partner();
        $event = CertificateEvent::factory()->published()->create([
            'issuer_id' => $partner->id,
            'organizer' => 'Panitia Bersama DIGDAYA',
        ]);
        $certificate = Certificate::factory()->create(['certificate_event_id' => $event->id]);

        $values = app(\App\Services\CertificateRenderService::class)->variables($certificate->fresh('event.issuer'));

        $this->assertSame('Panitia Bersama DIGDAYA', $values['organizer']);
    }
}

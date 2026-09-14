<?php

namespace Tests\Feature;

use App\Models\CertificateEvent;
use App\Models\CertificateEventParticipant;
use App\Models\Participant;
use App\Services\Certificates\CertificateIssuer;
use App\Support\CertificateStage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Tahap perjalanan sertifikat seseorang.
 *
 * Keadaannya dulu tersebar di beberapa kolom terpisah — eligible, sertifikat,
 * email — dan admin harus merangkainya sendiri. Aturannya kini satu tempat,
 * jadi tabel dan penyaringnya tidak bisa berbeda pendapat.
 */
class CertificateStageTest extends TestCase
{
    use RefreshDatabase;

    private function participation(bool $eligible = true): CertificateEventParticipant
    {
        $event = CertificateEvent::factory()->published()->create();

        $factory = CertificateEventParticipant::factory();

        return ($eligible ? $factory->eligible() : $factory)->create([
            'certificate_event_id' => $event->id,
            'participant_id' => Participant::factory(),
        ]);
    }

    public function test_someone_not_yet_entitled_is_waiting_on_the_admin(): void
    {
        $stage = CertificateStage::for($this->participation(eligible: false));

        $this->assertSame(CertificateStage::NOT_ELIGIBLE, $stage);
        $this->assertStringContainsString('Tandai berhak', $stage->getHint());
    }

    public function test_someone_entitled_without_a_certificate_is_ready(): void
    {
        $this->assertSame(CertificateStage::READY, CertificateStage::for($this->participation()));
    }

    /**
     * Inti pemisahan dua tahap: terbit bukan berarti terkirim.
     */
    public function test_a_freshly_issued_certificate_is_not_yet_sent(): void
    {
        $participation = $this->participation();
        app(CertificateIssuer::class)->issue($participation);

        $stage = CertificateStage::for($participation->fresh(['certificate']));

        $this->assertSame(CertificateStage::ISSUED, $stage);
        $this->assertSame('Terbit, belum dikirim', $stage->getLabel());
    }

    public function test_a_sent_certificate_reads_as_finished(): void
    {
        $participation = $this->participation();
        app(CertificateIssuer::class)->issue($participation)->forceFill(['emailed_at' => now()])->save();

        $this->assertSame(CertificateStage::SENT, CertificateStage::for($participation->fresh(['certificate'])));
    }

    public function test_a_failed_delivery_asks_the_admin_to_look_at_the_address(): void
    {
        $participation = $this->participation();
        app(CertificateIssuer::class)->issue($participation)
            ->forceFill(['email_failed_at' => now(), 'email_error' => 'SMTP menolak'])->save();

        $stage = CertificateStage::for($participation->fresh(['certificate']));

        $this->assertSame(CertificateStage::FAILED, $stage);
        $this->assertStringContainsString('alamat email', $stage->getHint());
    }

    public function test_a_recipient_without_an_address_is_named_as_such(): void
    {
        $participation = $this->participation();
        app(CertificateIssuer::class)->issue($participation)->forceFill(['recipient_email' => null])->save();

        $this->assertSame(
            CertificateStage::ISSUED_WITHOUT_EMAIL,
            CertificateStage::for($participation->fresh(['certificate'])),
        );
    }

    /**
     * Pencabutan mengalahkan keadaan lain: sertifikat yang dicabut tidak lagi
     * boleh terbaca sebagai sudah terkirim.
     */
    public function test_revoking_overrides_everything_else(): void
    {
        $participation = $this->participation();
        app(CertificateIssuer::class)->issue($participation)
            ->forceFill(['emailed_at' => now(), 'revoked_at' => now()])->save();

        $this->assertSame(CertificateStage::REVOKED, CertificateStage::for($participation->fresh(['certificate'])));
    }

    public function test_every_stage_carries_a_label_a_colour_and_a_next_step(): void
    {
        foreach (CertificateStage::cases() as $stage) {
            $this->assertNotSame('', $stage->getLabel());
            $this->assertNotSame('', $stage->getColor());
            $this->assertNotSame('', $stage->getHint());
        }

        $this->assertCount(count(CertificateStage::cases()), CertificateStage::options());
    }
}

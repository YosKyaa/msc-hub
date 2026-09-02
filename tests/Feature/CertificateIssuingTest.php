<?php

namespace Tests\Feature;

use App\Jobs\IssueCertificateJob;
use App\Jobs\SendCertificateEmailJob;
use App\Models\Certificate;
use App\Models\CertificateEvent;
use App\Models\CertificateEventParticipant;
use App\Models\Participant;
use App\Notifications\CertificateIssued;
use App\Services\Certificates\CertificateBatchException;
use App\Services\Certificates\CertificateBatchIssuer;
use App\Services\Certificates\CertificateIssuer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class CertificateIssuingTest extends TestCase
{
    use RefreshDatabase;

    private function eligibleParticipation(CertificateEvent $event): CertificateEventParticipant
    {
        return CertificateEventParticipant::factory()->eligible()->create([
            'certificate_event_id' => $event->id,
            'participant_id' => Participant::factory(),
        ]);
    }

    public function test_a_batch_is_dispatched_for_every_eligible_participant_without_a_certificate(): void
    {
        Bus::fake();

        $event = CertificateEvent::factory()->published()->create();
        $this->eligibleParticipation($event);
        $this->eligibleParticipation($event);
        $this->eligibleParticipation($event);

        // Belum eligible: tidak ikut diantrekan.
        CertificateEventParticipant::factory()->create(['certificate_event_id' => $event->id]);

        app(CertificateBatchIssuer::class)->dispatchFor($event);

        Bus::assertBatched(fn ($batch) => $batch->jobs->count() === 3);
    }

    public function test_issuing_is_idempotent_when_a_job_runs_twice(): void
    {
        Queue::fake();

        $event = CertificateEvent::factory()->published()->create();
        $participation = $this->eligibleParticipation($event);

        $job = new IssueCertificateJob($participation);
        $job->handle(app(CertificateIssuer::class));
        $job->handle(app(CertificateIssuer::class));

        $this->assertSame(1, Certificate::count());
        $this->assertSame($participation->id, Certificate::sole()->event_participant_id);
    }

    public function test_participants_that_already_have_a_certificate_are_skipped(): void
    {
        Bus::fake();

        $event = CertificateEvent::factory()->published()->create();
        $withCertificate = $this->eligibleParticipation($event);
        Certificate::factory()->create([
            'certificate_event_id' => $event->id,
            'event_participant_id' => $withCertificate->id,
        ]);
        $this->eligibleParticipation($event);

        app(CertificateBatchIssuer::class)->dispatchFor($event);

        Bus::assertBatched(fn ($batch) => $batch->jobs->count() === 1);
    }

    public function test_a_batch_without_anything_to_issue_is_refused(): void
    {
        Bus::fake();

        $event = CertificateEvent::factory()->published()->create();

        $this->expectException(CertificateBatchException::class);
        $this->expectExceptionMessage('Tidak ada peserta eligible');

        app(CertificateBatchIssuer::class)->dispatchFor($event);
    }

    public function test_an_inactive_template_blocks_issuing(): void
    {
        Bus::fake();

        $event = CertificateEvent::factory()->published()->create();
        $event->template->update(['is_active' => false]);
        $this->eligibleParticipation($event);

        $this->expectException(CertificateBatchException::class);
        $this->expectExceptionMessage('template sertifikat yang aktif');

        app(CertificateBatchIssuer::class)->dispatchFor($event->fresh());
    }

    public function test_a_custom_certificate_number_from_the_import_is_reused(): void
    {
        $event = CertificateEvent::factory()->published()->create();
        $participation = $this->eligibleParticipation($event);
        $participation->update(['certificate_number' => 'CERT-IMPOR-001']);

        $certificate = app(CertificateIssuer::class)->issue($participation->fresh());

        $this->assertSame('CERT-IMPOR-001', $certificate->certificate_number);
    }

    public function test_email_is_not_sent_while_the_event_is_still_a_draft(): void
    {
        Notification::fake();

        $event = CertificateEvent::factory()->create(); // draft
        $participation = $this->eligibleParticipation($event);
        $certificate = app(CertificateIssuer::class)->issue($participation);

        (new SendCertificateEmailJob($certificate))->handle();

        Notification::assertNothingSent();
        $this->assertNull($certificate->fresh()->emailed_at);
    }

    public function test_publishing_the_event_dispatches_the_pending_emails(): void
    {
        Queue::fake();

        $event = CertificateEvent::factory()->create();
        $participation = $this->eligibleParticipation($event);
        $certificate = app(CertificateIssuer::class)->issue($participation);

        $event->update(['status' => 'published']);

        Queue::assertPushed(
            SendCertificateEmailJob::class,
            fn (SendCertificateEmailJob $job) => $job->certificate->is($certificate),
        );
    }

    public function test_an_already_emailed_certificate_is_never_emailed_twice(): void
    {
        Notification::fake();

        $event = CertificateEvent::factory()->published()->create();
        $certificate = app(CertificateIssuer::class)->issue($this->eligibleParticipation($event));

        $job = new SendCertificateEmailJob($certificate);
        $job->handle();
        $job->handle();

        Notification::assertSentTimes(CertificateIssued::class, 1);
        $this->assertNotNull($certificate->fresh()->emailed_at);
    }

    public function test_the_email_links_to_the_download_and_verification_routes(): void
    {
        $event = CertificateEvent::factory()->published()->create();
        $certificate = app(CertificateIssuer::class)->issue($this->eligibleParticipation($event));

        $rendered = (new CertificateIssued($certificate))->toMail($certificate)->render();

        $this->assertStringContainsString(route('certificates.download', $certificate->verification_token), $rendered);
        $this->assertStringContainsString(route('certificates.verify', $certificate->verification_token), $rendered);
        $this->assertStringContainsString($certificate->certificate_number, $rendered);
    }

    public function test_a_revoked_certificate_is_not_emailed(): void
    {
        Notification::fake();

        $event = CertificateEvent::factory()->published()->create();
        $certificate = app(CertificateIssuer::class)->issue($this->eligibleParticipation($event));
        $certificate->update(['revoked_at' => now(), 'revocation_reason' => 'Kesalahan data']);

        (new SendCertificateEmailJob($certificate))->handle();

        Notification::assertNothingSent();
    }

    public function test_a_failed_email_is_recorded_for_follow_up(): void
    {
        $event = CertificateEvent::factory()->published()->create();
        $certificate = app(CertificateIssuer::class)->issue($this->eligibleParticipation($event));

        (new SendCertificateEmailJob($certificate))->failed(new \RuntimeException('SMTP menolak koneksi'));

        $certificate->refresh();
        $this->assertNotNull($certificate->email_failed_at);
        $this->assertStringContainsString('SMTP menolak koneksi', $certificate->email_error);
    }
}

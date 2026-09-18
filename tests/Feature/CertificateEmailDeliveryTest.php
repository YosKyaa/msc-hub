<?php

namespace Tests\Feature;

use App\Jobs\SendCertificateEmailJob;
use App\Models\Certificate;
use App\Models\CertificateEvent;
use App\Models\Issuer;
use App\Notifications\CertificateIssued;
use App\Services\Certificates\CertificateBatchMailer;
use App\Services\Certificates\CertificateMailProbe;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use RuntimeException;
use Symfony\Component\Mailer\Exception\TransportException;
use Tests\TestCase;

/**
 * Email sertifikat benar-benar sampai, dan kegagalannya tidak diam.
 *
 * Di produksi pengirimannya pernah tidak jalan sama sekali tanpa jejak apa
 * pun di panel. Dua sebabnya sama-sama tidak terlihat dari layar: penyedia
 * SMTP menolak kiriman yang terlalu rapat ("550 Too many emails per second"),
 * dan alasan kegagalan baru dicatat setelah ketiga percobaan habis — atau
 * tidak pernah, bila antreannya memang tidak ada yang mengerjakan.
 */
class CertificateEmailDeliveryTest extends TestCase
{
    use RefreshDatabase;

    private function certificate(array $extra = []): Certificate
    {
        $event = CertificateEvent::factory()->published()->create();

        return Certificate::factory()->create([
            'certificate_event_id' => $event->id,
            'recipient_email' => 'peserta@student.jgu.ac.id',
            'recipient_name' => 'Budi Santoso',
            'emailed_at' => null,
            ...$extra,
        ]);
    }

    // --------------------------------------------------------- templatenya

    /**
     * Templat yang tidak bisa dirender membuat setiap pengiriman gagal, dan
     * kegagalannya hanya muncul sebagai galat antrean yang tidak dibaca siapa
     * pun.
     */
    public function test_the_email_renders_with_everything_a_recipient_needs(): void
    {
        $certificate = $this->certificate();

        $mail = (new CertificateIssued($certificate))->toMail((object) []);
        $html = $mail->render();

        $this->assertStringContainsString($certificate->recipient_name, $html);
        $this->assertStringContainsString($certificate->certificate_number, $html);
        $this->assertStringContainsString($certificate->event->name, $html);

        // Dua tautan itulah alasan email ini dikirim.
        $this->assertStringContainsString($certificate->downloadUrl(), $html);
        $this->assertStringContainsString($certificate->verificationUrl(), $html);

        $this->assertStringContainsString($certificate->event->name, (string) $mail->subject);
    }

    /**
     * Sertifikat terbitan mitra tidak boleh sampai ke peserta sebagai
     * terbitan JGU.
     */
    public function test_the_email_carries_the_issuer_letterhead(): void
    {
        $issuer = Issuer::factory()->create(['name' => 'Student Career Development']);
        $event = CertificateEvent::factory()->published()->create(['issuer_id' => $issuer->id]);

        $certificate = Certificate::factory()->create([
            'certificate_event_id' => $event->id,
            'recipient_email' => 'peserta@student.jgu.ac.id',
        ]);

        $this->assertStringContainsString(
            'Student Career Development',
            (new CertificateIssued($certificate))->toMail((object) [])->render(),
        );
    }

    // ------------------------------------------------------- laju kirimnya

    /**
     * Inti kegagalan di produksi: penyedia SMTP menolak kiriman yang terlalu
     * rapat. Melepas seratus email sekaligus justru membuat sebagian besarnya
     * tidak sampai.
     */
    public function test_the_sending_is_spaced_out_so_the_provider_does_not_refuse(): void
    {
        config(['msc.certificates.emails_per_minute' => 20]);
        Bus::fake();

        $event = CertificateEvent::factory()->published()->create();

        foreach (range(1, 5) as $i) {
            Certificate::factory()->create([
                'certificate_event_id' => $event->id,
                'recipient_email' => "peserta{$i}@student.jgu.ac.id",
                'emailed_at' => null,
            ]);
        }

        app(CertificateBatchMailer::class)->dispatchFor($event->fresh());

        Bus::assertBatched(function ($batch): bool {
            $jeda = $batch->jobs
                ->map(fn (SendCertificateEmailJob $job) => (int) round(now()->diffInSeconds($job->delay ?? now())))
                ->sort()
                ->values();

            // 20 per menit berarti satu tiap tiga detik.
            $this->assertEqualsWithDelta(0, $jeda[0], 1);
            $this->assertEqualsWithDelta(12, $jeda[4], 1,
                'Email terakhir tidak dijarakkan, jadi seluruhnya dilepas sekaligus.');

            return true;
        });
    }

    public function test_a_faster_plan_may_send_faster(): void
    {
        config(['msc.certificates.emails_per_minute' => 60]);
        Bus::fake();

        $event = CertificateEvent::factory()->published()->create();

        foreach (range(1, 3) as $i) {
            Certificate::factory()->create([
                'certificate_event_id' => $event->id,
                'recipient_email' => "peserta{$i}@student.jgu.ac.id",
                'emailed_at' => null,
            ]);
        }

        app(CertificateBatchMailer::class)->dispatchFor($event->fresh());

        Bus::assertBatched(function ($batch): bool {
            $terakhir = $batch->jobs
                ->map(fn (SendCertificateEmailJob $job) => (int) round(now()->diffInSeconds($job->delay ?? now())))
                ->max();

            // 60 per menit berarti satu tiap detik.
            $this->assertEqualsWithDelta(2, $terakhir, 1);

            return true;
        });
    }

    public function test_the_wait_is_stated_before_anyone_starts_waiting(): void
    {
        config(['msc.certificates.emails_per_minute' => 20]);

        $mailer = app(CertificateBatchMailer::class);

        $this->assertSame(1, $mailer->estimatedMinutesFor(5));
        $this->assertSame(5, $mailer->estimatedMinutesFor(100));
    }

    // -------------------------------------------------- kegagalan bersuara

    /**
     * Kegagalan harus tercatat pada percobaan pertama, bukan setelah ketiganya
     * habis. failed() baru berjalan tujuh menit kemudian — dan tidak pernah
     * berjalan bila antreannya tidak ada yang mengerjakan.
     */
    public function test_a_failure_is_recorded_on_the_very_first_attempt(): void
    {
        $certificate = $this->certificate();

        // route() dibiarkan apa adanya; yang dibuat gagal adalah pengirimannya,
        // seperti saat penyedia SMTP menolak.
        Notification::partialMock()
            ->shouldReceive('send')
            ->andThrow(new TransportException('550 5.7.0 Too many emails per second.'));

        Log::shouldReceive('warning')
            ->once()
            ->withArgs(fn (string $pesan, array $konteks): bool => str_contains($pesan, 'Percobaan mengirim email')
                && str_contains($konteks['error'], 'Too many emails per second')
                && $konteks['recipient_email'] === $certificate->recipient_email);

        try {
            (new SendCertificateEmailJob($certificate))->handle();
            $this->fail('Galat pengiriman tidak diteruskan, sehingga antrean tidak akan mencoba lagi.');
        } catch (TransportException) {
            // Diteruskan supaya antrean mengulangnya.
        }

        $certificate->refresh();

        $this->assertNotNull($certificate->email_failed_at);
        $this->assertStringContainsString('Too many emails per second', $certificate->email_error);
        $this->assertNull($certificate->emailed_at, 'Sertifikat ditandai terkirim padahal gagal.');
    }

    /**
     * Setelah semua percobaan habis, catatannya naik menjadi galat supaya
     * terlihat saat log dipindai.
     */
    public function test_giving_up_is_logged_as_an_error(): void
    {
        $certificate = $this->certificate();

        Log::shouldReceive('error')
            ->once()
            ->withArgs(fn (string $pesan, array $konteks): bool => str_contains($pesan, 'tidak dicoba lagi')
                && $konteks['certificate_id'] === $certificate->id);

        (new SendCertificateEmailJob($certificate))->failed(new RuntimeException('SMTP tidak menjawab'));

        $certificate->refresh();

        $this->assertNotNull($certificate->email_failed_at);
        $this->assertSame('SMTP tidak menjawab', $certificate->email_error);
    }

    /**
     * Pengiriman yang berhasil harus membersihkan bekas kegagalan sebelumnya,
     * kalau tidak panel terus menampilkan kendala yang sudah teratasi.
     */
    public function test_a_successful_send_clears_the_earlier_failure(): void
    {
        Notification::fake();

        $certificate = $this->certificate([
            'email_failed_at' => now()->subHour(),
            'email_error' => 'Kegagalan yang sudah lewat',
        ]);

        (new SendCertificateEmailJob($certificate))->handle();

        $certificate->refresh();

        $this->assertNotNull($certificate->emailed_at);
        $this->assertNull($certificate->email_failed_at);
        $this->assertNull($certificate->email_error);
    }

    // ------------------------------------------------------------ kirim uji

    /**
     * Setelan SMTP yang salah baru ketahuan saat seluruh peserta dikirimi, dan
     * saat itu kegagalannya sudah menyebar.
     */
    public function test_a_test_email_proves_the_delivery_path_without_touching_participants(): void
    {
        Notification::fake();

        $event = CertificateEvent::factory()->published()->create();

        $galat = app(CertificateMailProbe::class)->send($event, 'admin@jgu.ac.id');

        $this->assertNull($galat);
        Notification::assertSentOnDemand(CertificateIssued::class);

        // Tidak ada sertifikat yang ikut tercipta atau tertandai terkirim.
        $this->assertSame(0, $event->certificates()->count());
    }

    public function test_a_test_email_reports_what_the_mail_server_said(): void
    {
        $event = CertificateEvent::factory()->published()->create();

        Notification::partialMock()
            ->shouldReceive('send')
            ->andThrow(new TransportException('535 5.7.8 Username and Password not accepted'));

        Log::shouldReceive('error')->once();

        $galat = app(CertificateMailProbe::class)->send($event, 'admin@jgu.ac.id');

        $this->assertStringContainsString('Username and Password not accepted', (string) $galat);
    }

    /**
     * Bila kegiatannya sudah punya sertifikat, yang dipakai adalah yang
     * sungguhan — sehingga tautan unduh dan verifikasinya ikut terbukti.
     */
    public function test_a_test_email_uses_a_real_certificate_when_there_is_one(): void
    {
        Mail::fake();
        Notification::fake();

        $certificate = $this->certificate();

        app(CertificateMailProbe::class)->send($certificate->event, 'admin@jgu.ac.id');

        Notification::assertSentOnDemand(
            CertificateIssued::class,
            fn (CertificateIssued $notification): bool => $notification->certificate->is($certificate),
        );
    }
}

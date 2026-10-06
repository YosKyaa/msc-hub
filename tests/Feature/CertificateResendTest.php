<?php

namespace Tests\Feature;

use App\Filament\Resources\CertificateEventResource\RelationManagers\ParticipationsRelationManager;
use App\Jobs\SendCertificateEmailJob;
use App\Models\Certificate;
use App\Models\CertificateEvent;
use App\Models\CertificateEventParticipant;
use App\Models\Participant;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Livewire\Livewire;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Kirim ulang email sertifikat.
 *
 * Aksi kirim biasa sengaja melewati penerima yang sudah pernah dikirimi, agar
 * tidak ada yang menerima dua kali tanpa diminta. Tetapi ketika pengantar
 * emailnya ternyata salah setel — MAIL_MAILER=log, misalnya — seluruh
 * sertifikat tertandai terkirim padahal tidak pernah sampai ke siapa pun.
 * Tanpa kirim ulang massal, satu-satunya jalan keluar adalah mengulanginya
 * satu per satu.
 */
class CertificateResendTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $this->seed(RoleSeeder::class);

        $user = User::factory()->create();
        $user->assignRole('admin');

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return $user->fresh();
    }

    private function event(): CertificateEvent
    {
        return CertificateEvent::factory()->published()->create();
    }

    private function participantWithCertificate(CertificateEvent $event, array $extra = []): CertificateEventParticipant
    {
        $participant = Participant::factory()->create();

        $keikutsertaan = CertificateEventParticipant::create([
            'certificate_event_id' => $event->id,
            'participant_id' => $participant->id,
            'role' => 'participant',
            'eligible_at' => now(),
            'attendance_status' => 'attended',
        ]);

        Certificate::factory()->create([
            'certificate_event_id' => $event->id,
            'event_participant_id' => $keikutsertaan->id,
            'participant_id' => $participant->id,
            'recipient_email' => $participant->email,
            ...$extra,
        ]);

        return $keikutsertaan->fresh();
    }

    private function table(CertificateEvent $event)
    {
        return Livewire::actingAs($this->admin())->test(ParticipationsRelationManager::class, [
            'ownerRecord' => $event,
            'pageClass' => \App\Filament\Resources\CertificateEventResource\Pages\EditCertificateEvent::class,
        ]);
    }

    /**
     * Inti perbaikannya: yang sudah tertandai terkirim pun ikut diantrekan.
     */
    public function test_a_bulk_resend_reaches_certificates_already_marked_as_sent(): void
    {
        Bus::fake();

        $event = $this->event();
        $sudah = $this->participantWithCertificate($event, ['emailed_at' => now()->subHour()]);

        $this->table($event)->callTableBulkAction('resendEmails', [$sudah->getKey()]);

        Bus::assertBatched(fn ($batch) => $batch->jobs->count() === 1);

        // Penanda dibersihkan, kalau tidak job akan berhenti tanpa berbuat apa-apa.
        $this->assertNull($sudah->fresh()->certificate->emailed_at);
    }

    /**
     * Pembedanya dengan aksi kirim biasa, yang memang melewati mereka.
     */
    public function test_the_plain_send_still_skips_those_already_sent(): void
    {
        Bus::fake();

        $event = $this->event();
        $sudah = $this->participantWithCertificate($event, ['emailed_at' => now()->subHour()]);

        $this->table($event)->callTableBulkAction('sendEmails', [$sudah->getKey()]);

        Bus::assertNothingBatched();
        $this->assertNotNull($sudah->fresh()->certificate->emailed_at);
    }

    public function test_a_bulk_resend_clears_an_earlier_failure(): void
    {
        Bus::fake();

        $event = $this->event();
        $gagal = $this->participantWithCertificate($event, [
            'email_failed_at' => now()->subHour(),
            'email_error' => 'Alamat tidak ditemukan',
        ]);

        $this->table($event)->callTableBulkAction('resendEmails', [$gagal->getKey()]);

        $sertifikat = $gagal->fresh()->certificate;

        $this->assertNull($sertifikat->email_failed_at);
        $this->assertNull($sertifikat->email_error);
    }

    /**
     * Sertifikat yang dicabut tidak boleh ikut terkirim ulang: ia memang
     * sudah tidak berlaku.
     */
    public function test_a_revoked_certificate_is_left_alone(): void
    {
        Bus::fake();

        $event = $this->event();
        $dicabut = $this->participantWithCertificate($event, [
            'emailed_at' => now()->subHour(),
            'revoked_at' => now(),
            'revocation_reason' => 'Salah nama',
        ]);

        $this->table($event)->callTableBulkAction('resendEmails', [$dicabut->getKey()]);

        Bus::assertNothingBatched();

        // Penandanya pun tidak disentuh.
        $this->assertNotNull($dicabut->fresh()->certificate->emailed_at);
    }

    /**
     * Beberapa sekaligus, yang justru menjadi alasan aksi ini ada.
     */
    public function test_several_certificates_are_resent_in_one_go(): void
    {
        Bus::fake();

        $event = $this->event();

        $keikutsertaan = collect(range(1, 4))
            ->map(fn () => $this->participantWithCertificate($event, ['emailed_at' => now()->subHour()]));

        $this->table($event)->callTableBulkAction('resendEmails', $keikutsertaan->map->getKey()->all());

        Bus::assertBatched(fn ($batch) => $batch->jobs->count() === 4);

        foreach ($keikutsertaan as $satu) {
            $this->assertNull($satu->fresh()->certificate->emailed_at);
        }
    }

    public function test_the_job_itself_is_the_one_that_gets_queued(): void
    {
        Bus::fake();

        $event = $this->event();
        $satu = $this->participantWithCertificate($event, ['emailed_at' => now()->subHour()]);

        $this->table($event)->callTableBulkAction('resendEmails', [$satu->getKey()]);

        Bus::assertBatched(fn ($batch) => $batch->jobs->first() instanceof SendCertificateEmailJob);
    }
}

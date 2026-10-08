<?php

namespace Tests\Feature;

use App\Filament\Resources\CertificateEventResource\Pages\EditCertificateEvent;
use App\Filament\Resources\CertificateEventResource\RelationManagers\ParticipationsRelationManager;
use App\Models\Certificate;
use App\Models\CertificateEvent;
use App\Models\User;
use App\Services\Certificates\CertificateBatchException;
use App\Services\Certificates\CertificateBatchMailer;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Kuota harian akun pengirim.
 *
 * Email sertifikat dikirim dari akun Google Workspace `no-reply@jgu.ac.id`,
 * yang dibatasi 2.000 penerima per 24 jam. Gmail yang menerima kiriman
 * melewati batas itu mengunci akunnya sampai sehari penuh, dan selama itu
 * seluruh email aplikasi tertahan — bukan hanya sertifikat, tetapi juga
 * pemberitahuan peminjaman. Satu kegiatan besar cukup untuk memicunya.
 *
 * Yang melebihi kuota karena itu ditahan di sini, tetap berstatus menunggu
 * kirim, dan berangkat ketika tombolnya ditekan lagi setelah kuotanya terbuka.
 */
class CertificateEmailQuotaTest extends TestCase
{
    use RefreshDatabase;

    private function event(): CertificateEvent
    {
        return CertificateEvent::factory()->published()->create();
    }

    private function awaiting(CertificateEvent $event, int $jumlah): void
    {
        foreach (range(1, $jumlah) as $i) {
            Certificate::factory()->create([
                'certificate_event_id' => $event->id,
                'recipient_email' => 'menunggu'.Str::random(6)."{$i}@student.jgu.ac.id",
                'emailed_at' => null,
            ]);
        }
    }

    /**
     * Email yang sudah terkirim pada waktu tertentu, di kegiatan lain: kuota
     * itu milik akun pengirim, bukan milik satu kegiatan.
     */
    private function alreadySent(int $jumlah, Carbon $pada): void
    {
        $lain = $this->event();

        foreach (range(1, $jumlah) as $i) {
            Certificate::factory()->create([
                'certificate_event_id' => $lain->id,
                'recipient_email' => 'terkirim'.Str::random(6)."{$i}@student.jgu.ac.id",
                'emailed_at' => $pada,
            ]);
        }
    }

    private function inFlightBatch(string $nama, int $menunggu, Carbon $dibuat): void
    {
        DB::table('job_batches')->insert([
            'id' => (string) Str::uuid(),
            'name' => $nama,
            'total_jobs' => $menunggu,
            'pending_jobs' => $menunggu,
            'failed_jobs' => 0,
            'failed_job_ids' => '[]',
            'options' => null,
            'created_at' => $dibuat->getTimestamp(),
            'cancelled_at' => null,
            'finished_at' => null,
        ]);
    }

    private function mailer(): CertificateBatchMailer
    {
        return app(CertificateBatchMailer::class);
    }

    // ------------------------------------------------------------ pembagian

    public function test_everything_goes_when_the_quota_is_enough(): void
    {
        config(['msc.certificates.daily_email_limit' => 10]);
        Bus::fake();

        $event = $this->event();
        $this->awaiting($event, 4);

        $hasil = $this->mailer()->dispatchFor($event);

        $this->assertSame(4, $hasil->queued());
        $this->assertSame(0, $hasil->heldBack);
    }

    /**
     * Intinya: yang melebihi kuota tidak ikut dilepas ke Gmail.
     */
    public function test_what_exceeds_the_quota_is_held_back(): void
    {
        config(['msc.certificates.daily_email_limit' => 3]);
        Bus::fake();

        $event = $this->event();
        $this->awaiting($event, 5);

        $hasil = $this->mailer()->dispatchFor($event);

        $this->assertSame(3, $hasil->queued());
        $this->assertSame(2, $hasil->heldBack);
        Bus::assertBatched(fn ($batch) => $batch->jobs->count() === 3);
    }

    /**
     * Yang ditahan tidak diberi tanda apa pun, jadi menekan Kirim Email lagi
     * cukup untuk mengirimnya.
     */
    public function test_held_back_certificates_still_await_their_email(): void
    {
        config(['msc.certificates.daily_email_limit' => 3]);
        Bus::fake();

        $event = $this->event();
        $this->awaiting($event, 5);

        $this->mailer()->dispatchFor($event);

        $this->assertSame(5, $event->certificates()->get()->filter->awaitsEmail()->count());
    }

    // -------------------------------------------------------- penghitungan

    public function test_emails_sent_in_the_last_day_use_up_the_quota(): void
    {
        config(['msc.certificates.daily_email_limit' => 5]);
        Bus::fake();

        $this->alreadySent(3, now()->subHours(2));

        $event = $this->event();
        $this->awaiting($event, 4);

        $hasil = $this->mailer()->dispatchFor($event);

        $this->assertSame(2, $hasil->queued());
        $this->assertSame(2, $hasil->heldBack);
    }

    /**
     * Jendelanya bergulir: yang terkirim lebih dari 24 jam lalu sudah tidak
     * dihitung.
     */
    public function test_emails_older_than_a_day_no_longer_count(): void
    {
        config(['msc.certificates.daily_email_limit' => 5]);

        $this->alreadySent(5, now()->subHours(25));

        $this->assertSame(5, $this->mailer()->remainingToday());
    }

    /**
     * Kiriman yang sudah diantrekan tetapi belum berangkat juga memakan
     * kuota. Tanpa itu, menekan Kirim Email di dua kegiatan berturut-turut
     * melihat kuota yang sama masih utuh.
     */
    public function test_emails_still_in_the_queue_use_up_the_quota(): void
    {
        config(['msc.certificates.daily_email_limit' => 10]);

        $this->inFlightBatch('Pengiriman email sertifikat: Seminar Lain', 4, now()->subMinutes(5));

        $this->assertSame(6, $this->mailer()->remainingToday());
    }

    public function test_unrelated_batches_do_not_count(): void
    {
        config(['msc.certificates.daily_email_limit' => 10]);

        $this->inFlightBatch('Penerbitan sertifikat: Seminar Lain', 4, now()->subMinutes(5));

        $this->assertSame(10, $this->mailer()->remainingToday());
    }

    /**
     * Antrean yang telanjur dikosongkan dengan tangan meninggalkan batch yang
     * tidak pernah selesai. Ia tidak boleh menyumbat kuota selamanya.
     */
    public function test_a_stale_batch_stops_counting_after_a_day(): void
    {
        config(['msc.certificates.daily_email_limit' => 10]);

        $this->inFlightBatch('Pengiriman email sertifikat: Seminar Lama', 4, now()->subDays(2));

        $this->assertSame(10, $this->mailer()->remainingToday());
    }

    // --------------------------------------------------------- kuota habis

    public function test_nothing_is_sent_once_the_quota_is_spent(): void
    {
        config(['msc.certificates.daily_email_limit' => 3]);
        Bus::fake();

        $this->alreadySent(3, now()->subHour());

        $event = $this->event();
        $this->awaiting($event, 2);

        try {
            $this->mailer()->dispatchFor($event);
            $this->fail('Pengiriman tetap dilepas walau kuotanya habis.');
        } catch (CertificateBatchException $exception) {
            $this->assertStringContainsString('Kuota harian', $exception->getMessage());
        }

        Bus::assertNothingBatched();
    }

    /**
     * Admin perlu tahu kapan boleh mencoba lagi, bukan sekadar "nanti".
     * Kuota pertama yang terbuka adalah milik email tertua dalam 24 jam.
     */
    public function test_the_refusal_says_when_the_quota_opens_again(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-08 10:00', 'Asia/Jakarta'));
        config(['msc.certificates.daily_email_limit' => 2]);

        $this->alreadySent(1, now()->subHours(3));
        $this->alreadySent(1, now()->subHour());

        $this->assertStringContainsString('9 Oktober 2026, 07:00', $this->mailer()->quotaFreesAtLabel());

        Carbon::setTestNow();
    }

    /**
     * Penyedia lain punya aturan lain. 0 mematikan penjagaannya.
     */
    public function test_zero_means_no_limit(): void
    {
        config(['msc.certificates.daily_email_limit' => 0]);
        Bus::fake();

        $this->alreadySent(50, now()->subHour());

        $event = $this->event();
        $this->awaiting($event, 3);

        $hasil = $this->mailer()->dispatchFor($event);

        $this->assertNull($this->mailer()->remainingToday());
        $this->assertSame(3, $hasil->queued());
        $this->assertSame(0, $hasil->heldBack);
    }

    // ---------------------------------------------------------------- panel

    private function admin(): User
    {
        $this->seed(RoleSeeder::class);

        $user = User::factory()->create();
        $user->assignRole('admin');

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return $user->fresh();
    }

    private function table(CertificateEvent $event)
    {
        return Livewire::actingAs($this->admin())->test(ParticipationsRelationManager::class, [
            'ownerRecord' => $event,
            'pageClass' => EditCertificateEvent::class,
        ]);
    }

    /**
     * Yang ditahan harus disebut di panel, kalau tidak admin mengira seluruhnya
     * sedang dikirim lalu tidak pernah menekan tombolnya lagi.
     */
    public function test_the_panel_says_some_were_held_back(): void
    {
        config(['msc.certificates.daily_email_limit' => 2]);
        Bus::fake();

        $event = $this->event();
        $this->awaiting($event, 3);

        $this->table($event)
            ->callTableAction('sendPendingEmails')
            ->assertNotified('Sebagian email diantrekan');
    }

    public function test_the_panel_explains_a_spent_quota(): void
    {
        config(['msc.certificates.daily_email_limit' => 1]);
        Bus::fake();

        $this->alreadySent(1, now()->subHour());

        $event = $this->event();
        $this->awaiting($event, 2);

        $this->table($event)
            ->callTableAction('sendPendingEmails')
            ->assertNotified('Pengiriman dibatalkan');

        Bus::assertNothingBatched();
    }
}

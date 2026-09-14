<?php

namespace Tests\Feature;

use App\Filament\Resources\CertificateEventResource\Pages\EditCertificateEvent;
use App\Filament\Resources\CertificateEventResource\RelationManagers\ParticipationsRelationManager;
use App\Models\Certificate;
use App\Models\CertificateEvent;
use App\Models\CertificateEventParticipant;
use App\Models\Participant;
use App\Models\User;
use App\Notifications\CertificateIssued;
use App\Support\CertificateStage;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Perjalanan sertifikat dari ujung ke ujung.
 *
 * Tiap bagiannya sudah punya test sendiri, tetapi yang sering putus justru
 * sambungannya: admin menekan tombol, lalu peserta membuka tautan. Test ini
 * menelusuri jalur itu seperti keduanya melakukannya sungguhan — lewat panel
 * dan lewat HTTP — tanpa satu pun pekerja antrean berjalan.
 */
class CertificateJourneyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Desain latar template disiapkan sungguhan, supaya unduhan PDF
        // menempuh jalur yang sama seperti di produksi.
        Storage::fake('public');
        Storage::disk('public')->put('certificates/templates/dummy.png', $this->pngBytes());
    }

    /** PNG 1×1 paling ringkas yang masih sah. */
    private function pngBytes(): string
    {
        return base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg=='
        );
    }

    private function admin(): User
    {
        $this->seed(RoleSeeder::class);

        $user = User::factory()->create();
        $user->assignRole('admin');

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return $user->fresh();
    }

    private function participation(CertificateEvent $event): CertificateEventParticipant
    {
        return CertificateEventParticipant::factory()->eligible()->create([
            'certificate_event_id' => $event->id,
            'participant_id' => Participant::factory(),
        ]);
    }

    private function panel(CertificateEvent $event): Testable
    {
        return Livewire::test(ParticipationsRelationManager::class, [
            'ownerRecord' => $event->fresh(),
            'pageClass' => EditCertificateEvent::class,
        ]);
    }

    /**
     * Satu kegiatan, dua peserta: diterbitkan, dibuka publik, lalu dikirim —
     * masing-masing tahap dilakukan dari tempat yang sebenarnya.
     */
    public function test_the_whole_journey_runs_without_a_queue_worker(): void
    {
        Notification::fake();

        $this->actingAs($this->admin());

        $event = CertificateEvent::factory()->published()->create();
        $satu = $this->participation($event);
        $dua = $this->participation($event);

        // Sebelum diterbitkan, keduanya menunggu.
        $this->assertSame(CertificateStage::READY, CertificateStage::for($satu));

        // --- tahap 1: admin menerbitkan dari panel
        $this->panel($event)->callTableAction('issueAllEligible')->assertHasNoTableActionErrors();

        $this->assertSame(2, Certificate::where('certificate_event_id', $event->id)->count());
        $this->assertSame(CertificateStage::ISSUED, CertificateStage::for($satu->fresh(['certificate'])));

        // --- peserta langsung bisa memverifikasi dan mengunduh
        foreach (Certificate::all() as $certificate) {
            $this->get($certificate->verificationUrl())
                ->assertOk()
                ->assertSee($certificate->certificate_number);

            $unduh = $this->get($certificate->downloadUrl())->assertOk();

            $this->assertSame('application/pdf', $unduh->headers->get('Content-Type'));
        }

        // --- tahap 2: admin mengirim emailnya
        $this->panel($event)->callTableAction('sendPendingEmails')->assertHasNoTableActionErrors();

        // Batch antrean dijalankan seperti pekerja akan menjalankannya.
        $this->runQueuedJobs();

        Notification::assertSentTimes(CertificateIssued::class, 2);
        $this->assertSame(CertificateStage::SENT, CertificateStage::for($satu->fresh(['certificate'])));
    }

    /**
     * Nomor yang diberikan harus berurutan dan tidak pernah kembar, termasuk
     * ketika beberapa peserta diterbitkan sekaligus.
     */
    public function test_every_certificate_gets_its_own_number(): void
    {
        $this->actingAs($this->admin());

        $event = CertificateEvent::factory()->published()->create();

        for ($i = 0; $i < 5; $i++) {
            $this->participation($event);
        }

        $this->panel($event)->callTableAction('issueAllEligible');

        $nomor = Certificate::pluck('certificate_number');

        $this->assertCount(5, $nomor);
        $this->assertCount(5, $nomor->unique());
    }

    /**
     * Menekan tombol dua kali tidak boleh menghasilkan sertifikat kedua.
     */
    public function test_issuing_twice_changes_nothing(): void
    {
        $this->actingAs($this->admin());

        $event = CertificateEvent::factory()->published()->create();
        $this->participation($event);

        $this->panel($event)->callTableAction('issueAllEligible');
        $pertama = Certificate::sole()->certificate_number;

        // Tidak ada lagi yang bisa diterbitkan; aksinya menolak dengan sopan.
        $this->panel($event)->callTableAction('issueAllEligible')->assertHasNoTableActionErrors();

        $this->assertSame(1, Certificate::count());
        $this->assertSame($pertama, Certificate::sole()->certificate_number);
    }

    /**
     * Biaya halaman panel tidak boleh ikut tumbuh bersama jumlah peserta.
     */
    public function test_the_panel_table_does_not_query_once_per_participant(): void
    {
        $this->actingAs($this->admin());

        $event = CertificateEvent::factory()->published()->create();

        $hitung = function () use ($event): int {
            $n = 0;
            DB::listen(function () use (&$n): void {
                $n++;
            });

            $this->panel($event);

            return $n;
        };

        for ($i = 0; $i < 3; $i++) {
            $this->participation($event);
        }
        $sedikit = $hitung();

        for ($i = 0; $i < 15; $i++) {
            $this->participation($event);
        }

        $this->assertLessThanOrEqual($sedikit, $hitung(),
            'Jumlah kueri ikut bertambah seiring jumlah peserta.');
    }

    /**
     * Nomor resmi kampus memuat garis miring — "0001/CERT/MSC-JGU/IX/2026" —
     * dan header Content-Disposition menolaknya, sehingga setiap unduhan
     * berujung galat sampai nomornya diratakan.
     */
    public function test_an_official_number_with_slashes_can_still_be_downloaded(): void
    {
        $this->actingAs($this->admin());

        $event = CertificateEvent::factory()->published()->create();
        $this->participation($event);

        $this->panel($event)->callTableAction('issueAllEligible');

        $certificate = Certificate::sole();
        $certificate->forceFill(['certificate_number' => '0001/CERT/MSC-JGU/IX/2026'])->save();

        $unduh = $this->get($certificate->downloadUrl())->assertOk();

        $this->assertSame('application/pdf', $unduh->headers->get('Content-Type'));
        $this->assertStringContainsString(
            'Sertifikat-0001-CERT-MSC-JGU-IX-2026.pdf',
            (string) $unduh->headers->get('Content-Disposition'),
        );
    }

    /**
     * Desain latarnya hiasan, bukan isinya. Berkas yang hilang — misalnya
     * karena storage tidak ikut terbawa saat rilis — tidak boleh membuat
     * setiap unduhan sertifikat gagal.
     */
    public function test_a_missing_background_still_produces_a_certificate(): void
    {
        $this->actingAs($this->admin());

        $event = CertificateEvent::factory()->published()->create();
        $this->participation($event);

        $this->panel($event)->callTableAction('issueAllEligible');

        Storage::disk('public')->delete('certificates/templates/dummy.png');

        $unduh = $this->get(Certificate::sole()->downloadUrl())->assertOk();

        $this->assertSame('application/pdf', $unduh->headers->get('Content-Type'));
    }

    /**
     * Jalankan batch antrean seperti `queue:work` akan menjalankannya.
     */
    private function runQueuedJobs(): void
    {
        $this->artisan('queue:work', ['--stop-when-empty' => true, '--tries' => 1]);
    }
}

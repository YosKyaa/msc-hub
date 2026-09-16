<?php

namespace Tests\Feature;

use App\Filament\Resources\CertificateResource;
use App\Filament\Resources\CertificateResource\Pages\ListCertificates;
use App\Models\Certificate;
use App\Models\CertificateEvent;
use App\Models\CertificateEventParticipant;
use App\Models\Participant;
use App\Models\User;
use App\Services\Certificates\CertificateIssuer;
use App\Support\CertificateStage;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Daftar sertifikat lintas kegiatan.
 *
 * Sertifikat dulu hanya terlihat dari dalam kegiatannya masing-masing, jadi
 * ketika seseorang menghubungi dan mengaku belum menerima emailnya, staf
 * harus menebak dulu kegiatan mana yang dimaksud. Halaman ini hanya untuk
 * melihat dan menelusuri — tidak ada satu pun yang bisa diubah dari sini.
 */
class CertificateLookupTest extends TestCase
{
    use RefreshDatabase;

    private function staff(): User
    {
        $this->seed(RoleSeeder::class);

        $user = User::factory()->create();
        $user->assignRole('admin');

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return $user->fresh();
    }

    private function issue(string $nama, string $email, ?CertificateEvent $event = null): Certificate
    {
        $event ??= CertificateEvent::factory()->published()->create();

        $participation = CertificateEventParticipant::factory()->eligible()->create([
            'certificate_event_id' => $event->id,
            'participant_id' => Participant::factory()->create(['name' => $nama, 'email' => $email]),
        ]);

        return app(CertificateIssuer::class)->issue($participation);
    }

    private function page(): Testable
    {
        return Livewire::test(ListCertificates::class);
    }

    // ----------------------------------------------------------- mencari

    /**
     * Inti gunanya: menemukan sertifikat seseorang tanpa tahu lebih dulu
     * kegiatan mana yang dimaksud.
     */
    public function test_certificates_from_every_event_appear_in_one_list(): void
    {
        $this->actingAs($this->staff());

        $satu = $this->issue('Budi Santoso', 'budi@student.jgu.ac.id');
        $dua = $this->issue('Siti Nurhaliza', 'siti@jgu.ac.id');

        $this->page()->assertSuccessful()->assertCanSeeTableRecords([$satu, $dua]);
    }

    public function test_it_can_be_searched_by_name_email_or_number(): void
    {
        $this->actingAs($this->staff());

        $budi = $this->issue('Budi Santoso', 'budi@student.jgu.ac.id');
        $siti = $this->issue('Siti Nurhaliza', 'siti@jgu.ac.id');

        $this->page()->searchTable('Budi')->assertCanSeeTableRecords([$budi])->assertCanNotSeeTableRecords([$siti]);
        $this->page()->searchTable('siti@jgu.ac.id')->assertCanSeeTableRecords([$siti])->assertCanNotSeeTableRecords([$budi]);
        $this->page()->searchTable($budi->certificate_number)->assertCanSeeTableRecords([$budi])->assertCanNotSeeTableRecords([$siti]);
    }

    /**
     * Justru inilah yang dicari ketika emailnya tidak sampai: menyaring
     * siapa saja yang sertifikatnya belum terkirim.
     */
    public function test_it_can_single_out_the_ones_that_never_reached_anyone(): void
    {
        $this->actingAs($this->staff());

        $belum = $this->issue('Budi Santoso', 'budi@student.jgu.ac.id');
        $sudah = $this->issue('Siti Nurhaliza', 'siti@jgu.ac.id');
        $sudah->forceFill(['emailed_at' => now()])->save();

        $this->page()
            ->filterTable('stage', CertificateStage::ISSUED->value)
            ->assertCanSeeTableRecords([$belum])
            ->assertCanNotSeeTableRecords([$sudah]);

        $this->page()
            ->filterTable('stage', CertificateStage::SENT->value)
            ->assertCanSeeTableRecords([$sudah])
            ->assertCanNotSeeTableRecords([$belum]);
    }

    /**
     * Tahap yang hanya milik peserta tanpa sertifikat tidak boleh
     * menghasilkan baris apa pun di tabel ini.
     */
    public function test_stages_that_precede_a_certificate_match_nothing_here(): void
    {
        $this->actingAs($this->staff());

        $terbit = $this->issue('Budi Santoso', 'budi@student.jgu.ac.id');

        foreach ([CertificateStage::NOT_ELIGIBLE, CertificateStage::READY] as $stage) {
            $this->page()
                ->filterTable('stage', $stage->value)
                ->assertCanNotSeeTableRecords([$terbit]);
        }
    }

    /**
     * Tautan verifikasinya disalin lalu dikirim lewat jalur lain — itulah
     * jalan keluar ketika emailnya tidak kunjung sampai.
     */
    public function test_the_verification_link_is_there_to_be_copied(): void
    {
        $this->actingAs($this->staff());

        $certificate = $this->issue('Budi Santoso', 'budi@student.jgu.ac.id');

        $this->get(CertificateResource::getUrl())
            ->assertOk()
            ->assertSee($certificate->verificationUrl(), false);
    }

    // ------------------------------------------------------ hanya melihat

    /**
     * Yang diminta: halaman ini view saja. Penerbitan dan pengiriman ulang
     * tetap dikerjakan dari halaman kegiatannya.
     */
    public function test_nothing_here_can_change_a_certificate(): void
    {
        $this->actingAs($this->staff());
        $certificate = $this->issue('Budi Santoso', 'budi@student.jgu.ac.id');

        $this->assertFalse(CertificateResource::canCreate());
        $this->assertFalse(CertificateResource::canEdit($certificate));
        $this->assertFalse(CertificateResource::canDelete($certificate));

        $this->page()
            ->assertSuccessful()
            ->assertTableActionDoesNotExist('delete')
            ->assertTableActionDoesNotExist('edit')
            ->assertTableActionDoesNotExist('revoke')
            ->assertTableActionDoesNotExist('sendEmail');
    }

    public function test_only_the_index_page_exists(): void
    {
        $this->assertSame(['index'], array_keys(CertificateResource::getPages()));
    }

    public function test_it_says_plainly_that_it_is_read_only(): void
    {
        $this->actingAs($this->staff());

        $this->get(CertificateResource::getUrl())
            ->assertOk()
            ->assertSee('hanya untuk melihat');
    }

    public function test_someone_without_the_permission_cannot_open_it(): void
    {
        $this->seed(RoleSeeder::class);

        $tanpaIzin = User::factory()->create();
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->actingAs($tanpaIzin->fresh());

        $this->assertFalse(CertificateResource::canAccess());
    }

    /**
     * Biaya halamannya tidak boleh tumbuh bersama jumlah sertifikat.
     */
    public function test_the_list_does_not_query_once_per_certificate(): void
    {
        $this->actingAs($this->staff());
        $event = CertificateEvent::factory()->published()->create();

        $hitung = function (): int {
            $n = 0;
            \Illuminate\Support\Facades\DB::listen(function () use (&$n): void {
                $n++;
            });

            $this->page()->assertSuccessful();

            return $n;
        };

        for ($i = 0; $i < 3; $i++) {
            $this->issue('Orang '.$i, "orang{$i}@jgu.ac.id", $event);
        }
        $sedikit = $hitung();

        for ($i = 3; $i < 15; $i++) {
            $this->issue('Orang '.$i, "orang{$i}@jgu.ac.id", $event);
        }

        $this->assertLessThanOrEqual($sedikit, $hitung(),
            'Jumlah kueri ikut bertambah seiring banyaknya sertifikat.');
    }
}

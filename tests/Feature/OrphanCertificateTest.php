<?php

namespace Tests\Feature;

use App\Filament\Resources\CertificateResource\Pages\ListCertificates;
use App\Models\Certificate;
use App\Models\CertificateEvent;
use App\Models\CertificateEventParticipant;
use App\Models\Participant;
use App\Models\User;
use App\Services\Certificates\CertificateIssuer;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Sertifikat yang keikutsertaannya sudah dihapus.
 *
 * Kunci asingnya nullOnDelete, jadi sertifikatnya tetap ada dan tetap sah di
 * halaman verifikasi, tetapi tidak lagi muncul di tabel kegiatan mana pun.
 * Ringkasan di atas tabel kegiatan menyebut jumlahnya, tanpa ada tempat untuk
 * mengurusnya. Halaman Cari Sertifikat kini menampilkan dan mencabutnya.
 */
class OrphanCertificateTest extends TestCase
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

    private function issue(string $nama): Certificate
    {
        $participation = CertificateEventParticipant::factory()->eligible()->create([
            'certificate_event_id' => CertificateEvent::factory()->published()->create()->id,
            'participant_id' => Participant::factory()->create(['name' => $nama]),
        ]);

        return app(CertificateIssuer::class)->issue($participation);
    }

    private function orphan(string $nama): Certificate
    {
        $certificate = $this->issue($nama);
        $certificate->participation->delete();

        return $certificate->fresh();
    }

    public function test_the_filter_shows_only_certificates_without_a_participant(): void
    {
        $this->actingAs($this->admin());

        $yatim = $this->orphan('Citra Lestari');
        $biasa = $this->issue('Bagas Pratama');

        Livewire::test(ListCertificates::class)
            ->filterTable('orphaned')
            ->assertCanSeeTableRecords([$yatim])
            ->assertCanNotSeeTableRecords([$biasa]);
    }

    /**
     * Tanpa aksi ini sertifikatnya tetap sah selamanya: tidak ada tempat
     * lain di panel yang menampilkannya.
     */
    public function test_an_orphaned_certificate_can_be_revoked_and_restored(): void
    {
        $this->actingAs($this->admin());
        $yatim = $this->orphan('Citra Lestari');

        Livewire::test(ListCertificates::class)
            ->callTableAction('revokeOrphan', $yatim, ['reason' => 'Peserta sudah dihapus dari kegiatan.'])
            ->assertHasNoTableActionErrors();

        $this->assertNotNull($yatim->fresh()->revoked_at);
        $this->assertFalse($yatim->fresh()->isValid());
        $this->assertSame('Peserta sudah dihapus dari kegiatan.', $yatim->fresh()->revocation_reason);

        Livewire::test(ListCertificates::class)->callTableAction('restoreOrphan', $yatim);

        $this->assertTrue($yatim->fresh()->isValid());
    }

    public function test_revoking_requires_a_reason(): void
    {
        $this->actingAs($this->admin());
        $yatim = $this->orphan('Citra Lestari');

        Livewire::test(ListCertificates::class)
            ->callTableAction('revokeOrphan', $yatim, ['reason' => ''])
            ->assertHasTableActionErrors(['reason' => 'required']);

        $this->assertNull($yatim->fresh()->revoked_at);
    }

    /**
     * Sertifikat yang masih punya pemilik tetap diurus dari halaman
     * kegiatannya, supaya keputusan tentang satu orang tidak terbagi di dua
     * tempat.
     */
    public function test_a_certificate_that_still_has_its_participant_is_not_revoked_here(): void
    {
        $this->actingAs($this->admin());
        $biasa = $this->issue('Bagas Pratama');

        Livewire::test(ListCertificates::class)
            ->assertTableActionHidden('revokeOrphan', $biasa)
            ->assertTableActionHidden('restoreOrphan', $biasa);
    }

    /**
     * Melihat daftar sertifikat tidak sama dengan berhak mencabutnya.
     */
    public function test_someone_who_may_only_look_cannot_revoke(): void
    {
        $this->seed(RoleSeeder::class);

        $peran = Role::create(['name' => 'pengamat', 'guard_name' => 'web']);
        $peran->givePermissionTo(Permission::whereIn('name', ['panel.access', 'certificates.view'])->get());

        $pengamat = User::factory()->create();
        $pengamat->assignRole($peran);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->actingAs($pengamat->fresh());
        $yatim = $this->orphan('Citra Lestari');

        Livewire::test(ListCertificates::class)->assertTableActionHidden('revokeOrphan', $yatim);
    }
}

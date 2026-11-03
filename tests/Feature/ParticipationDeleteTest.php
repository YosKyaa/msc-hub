<?php

namespace Tests\Feature;

use App\Filament\Resources\CertificateEventResource\Pages\EditCertificateEvent;
use App\Filament\Resources\CertificateEventResource\RelationManagers\ParticipationsRelationManager;
use App\Models\Certificate;
use App\Models\CertificateEvent;
use App\Models\CertificateEventParticipant;
use App\Models\Participant;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Filament\Notifications\Livewire\Notifications;
use Filament\Notifications\Notification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Menghapus peserta dari tabel peserta sebuah kegiatan.
 *
 * Tabel ini tidak punya policy, jadi tombol Hapus bawaannya dulu terbuka bagi
 * siapa pun yang bisa membuka kegiatannya, termasuk staf MSC yang sengaja
 * tidak diberi certificates.delete. Hapus massalnya juga menghapus pemegang
 * sertifikat, padahal hapus per baris melewatinya: sertifikatnya tetap sah,
 * tetapi lepas dari kegiatannya, sehingga tidak bisa lagi dikirim ulang,
 * dicabut, atau dipulihkan dari tabel itu.
 */
class ParticipationDeleteTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $role): User
    {
        $this->seed(RoleSeeder::class);

        $user = User::factory()->create();
        $user->assignRole($role);

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return $user->fresh();
    }

    private function table(User $user, CertificateEvent $event): Testable
    {
        return Livewire::actingAs($user)->test(ParticipationsRelationManager::class, [
            'ownerRecord' => $event,
            'pageClass' => EditCertificateEvent::class,
        ]);
    }

    private function participation(CertificateEvent $event, string $nama = 'Budi Santoso'): CertificateEventParticipant
    {
        return CertificateEventParticipant::factory()->create([
            'certificate_event_id' => $event->id,
            'participant_id' => Participant::factory()->create(['name' => $nama])->id,
        ]);
    }

    private function holdingCertificate(CertificateEvent $event, string $nama, array $sertifikat = []): CertificateEventParticipant
    {
        $keikutsertaan = $this->participation($event, $nama);

        Certificate::factory()->create([
            'certificate_event_id' => $event->id,
            'participant_id' => $keikutsertaan->participant_id,
            'event_participant_id' => $keikutsertaan->id,
            'recipient_name' => $nama,
            ...$sertifikat,
        ]);

        return $keikutsertaan;
    }

    /**
     * Pemberitahuan yang terkirim, sebagaimana dibaca admin.
     */
    private function notificationText(): string
    {
        $komponen = new Notifications;
        $komponen->mount();

        return $komponen->notifications
            ->map(fn (Notification $pemberitahuan) => $pemberitahuan->getTitle().' '.$pemberitahuan->getBody())
            ->implode(' | ');
    }

    /**
     * Tekan Hapus pada sebuah baris lewat permintaan Livewire mentah, tanpa
     * pemeriksaan tampilan yang dilakukan pembantu test Filament.
     */
    private function pressDelete(Testable $tabel, CertificateEventParticipant $record): void
    {
        $tabel->call('mountAction', 'delete', [], ['table' => true, 'recordKey' => (string) $record->getKey()])
            ->call('callMountedAction');
    }

    /**
     * @param  list<CertificateEventParticipant>  $records
     */
    private function pressBulkDelete(Testable $tabel, array $records): void
    {
        $tabel->set('selectedTableRecords', array_map(fn (CertificateEventParticipant $r) => (string) $r->getKey(), $records))
            ->call('mountAction', 'delete', [], ['table' => true, 'bulk' => true])
            ->call('callMountedAction');
    }

    // ------------------------------------------------------------- izin

    /**
     * staff_msc boleh menambah dan mengubah peserta kegiatan, tetapi tidak
     * memegang certificates.delete.
     */
    public function test_staff_without_the_delete_permission_cannot_remove_participants(): void
    {
        $staf = $this->user('staff_msc');
        $this->assertFalse($staf->can('certificates.delete'), 'Prasyarat: staff_msc tidak diberi izin menghapus.');

        $event = CertificateEvent::factory()->create();
        $peserta = $this->participation($event);

        $tabel = $this->table($staf, $event)
            ->assertTableActionHidden('delete', $peserta)
            ->assertTableBulkActionHidden('delete');

        // Tombol yang disembunyikan belum tentu tertutup. Permintaannya
        // dikirim langsung, seolah tombolnya tetap ditekan.
        $this->pressDelete($tabel, $peserta);
        $this->pressBulkDelete($tabel, [$peserta]);
        $this->assertModelExists($peserta);

        // Permintaan yang sama persis dari pemegang izin memang menghapus,
        // jadi penolakan di atas bukan karena permintaannya keliru.
        $kepala = $this->user('head_msc');
        $this->assertTrue($kepala->can('certificates.delete'));

        $this->pressDelete($this->table($kepala, $event)->assertTableActionVisible('delete', $peserta), $peserta);
        $this->assertModelMissing($peserta);
    }

    // ------------------------------------------------- pemegang sertifikat

    public function test_a_bulk_delete_skips_certificate_holders_and_names_them(): void
    {
        $event = CertificateEvent::factory()->create();
        $bebas = [$this->participation($event, 'Siti Rahma'), $this->participation($event, 'Andi Wijaya')];
        $pemegang = $this->holdingCertificate($event, 'Yosua Immanuel');

        $this->pressBulkDelete($this->table($this->user('head_msc'), $event), [...$bebas, $pemegang]);

        foreach ($bebas as $peserta) {
            $this->assertModelMissing($peserta);
        }

        $this->assertModelExists($pemegang);
        $this->assertSame($pemegang->id, Certificate::sole()->event_participant_id, 'Sertifikatnya lepas dari kegiatannya.');

        $pesan = $this->notificationText();
        $this->assertStringContainsString('1 peserta dilewati karena sudah memegang sertifikat: Yosua Immanuel.', $pesan);
        $this->assertStringContainsString('Cabut sertifikatnya bila dokumennya memang harus dibatalkan.', $pesan);
    }

    /**
     * Sertifikat yang dicabut tetap tampil di halaman verifikasi sebagai
     * dicabut, dan hanya bisa dipulihkan dari tabel ini.
     */
    public function test_a_revoked_certificate_still_counts(): void
    {
        $event = CertificateEvent::factory()->create();
        $pemegang = $this->holdingCertificate($event, 'Chika Arzika', ['revoked_at' => now(), 'revocation_reason' => 'Uji']);

        $tabel = $this->table($this->user('head_msc'), $event)->assertTableActionHidden('delete', $pemegang);

        $this->pressBulkDelete($tabel, [$pemegang]);
        $this->assertModelExists($pemegang);
    }
}

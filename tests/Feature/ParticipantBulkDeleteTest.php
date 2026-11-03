<?php

namespace Tests\Feature;

use App\Filament\Resources\ParticipantResource\Pages\EditParticipant;
use App\Filament\Resources\ParticipantResource\Pages\ListParticipants;
use App\Models\Certificate;
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
 * Menghapus banyak peserta sekaligus dari Master Participant.
 *
 * Menghapus peserta ikut menghapus keikutsertaannya di setiap kegiatan,
 * sedangkan sertifikatnya tetap ada tanpa pemilik. Karena itu pemegang
 * sertifikat dilewati, dengan aturan yang sama untuk hapus per baris, hapus
 * massal, dan tombol Hapus di halaman ubah.
 */
class ParticipantBulkDeleteTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $role = 'admin'): User
    {
        $this->seed(RoleSeeder::class);

        $user = User::factory()->create();
        $user->assignRole($role);

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return $user->fresh();
    }

    private function list(?User $user = null): Testable
    {
        return Livewire::actingAs($user ?? $this->user())->test(ListParticipants::class);
    }

    private function holdingCertificate(string $nama, array $sertifikat = []): Participant
    {
        $peserta = Participant::factory()->create(['name' => $nama]);
        Certificate::factory()->create(['participant_id' => $peserta->id, 'recipient_name' => $nama, ...$sertifikat]);

        return $peserta;
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

    // ------------------------------------------------------------ massal

    /**
     * Yang diminta: pilih banyak, lalu hapus sekaligus.
     */
    public function test_participants_can_be_selected_and_deleted_together(): void
    {
        $dipilih = Participant::factory()->count(3)->create();
        $tetap = Participant::factory()->create();

        $this->list()->callTableBulkAction('delete', $dipilih);

        foreach ($dipilih as $peserta) {
            $this->assertModelMissing($peserta);
        }

        $this->assertModelExists($tetap);
    }

    public function test_participants_holding_a_certificate_are_skipped_and_named(): void
    {
        $bebas = Participant::factory()->count(2)->create();
        $pemegang = $this->holdingCertificate('Yosua Immanuel');

        $this->list()->callTableBulkAction('delete', [...$bebas, $pemegang]);

        foreach ($bebas as $peserta) {
            $this->assertModelMissing($peserta);
        }

        $this->assertModelExists($pemegang);
        $this->assertSame($pemegang->id, Certificate::sole()->participant_id, 'Sertifikatnya kehilangan pemilik.');

        $pesan = $this->notificationText();
        $this->assertStringContainsString('1 peserta dilewati karena sudah memegang sertifikat: Yosua Immanuel.', $pesan);
        $this->assertStringContainsString('Cabut sertifikatnya bila dokumennya memang harus dibatalkan.', $pesan);
    }

    /**
     * Sertifikat yang dicabut tetap tampil di halaman verifikasi sebagai
     * dicabut, dan bisa dipulihkan dari tabel peserta kegiatannya.
     */
    public function test_a_revoked_certificate_still_counts(): void
    {
        $pemegang = $this->holdingCertificate('Chika Arzika', ['revoked_at' => now(), 'revocation_reason' => 'Uji']);

        $this->list()->callTableBulkAction('delete', [$pemegang]);

        $this->assertModelExists($pemegang);
    }

    public function test_a_long_list_of_skipped_names_is_shortened(): void
    {
        $pemegang = collect(range(1, 7))->map(fn (int $i) => $this->holdingCertificate("Pemegang {$i}"));

        $this->list()->callTableBulkAction('delete', $pemegang);

        $this->assertStringContainsString(
            '7 peserta dilewati karena sudah memegang sertifikat: Pemegang 1, Pemegang 2, Pemegang 3, Pemegang 4, Pemegang 5, dan 2 lainnya.',
            $this->notificationText(),
        );
    }

    /**
     * Akibat yang disebut di jendela konfirmasinya memang terjadi.
     */
    public function test_their_event_registrations_go_with_them(): void
    {
        $peserta = Participant::factory()->create();
        $keikutsertaan = CertificateEventParticipant::factory()->create(['participant_id' => $peserta->id]);

        $this->list()->callTableBulkAction('delete', [$peserta]);

        $this->assertModelMissing($peserta);
        $this->assertModelMissing($keikutsertaan);
    }

    // ------------------------------------------------------- satu-satu

    public function test_the_row_delete_button_is_hidden_for_a_certificate_holder(): void
    {
        $bebas = Participant::factory()->create();
        $pemegang = $this->holdingCertificate('Yosua Immanuel');

        $this->list()
            ->assertTableActionVisible('delete', $bebas)
            ->assertTableActionHidden('delete', $pemegang);
    }

    public function test_the_edit_page_hides_delete_for_a_certificate_holder(): void
    {
        $admin = $this->user();
        $bebas = Participant::factory()->create();
        $pemegang = $this->holdingCertificate('Yosua Immanuel');

        Livewire::actingAs($admin)
            ->test(EditParticipant::class, ['record' => $bebas->getRouteKey()])
            ->assertActionVisible('delete');

        Livewire::actingAs($admin)
            ->test(EditParticipant::class, ['record' => $pemegang->getRouteKey()])
            ->assertActionHidden('delete');
    }

    /**
     * Kolom inilah yang menjelaskan kenapa tombol Hapus di sebuah baris
     * tidak ada.
     */
    public function test_the_table_shows_how_many_certificates_each_holds(): void
    {
        $pemegang = $this->holdingCertificate('Yosua Immanuel');

        $this->list()->assertTableColumnStateSet('certificates_count', 1, $pemegang);
    }

    // ------------------------------------------------------------- izin

    /**
     * Staf MSC boleh menambah dan mengubah peserta, tetapi tidak memegang
     * certificates.delete. Tombol hapus, per baris maupun massal, tertutup
     * baginya — di server, bukan sekadar disembunyikan.
     */
    public function test_staff_without_the_delete_permission_cannot_delete(): void
    {
        $staf = $this->user('staff_msc');
        $this->assertFalse($staf->can('certificates.delete'), 'Prasyarat: staff_msc tidak diberi izin menghapus.');

        $peserta = Participant::factory()->create();

        $tabel = $this->list($staf)
            ->assertTableActionHidden('delete', $peserta)
            ->assertTableBulkActionHidden('delete');

        // Tombol yang disembunyikan belum tentu tertutup. Permintaannya
        // dikirim langsung, seolah tombolnya tetap ditekan.
        $this->pressBulkDelete($tabel, [$peserta]);
        $this->assertModelExists($peserta);

        Livewire::actingAs($staf)
            ->test(EditParticipant::class, ['record' => $peserta->getRouteKey()])
            ->assertActionHidden('delete');

        // Permintaan yang sama persis dari admin memang menghapus, jadi
        // penolakan di atas bukan karena permintaannya keliru.
        $this->pressBulkDelete($this->list(), [$peserta]);
        $this->assertModelMissing($peserta);
    }

    /**
     * Tekan hapus massal lewat permintaan Livewire mentah, tanpa pemeriksaan
     * tampilan yang dilakukan pembantu test Filament.
     *
     * @param  array<int, Participant>  $peserta
     */
    private function pressBulkDelete(Testable $tabel, array $peserta): void
    {
        $tabel->set('selectedTableRecords', array_map(fn (Participant $p) => (string) $p->getKey(), $peserta))
            ->call('mountAction', 'delete', [], ['table' => true, 'bulk' => true])
            ->call('callMountedAction');
    }
}

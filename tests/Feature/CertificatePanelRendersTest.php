<?php

namespace Tests\Feature;

use App\Enums\AttendanceAction;
use App\Filament\Resources\CertificateEventResource;
use App\Filament\Resources\IssuerResource;
use App\Filament\Resources\ParticipantResource;
use App\Models\CertificateEvent;
use App\Models\CertificateEventParticipant;
use App\Models\Participant;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Asap: memastikan schema Filament modul sertifikat benar-benar terbentuk.
 * Kesalahan penulisan komponen baru hanya muncul saat halaman dirender.
 */
class CertificatePanelRendersTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Panel merender seluruh navigasi, sehingga set permission lengkap dari
     * RoleSeeder dipakai apa adanya.
     */
    private function admin(): User
    {
        $this->seed(RoleSeeder::class);

        $user = User::factory()->create();
        $user->assignRole('admin');

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return $user->fresh();
    }

    public function test_the_event_list_and_edit_pages_render(): void
    {
        $event = CertificateEvent::factory()->withOpenAttendance()->create();
        CertificateEventParticipant::factory()->eligible()->create([
            'certificate_event_id' => $event->id,
            'participant_id' => Participant::factory(),
        ]);

        $this->actingAs($this->admin());

        $this->get(CertificateEventResource::getUrl('index'))->assertOk();
        $this->get(CertificateEventResource::getUrl('edit', ['record' => $event]))
            ->assertOk()
            ->assertSee('Absensi & QR')
            ->assertSee('Aturan kelayakan sertifikat')
            // Kedua kartu window harus tampil berdampingan.
            ->assertSee('Dipindai saat peserta tiba di lokasi.')
            ->assertSee('Dipindai saat acara selesai. Buka menjelang acara bubar.')
            ->assertSee($event->attendanceUrl(AttendanceAction::CHECK_IN))
            ->assertSee($event->attendanceUrl(AttendanceAction::CHECK_OUT));
    }

    public function test_the_issuer_list_and_form_render(): void
    {
        $this->actingAs($this->admin());

        $this->get(IssuerResource::getUrl('index'))
            ->assertOk()
            ->assertSee('MSC-JGU');

        $this->get(IssuerResource::getUrl('create'))
            ->assertOk()
            ->assertSee('Kode penerbit')
            ->assertSee('Pola nomor penerbit ini');
    }

    public function test_the_participant_master_list_renders(): void
    {
        Participant::factory()->count(3)->create();

        $this->actingAs($this->admin())
            ->get(ParticipantResource::getUrl('index'))
            ->assertOk();
    }
}

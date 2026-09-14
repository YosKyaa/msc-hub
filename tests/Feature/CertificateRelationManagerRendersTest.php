<?php

namespace Tests\Feature;

use App\Filament\Resources\CertificateEventResource\Pages\EditCertificateEvent;
use App\Filament\Resources\CertificateEventResource\RelationManagers\CertificatesRelationManager;
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
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Aksi baris memakai tombol ikon inline, bukan dropdown.
 *
 * Panel ActionGroup Filament dirender dengan `x-float.placement.bottom-start`
 * tanpa modifier `.flip`, sehingga selalu membuka ke bawah dan terpotong tepi
 * layar pada baris terakhir tabel. Test ini menjaga agar tabel tidak kembali
 * memakai dropdown dan setiap aksi tetap terdaftar.
 */
class CertificateRelationManagerRendersTest extends TestCase
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
        $event = CertificateEvent::factory()->published()->withOpenAttendance()->create();

        CertificateEventParticipant::factory()->eligible()->create([
            'certificate_event_id' => $event->id,
            'participant_id' => Participant::factory(),
        ]);

        return $event;
    }

    public function test_the_participant_table_renders_its_row_actions_as_icon_buttons(): void
    {
        $this->actingAs($this->admin());
        $event = $this->event();

        Livewire::test(ParticipationsRelationManager::class, [
            'ownerRecord' => $event,
            'pageClass' => EditCertificateEvent::class,
        ])
            ->assertSuccessful()
            ->assertTableActionExists('toggleEligible')
            ->assertTableActionExists('correctName')
            ->assertTableActionExists('edit')
            ->assertTableActionExists('delete')
            ->assertTableActionHasIcon('correctName', 'heroicon-o-pencil-square');
    }

    public function test_the_certificate_table_renders_its_row_actions_as_icon_buttons(): void
    {
        $this->actingAs($this->admin());
        $event = $this->event();

        Certificate::factory()->create([
            'certificate_event_id' => $event->id,
            'recipient_email' => 'budi@student.jgu.ac.id',
        ]);

        Livewire::test(CertificatesRelationManager::class, [
            'ownerRecord' => $event,
            'pageClass' => EditCertificateEvent::class,
        ])
            ->assertSuccessful()
            ->assertTableActionExists('verify')
            ->assertTableActionExists('download')
            ->assertTableActionExists('resendEmail')
            ->assertTableActionExists('revoke');
    }

    public function test_the_eligible_toggle_actually_flips_the_flag(): void
    {
        $this->actingAs($this->admin());
        $event = $this->event();
        $participation = $event->participations()->sole();

        Livewire::test(ParticipationsRelationManager::class, [
            'ownerRecord' => $event,
            'pageClass' => EditCertificateEvent::class,
        ])->callTableAction('toggleEligible', $participation);

        $this->assertNull($participation->fresh()->eligible_at);
    }

    public function test_correcting_the_name_updates_the_master_participant(): void
    {
        $this->actingAs($this->admin());
        $event = $this->event();
        $participation = $event->participations()->sole();

        Livewire::test(ParticipationsRelationManager::class, [
            'ownerRecord' => $event,
            'pageClass' => EditCertificateEvent::class,
        ])->callTableAction('correctName', $participation, ['name' => 'Budi Santoso, S.Kom.']);

        $this->assertSame('Budi Santoso, S.Kom.', $participation->participant->fresh()->name);
    }

    // ------------------------------------------- terbit dulu, kirim kemudian

    /**
     * Menerbitkan lewat panel tidak boleh ikut mengirim email; pengirimannya
     * adalah tombol tersendiri di tabel penerima.
     */
    public function test_issuing_from_the_panel_sends_no_email(): void
    {
        Queue::fake();

        $this->actingAs($this->admin());
        $event = $this->event();

        Livewire::test(ParticipationsRelationManager::class, [
            'ownerRecord' => $event,
            'pageClass' => EditCertificateEvent::class,
        ])->callTableAction('issueAllEligible')->assertHasNoActionErrors();

        Queue::assertNotPushed(SendCertificateEmailJob::class);
    }

    public function test_the_certificate_table_offers_a_separate_send_action(): void
    {
        $this->actingAs($this->admin());
        $event = $this->event();

        Certificate::factory()->create([
            'certificate_event_id' => $event->id,
            'recipient_email' => 'budi@student.jgu.ac.id',
        ]);

        Livewire::test(CertificatesRelationManager::class, [
            'ownerRecord' => $event,
            'pageClass' => EditCertificateEvent::class,
        ])
            ->assertSuccessful()
            ->assertTableActionExists('sendPendingEmails')
            ->assertTableBulkActionExists('sendEmails');
    }

    public function test_sending_from_the_panel_queues_the_waiting_emails(): void
    {
        Bus::fake();

        $this->actingAs($this->admin());
        $event = $this->event();

        Certificate::factory()->create([
            'certificate_event_id' => $event->id,
            'recipient_email' => 'budi@student.jgu.ac.id',
        ]);

        Livewire::test(CertificatesRelationManager::class, [
            'ownerRecord' => $event,
            'pageClass' => EditCertificateEvent::class,
        ])->callTableAction('sendPendingEmails')->assertHasNoActionErrors();

        Bus::assertBatched(fn ($batch) => $batch->jobs->count() === 1);
    }
}

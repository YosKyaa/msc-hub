<?php

namespace Tests\Feature;

use App\Filament\Resources\CertificateEventResource\Pages\EditCertificateEvent;
use App\Filament\Resources\CertificateEventResource\RelationManagers\ParticipationsRelationManager;
use App\Models\CertificateEvent;
use App\Models\CertificateEventParticipant;
use App\Models\Participant;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Peserta tanpa email ditandai kuning di tabel kegiatan.
 *
 * Peserta yang diimpor tanpa email tetap terdaftar dan tetap mendapat
 * sertifikat, tetapi tidak bisa dikirimi. Tanpa tanda, admin tidak tahu
 * siapa yang emailnya masih perlu dilengkapi sebelum menekan Kirim Email.
 */
class ParticipantWithoutEmailHighlightTest extends TestCase
{
    use RefreshDatabase;

    private function table(CertificateEvent $event)
    {
        $this->seed(RoleSeeder::class);
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return Livewire::actingAs($admin->fresh())->test(ParticipationsRelationManager::class, [
            'ownerRecord' => $event,
            'pageClass' => EditCertificateEvent::class,
        ]);
    }

    private function participation(CertificateEvent $event, string $nama, ?string $email): CertificateEventParticipant
    {
        return CertificateEventParticipant::factory()->create([
            'certificate_event_id' => $event->id,
            'participant_id' => Participant::factory()->create(['name' => $nama, 'email' => $email]),
        ]);
    }

    public function test_a_row_without_an_email_is_highlighted(): void
    {
        $event = CertificateEvent::factory()->create();
        $this->participation($event, 'Citra Lestari', null);
        $this->participation($event, 'Bagas Pratama', 'bagas@student.jgu.ac.id');

        $html = $this->table($event)->html();

        $this->assertSame(1, substr_count($html, 'msc-tanpa-email'));
        $this->assertStringContainsString('Belum ada email', $html);
    }

    public function test_the_summary_counts_them(): void
    {
        $event = CertificateEvent::factory()->create();
        $this->participation($event, 'Citra Lestari', null);
        $this->participation($event, 'Dimas Saputra', null);
        $this->participation($event, 'Bagas Pratama', 'bagas@student.jgu.ac.id');

        $this->table($event)->assertSee('2 belum punya email (baris kuning)');
    }

    public function test_they_can_be_filtered(): void
    {
        $event = CertificateEvent::factory()->create();
        $tanpa = $this->participation($event, 'Citra Lestari', null);
        $ada = $this->participation($event, 'Bagas Pratama', 'bagas@student.jgu.ac.id');

        $this->table($event)
            ->filterTable('without_email')
            ->assertCanSeeTableRecords([$tanpa])
            ->assertCanNotSeeTableRecords([$ada]);
    }

    /**
     * Warnanya ditulis sebagai CSS sendiri, karena panel ini tidak
     * mengompilasi kelas Tailwind yang tidak dipakai Filament.
     */
    public function test_the_highlight_style_reaches_the_panel(): void
    {
        $this->seed(RoleSeeder::class);
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->actingAs($admin->fresh())
            ->get('/panel')
            ->assertOk()
            ->assertSee('.fi-ta-row.msc-tanpa-email', false);
    }
}

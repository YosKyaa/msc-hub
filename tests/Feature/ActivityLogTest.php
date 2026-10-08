<?php

namespace Tests\Feature;

use App\Filament\Resources\ActivityLogResource;
use App\Filament\Resources\ActivityLogResource\Pages\ListActivityLogs;
use App\Filament\Resources\CertificateEventResource\Pages\EditCertificateEvent;
use App\Filament\Resources\CertificateEventResource\RelationManagers\ParticipationsRelationManager;
use App\Models\Certificate;
use App\Models\CertificateEvent;
use App\Models\CertificateEventParticipant;
use App\Models\Participant;
use App\Models\User;
use App\Services\Certificates\CertificateIssuer;
use App\Services\Certificates\RecipientCorrection;
use App\Support\ActivityDescription;
use App\Support\AppSetting;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Jejak audit: siapa mengubah apa, kapan, dari nilai apa menjadi apa.
 *
 * Sebelumnya tidak ada catatan sama sekali — paket activitylog terpasang
 * tetapi tidak dipakai di satu tempat pun. Ketika sertifikat dicabut, nama
 * dikoreksi, atau seseorang diberi hak menerbitkan, tidak ada cara mengetahui
 * siapa yang melakukannya.
 */
class ActivityLogTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $this->seed(RoleSeeder::class);

        $user = User::factory()->create(['name' => 'Admin Uji']);
        $user->assignRole('admin');

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return $user->fresh();
    }

    private function issued(): Certificate
    {
        $participation = CertificateEventParticipant::factory()->eligible()->create([
            'certificate_event_id' => CertificateEvent::factory()->published()->create()->id,
            'participant_id' => Participant::factory()->create(['name' => 'Citra Lestari', 'email' => 'citra@student.jgu.ac.id']),
        ]);

        return app(CertificateIssuer::class)->issue($participation);
    }

    private function last(string $subjectType): ?Activity
    {
        return Activity::where('subject_type', $subjectType)->latest('id')->first();
    }

    // ------------------------------------------------------------ dicatat

    /**
     * Yang paling perlu dipertanggungjawabkan: pencabutan sertifikat.
     */
    public function test_revoking_a_certificate_records_who_did_it_and_why(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin);
        $sertifikat = $this->issued();

        Livewire::test(ParticipationsRelationManager::class, [
            'ownerRecord' => $sertifikat->event,
            'pageClass' => EditCertificateEvent::class,
        ])->callTableAction('revoke', $sertifikat->participation, ['reason' => 'Salah kegiatan']);

        $catatan = $this->last(Certificate::class);

        $this->assertNotNull($catatan);
        $this->assertSame($admin->id, $catatan->causer_id);
        $this->assertSame('updated', $catatan->event);
        $this->assertNull($catatan->properties['old']['revoked_at']);
        $this->assertSame('Salah kegiatan', $catatan->properties['attributes']['revocation_reason']);
    }

    public function test_correcting_a_recipient_records_the_old_and_new_values(): void
    {
        $this->actingAs($this->admin());
        $sertifikat = $this->issued();

        app(RecipientCorrection::class)->apply($sertifikat->participant, 'Citra Lestari, S.Farm.', 'citra.baru@gmail.com');

        $peserta = $this->last(Participant::class);
        $this->assertSame('Citra Lestari', $peserta->properties['old']['name']);
        $this->assertSame('Citra Lestari, S.Farm.', $peserta->properties['attributes']['name']);
        $this->assertSame('citra.baru@gmail.com', $peserta->properties['attributes']['email']);

        $this->assertSame('citra.baru@gmail.com', $this->last(Certificate::class)->properties['attributes']['recipient_email']);
    }

    /**
     * Peran tersimpan di tabel penghubung, jadi tidak tertangkap pencatat
     * kolom biasa. Siapa memberi siapa hak apa adalah perubahan yang paling
     * perlu bisa ditelusuri.
     */
    public function test_giving_someone_a_role_is_recorded(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin);
        $staf = User::factory()->create();

        $staf->assignRole('staff_msc');

        $catatan = Activity::where('event', 'peran_diberikan')->where('subject_id', $staf->id)->sole();

        $this->assertSame($admin->id, $catatan->causer_id);
        $this->assertSame(['staff_msc'], $catatan->properties['diberikan']);
    }

    public function test_changing_what_a_role_may_do_is_recorded(): void
    {
        $this->actingAs($this->admin());

        Role::findByName('staff_msc')->revokePermissionTo('certificates.publish');

        $catatan = Activity::where('event', 'izin_dicabut')->latest('id')->first();

        $this->assertSame(['certificates.publish'], $catatan->properties['dicabut']);
        $this->assertSame('Izin dicabut: certificates.publish', $catatan->description);
        $this->assertSame(['Dicabut: certificates.publish'], ActivityDescription::changes($catatan));
    }

    /**
     * Pengaturan disimpan tanpa model, jadi dicatat tersendiri.
     */
    public function test_changing_a_setting_is_recorded_only_when_it_changes(): void
    {
        $this->actingAs($this->admin());

        AppSetting::set('jam_operasional_buka', '08:00');
        AppSetting::set('jam_operasional_buka', '08:00');
        AppSetting::set('jam_operasional_buka', '07:30');

        $catatan = Activity::where('description', 'Pengaturan jam_operasional_buka diubah')->orderBy('id')->get();

        $this->assertCount(2, $catatan);
        $this->assertSame('08:00', $catatan[1]->properties['old']['value']);
        $this->assertSame('07:30', $catatan[1]->properties['attributes']['value']);
    }

    // -------------------------------------------------------- tidak dicatat

    /**
     * Satu impor 500 peserta tidak boleh membanjiri jejak audit.
     */
    public function test_creating_participants_in_bulk_is_not_recorded(): void
    {
        Participant::factory()->count(5)->create();

        $this->assertSame(0, Activity::where('subject_type', Participant::class)->count());
    }

    public function test_a_password_never_reaches_the_log(): void
    {
        $this->actingAs($this->admin());
        $staf = User::factory()->create();

        $staf->update(['password' => 'rahasia-baru-123']);

        $this->assertSame(0, Activity::where('subject_type', User::class)->where('subject_id', $staf->id)->where('event', 'updated')->count());
        $this->assertStringNotContainsString('rahasia', Activity::all()->toJson());
    }

    // ------------------------------------------------------------- halaman

    public function test_the_admin_can_read_the_history_in_plain_words(): void
    {
        $this->actingAs($this->admin());
        $sertifikat = $this->issued();
        $sertifikat->update(['revoked_at' => now(), 'revocation_reason' => 'Salah kegiatan']);

        $catatan = $this->last(Certificate::class);

        Livewire::test(ListActivityLogs::class)
            ->assertSuccessful()
            ->assertCanSeeTableRecords([$catatan])
            ->assertSee('Sertifikat #'.$sertifikat->id)
            ->assertSee('Admin Uji');
    }

    /**
     * Catatan audit yang bisa diubah atau dihapus dari panel tidak bisa
     * dipercaya, termasuk oleh admin.
     */
    public function test_nobody_can_change_or_delete_the_history(): void
    {
        $this->actingAs($this->admin());
        $catatan = activity('audit')->log('uji');

        $this->assertFalse(ActivityLogResource::canCreate());
        $this->assertFalse(ActivityLogResource::canEdit($catatan));
        $this->assertFalse(ActivityLogResource::canDelete($catatan));
        $this->assertFalse(ActivityLogResource::canDeleteAny());
        $this->assertSame(['index'], array_keys(ActivityLogResource::getPages()));
    }

    public function test_staff_without_the_permission_cannot_open_it(): void
    {
        $this->seed(RoleSeeder::class);
        $staf = User::factory()->create();
        $staf->assignRole('staff_msc');
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->actingAs($staf->fresh())
            ->get(ActivityLogResource::getUrl())
            ->assertForbidden();
    }

    // ------------------------------------------------------------ migrasi

    /**
     * Server yang sudah berjalan mendapat izinnya lewat migrasi yang hanya
     * menambah. RoleSeeder memakai syncPermissions, yang menimpa setiap
     * perubahan izin yang pernah dibuat admin lewat panel.
     */
    public function test_the_permission_migration_keeps_custom_permissions(): void
    {
        $this->seed(RoleSeeder::class);

        $headMsc = Role::findByName('head_msc');
        $headMsc->revokePermissionTo('activity_log.view');
        $headMsc->givePermissionTo(Permission::firstOrCreate(['name' => 'izin.khusus', 'guard_name' => 'web']));

        (require database_path('migrations/2026_10_08_130000_grant_activity_log_permission.php'))->up();

        $headMsc = $headMsc->fresh();
        $this->assertTrue($headMsc->hasPermissionTo('activity_log.view'));
        $this->assertTrue($headMsc->hasPermissionTo('izin.khusus'), 'Migrasi menimpa izin yang diubah admin.');
    }
}

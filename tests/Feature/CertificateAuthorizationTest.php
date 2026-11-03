<?php

namespace Tests\Feature;

use App\Enums\AttendanceAction;
use App\Filament\Resources\CertificateEventResource;
use App\Filament\Resources\CertificateEventResource\Pages\ListCertificateEvents;
use App\Filament\Resources\CertificateTemplateResource;
use App\Filament\Resources\CertificateTemplateResource\Pages\ListCertificateTemplates;
use App\Filament\Resources\IssuerResource\Pages\ListIssuers;
use App\Filament\Resources\ParticipantResource;
use App\Filament\Resources\ParticipantResource\Pages\ListParticipants;
use App\Models\CertificateEvent;
use App\Models\CertificateTemplate;
use App\Models\Issuer;
use App\Models\Participant;
use App\Models\User;
use App\Support\CertificatePermission;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class CertificateAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    /** @var array<int, string> */
    private const CERTIFICATE_PERMISSIONS = [
        'certificates.view', 'certificates.create', 'certificates.edit',
        'certificates.delete', 'certificates.publish',
    ];

    /**
     * @param  array<int, string>  $permissions
     */
    private function userWith(string $role, array $permissions = []): User
    {
        foreach (array_unique([...self::CERTIFICATE_PERMISSIONS, ...$permissions]) as $name) {
            Permission::findOrCreate($name, 'web');
        }

        $user = User::factory()->create();
        $user->assignRole(Role::findOrCreate($role, 'web'));
        $user->givePermissionTo($permissions);

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return $user->fresh();
    }

    public function test_the_log_viewer_is_closed_to_guests(): void
    {
        $this->get('/logs')->assertRedirect(route('filament.admin.auth.login'));
    }

    public function test_the_log_viewer_is_closed_to_non_admin_users(): void
    {
        $this->actingAs($this->userWith('department'))->get('/logs')->assertForbidden();
    }

    public function test_the_log_viewer_is_open_to_admins(): void
    {
        $this->actingAs($this->userWith('admin'))->get('/logs')->assertOk();
    }

    public function test_a_user_without_certificate_permissions_cannot_reach_the_resources(): void
    {
        $this->actingAs($this->userWith('department'));

        $this->assertFalse(CertificateEventResource::canAccess());
        $this->assertFalse(CertificateTemplateResource::canAccess());
        $this->assertFalse(ParticipantResource::canAccess());
    }

    public function test_view_permission_alone_does_not_unlock_mutations(): void
    {
        $this->actingAs($this->userWith('department', ['certificates.view']));

        $this->assertTrue(CertificateEventResource::canAccess());
        $this->assertFalse(CertificateEventResource::canCreate());
        $this->assertFalse(CertificatePermission::allows('edit'));
        $this->assertFalse(CertificatePermission::allows('delete'));
        $this->assertFalse(CertificatePermission::allowsIssuing());
    }

    public function test_each_mutation_requires_its_own_permission(): void
    {
        $event = CertificateEvent::factory()->create();

        $this->actingAs($this->userWith('staff_msc', ['certificates.view', 'certificates.create']));
        $this->assertTrue(CertificateEventResource::canCreate());
        $this->assertFalse(CertificateEventResource::canEdit($event));
        $this->assertFalse(CertificateEventResource::canDelete($event));

        $this->actingAs($this->userWith('head_msc', ['certificates.view', 'certificates.edit', 'certificates.publish']));
        $this->assertTrue(CertificateEventResource::canEdit($event));
        $this->assertTrue(CertificatePermission::allowsIssuing());
        $this->assertFalse(CertificateEventResource::canDelete($event));
    }

    /**
     * @return array<string, array{0: class-string, 1: Closure(): Model}>
     */
    public static function deletableRecords(): array
    {
        return [
            'kegiatan' => [ListCertificateEvents::class, fn () => CertificateEvent::factory()->create()],
            'template' => [ListCertificateTemplates::class, fn () => CertificateTemplate::factory()->create()],
            'penerbit' => [ListIssuers::class, fn () => Issuer::factory()->create()],
            'peserta' => [ListParticipants::class, fn () => Participant::factory()->create()],
        ];
    }

    /**
     * canDelete() hanya dipanggil halaman. Tombol Hapus di tabel memakai
     * jalur lain yang, tanpa policy, selalu mengizinkan, sehingga staf tanpa
     * certificates.delete dulu bisa menghapus kegiatan beserta seluruh
     * pesertanya. Yang diuji di sini tombolnya, bukan sekadar methodnya.
     *
     * @param  class-string  $halaman
     * @param  Closure(): Model  $buat
     */
    #[DataProvider('deletableRecords')]
    public function test_the_delete_buttons_follow_the_delete_permission(string $halaman, Closure $buat): void
    {
        $record = $buat();

        $staf = Livewire::actingAs($this->userWith('staff_msc', ['certificates.view', 'certificates.create', 'certificates.edit']))
            ->test($halaman)
            ->assertTableActionHidden('delete', $record);

        // Tombol yang disembunyikan belum tentu tertutup. Permintaannya
        // dikirim langsung, seolah tombolnya tetap ditekan.
        $this->pressDelete($staf, $record);
        $this->assertModelExists($record);

        // Permintaan yang sama persis dari pemegang izin memang menghapus,
        // jadi penolakan di atas bukan karena permintaannya keliru.
        $kepala = Livewire::actingAs($this->userWith('head_msc', ['certificates.view', 'certificates.delete']))
            ->test($halaman)
            ->assertTableActionVisible('delete', $record);

        $this->pressDelete($kepala, $record);
        $this->assertModelMissing($record);
    }

    /**
     * Tekan Hapus pada sebuah baris lewat permintaan Livewire mentah, tanpa
     * pemeriksaan tampilan yang dilakukan pembantu test Filament.
     */
    private function pressDelete(Testable $tabel, Model $record): void
    {
        $tabel->call('mountAction', 'delete', [], ['table' => true, 'recordKey' => (string) $record->getKey()])
            ->call('callMountedAction');
    }

    public function test_the_attendance_poster_requires_a_signed_in_user_with_certificate_access(): void
    {
        $event = CertificateEvent::factory()->withOpenAttendance()->create();
        $poster = route('attendance.poster', ['event' => $event, 'action' => AttendanceAction::CHECK_IN->value]);

        $this->get($poster)->assertRedirect(route('filament.admin.auth.login'));

        $this->actingAs($this->userWith('department'))->get($poster)->assertForbidden();

        $this->actingAs($this->userWith('staff_msc', ['certificates.view']))
            ->get($poster)
            ->assertOk()
            ->assertSee($event->attendanceUrl(AttendanceAction::CHECK_IN));
    }

    public function test_each_attendance_action_has_its_own_poster(): void
    {
        $event = CertificateEvent::factory()->withOpenAttendance()->create();
        $this->actingAs($this->userWith('staff_msc', ['certificates.view']));

        foreach (AttendanceAction::cases() as $action) {
            $this->get(route('attendance.poster', ['event' => $event, 'action' => $action->value]))
                ->assertOk()
                ->assertSee($event->attendanceUrl($action))
                ->assertSee($action->getLabel());
        }
    }

    public function test_the_poster_is_hidden_when_attendance_is_disabled(): void
    {
        $event = CertificateEvent::factory()->create();

        $this->actingAs($this->userWith('admin', ['certificates.view']))
            ->get(route('attendance.poster', ['event' => $event, 'action' => AttendanceAction::CHECK_IN->value]))
            ->assertNotFound();
    }
}

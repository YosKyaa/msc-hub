<?php

namespace Tests\Feature;

use App\Filament\Resources\CertificateEventResource;
use App\Filament\Resources\CertificateTemplateResource;
use App\Filament\Resources\ParticipantResource;
use App\Models\CertificateEvent;
use App\Models\User;
use App\Support\CertificatePermission;
use Illuminate\Foundation\Testing\RefreshDatabase;
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

    public function test_the_attendance_poster_requires_a_signed_in_user_with_certificate_access(): void
    {
        $event = CertificateEvent::factory()->withOpenAttendance()->create();

        $this->get(route('attendance.poster', $event))->assertRedirect(route('filament.admin.auth.login'));

        $this->actingAs($this->userWith('department'))
            ->get(route('attendance.poster', $event))
            ->assertForbidden();

        $this->actingAs($this->userWith('staff_msc', ['certificates.view']))
            ->get(route('attendance.poster', $event))
            ->assertOk()
            ->assertSee($event->attendanceUrl());
    }

    public function test_the_poster_is_hidden_when_attendance_is_disabled(): void
    {
        $event = CertificateEvent::factory()->create();

        $this->actingAs($this->userWith('admin', ['certificates.view']))
            ->get(route('attendance.poster', $event))
            ->assertNotFound();
    }
}

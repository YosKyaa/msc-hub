<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RoleSeeder;
use Filament\Resources\Resource;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Izin yang tidak diberikan harus benar-benar menutup aksinya.
 *
 * Filament **mengizinkan** ketika sebuah aksi tidak punya policy: lihat
 * `Filament\get_authorization_response()`, yang mengembalikan `Response::allow()`
 * bila model tidak punya policy atau policy-nya tidak punya metode aksi itu.
 * Resource yang hanya menjaga `canAccess()` karena itu membiarkan siapa pun
 * yang boleh *melihat* daftarnya ikut membuat, menyunting, dan menghapus.
 *
 * Bukan kemungkinan teoretis: peran `head_msc` hanya memegang `roles.view`,
 * sehingga ia dapat menyunting peran mana pun — termasuk memberi dirinya
 * sendiri seluruh izin — dan peran `department` hanya memegang
 * `announcements.view` tetapi dapat menghapus pengumuman di halaman publik.
 */
class PanelAuthorizationTest extends TestCase
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

    /**
     * @return list<class-string<resource>>
     */
    private function resources(): array
    {
        $kelas = [];

        foreach (File::files(app_path('Filament/Resources')) as $berkas) {
            $nama = 'App\\Filament\\Resources\\'.$berkas->getFilenameWithoutExtension();

            if (class_exists($nama) && is_subclass_of($nama, Resource::class)) {
                $kelas[] = $nama;
            }
        }

        // Penjaga bagi penjaga: bila penyusurannya rusak, test di bawah lulus
        // tanpa memeriksa satu resource pun.
        $this->assertGreaterThan(15, count($kelas));

        return $kelas;
    }

    // ------------------------------------------- kenaikan hak yang sebenarnya

    /**
     * Yang paling berbahaya dari seluruhnya: memegang `roles.view` saja sudah
     * cukup untuk menyunting peran, dan menyunting peran berarti dapat
     * memberikan izin apa pun kepada diri sendiri.
     */
    public function test_seeing_the_roles_list_does_not_let_you_rewrite_roles(): void
    {
        $this->actingAs($kepala = $this->user('head_msc'));

        $this->assertTrue($kepala->can('roles.view'), 'Prasyarat: head_msc memang boleh melihat peran.');
        $this->assertFalse($kepala->can('roles.edit'), 'Prasyarat: head_msc tidak diberi izin menyunting peran.');

        $peran = \Spatie\Permission\Models\Role::where('name', 'admin')->firstOrFail();

        $this->assertTrue(\App\Filament\Resources\RoleResource::canViewAny());
        $this->assertFalse(\App\Filament\Resources\RoleResource::canEdit($peran),
            'Pemegang roles.view dapat menyunting peran, jadi dapat memberi dirinya seluruh izin.');
        $this->assertFalse(\App\Filament\Resources\RoleResource::canCreate());
        $this->assertFalse(\App\Filament\Resources\RoleResource::canDelete($peran));
    }

    /**
     * `head_msc` memegang `users.view` tanpa izin membuat atau menghapus.
     */
    public function test_seeing_the_user_list_does_not_let_you_create_or_delete_users(): void
    {
        $this->actingAs($kepala = $this->user('head_msc'));

        $this->assertTrue($kepala->can('users.view'));
        $this->assertFalse($kepala->can('users.delete'));

        $korban = User::factory()->create();

        $this->assertTrue(\App\Filament\Resources\UserResource::canViewAny());
        $this->assertFalse(\App\Filament\Resources\UserResource::canCreate());
        $this->assertFalse(\App\Filament\Resources\UserResource::canEdit($korban));
        $this->assertFalse(\App\Filament\Resources\UserResource::canDelete($korban));
    }

    /**
     * `department` hanya boleh melihat pengumuman, tetapi pengumuman tampil di
     * halaman publik.
     */
    public function test_a_department_account_cannot_rewrite_public_announcements(): void
    {
        $this->actingAs($this->user('department'));

        $pengumuman = \App\Models\Announcement::create([
            'title' => 'Jadwal Peminjaman Studio',
            'summary' => 'Studio tutup selama pekan ujian.',
            'content' => 'Studio MSC tutup selama pekan ujian akhir semester.',
            'category' => 'announcement',
        ]);

        $this->assertTrue(\App\Filament\Resources\AnnouncementResource::canViewAny());
        $this->assertFalse(\App\Filament\Resources\AnnouncementResource::canCreate());
        $this->assertFalse(\App\Filament\Resources\AnnouncementResource::canEdit($pengumuman));
        $this->assertFalse(\App\Filament\Resources\AnnouncementResource::canDelete($pengumuman));
    }

    /**
     * `staff_msc` boleh membuat dan menyunting pengumuman, tetapi tidak
     * menghapusnya. Yang diberikan harus tetap berlaku.
     */
    public function test_the_permissions_that_were_granted_still_work(): void
    {
        $this->actingAs($staf = $this->user('staff_msc'));

        $this->assertTrue($staf->can('announcements.create'));
        $this->assertFalse($staf->can('announcements.delete'));

        $pengumuman = \App\Models\Announcement::create([
            'title' => 'Uji',
            'summary' => 'Uji',
            'content' => 'Uji',
            'category' => 'announcement',
        ]);

        $this->assertTrue(\App\Filament\Resources\AnnouncementResource::canCreate());
        $this->assertTrue(\App\Filament\Resources\AnnouncementResource::canEdit($pengumuman));
        $this->assertFalse(\App\Filament\Resources\AnnouncementResource::canDelete($pengumuman));
    }

    /**
     * Admin tidak boleh ikut terkunci oleh perbaikan ini.
     */
    public function test_an_admin_may_still_do_everything(): void
    {
        $this->actingAs($this->user('admin'));

        $tertutup = [];

        foreach ($this->resources() as $resource) {
            // CertificateResource memang direktori baca-saja: sertifikat
            // diterbitkan lewat kegiatannya, bukan diketik tangan.
            if ($resource === \App\Filament\Resources\CertificateResource::class) {
                continue;
            }

            // Riwayat Aktivitas juga baca-saja, bahkan bagi admin: catatan
            // audit yang bisa diubah dari panel tidak bisa dipercaya.
            if ($resource === \App\Filament\Resources\ActivityLogResource::class) {
                $this->assertTrue($resource::canViewAny(), 'Admin tidak bisa membaca Riwayat Aktivitas.');

                continue;
            }

            if (! $resource::canViewAny() || ! $resource::canCreate()) {
                $tertutup[] = class_basename($resource);
            }
        }

        $this->assertSame([], $tertutup,
            'Admin kehilangan akses ke: '.implode(', ', $tertutup));
    }

    // --------------------------------------------------- penjaga menyeluruh

    /**
     * Resource berikutnya yang ditambahkan akan mewarisi lubang yang sama bila
     * tidak ada yang mengingatkan: menjaga `canAccess()` saja terasa cukup,
     * padahal Filament mengizinkan sisanya.
     */
    public function test_no_resource_leaves_its_write_actions_wide_open(): void
    {
        // Akun tanpa izin apa pun selain membuka panel.
        $this->seed(RoleSeeder::class);
        $user = User::factory()->create();
        $user->givePermissionTo('panel.access');
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->actingAs($user->fresh());

        $terbuka = [];

        foreach ($this->resources() as $resource) {
            if ($resource::canCreate()) {
                $terbuka[] = class_basename($resource).'::canCreate';
            }

            if ($resource::canDeleteAny()) {
                $terbuka[] = class_basename($resource).'::canDeleteAny';
            }
        }

        $this->assertSame([], $terbuka,
            "Akun tanpa izin apa pun masih boleh menulis di:\n".implode("\n", $terbuka));
    }
}

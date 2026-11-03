<?php

namespace Tests\Feature;

use App\Filament\Resources\AchievementResource\Pages\ListAchievements;
use App\Filament\Resources\ActivityLogResource;
use App\Filament\Resources\AnnouncementResource;
use App\Filament\Resources\AnnouncementResource\Pages\EditAnnouncement;
use App\Filament\Resources\CertificateResource;
use App\Filament\Resources\FeaturedWorkResource\Pages\ListFeaturedWorks;
use App\Filament\Resources\InventoryItemResource\Pages\ListInventoryItems;
use App\Filament\Resources\RoleResource;
use App\Filament\Resources\RoleResource\Pages\ListRoles;
use App\Filament\Resources\TwibbonResource\Pages\EditTwibbon;
use App\Filament\Resources\UserResource;
use App\Models\Achievement;
use App\Models\Announcement;
use App\Models\FeaturedWork;
use App\Models\InventoryItem;
use App\Models\Twibbon;
use App\Models\User;
use Closure;
use Database\Seeders\RoleSeeder;
use Filament\Resources\Resource;
use Illuminate\Auth\Access\Response;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
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

        $peran = Role::where('name', 'admin')->firstOrFail();

        $this->assertTrue(RoleResource::canViewAny());
        $this->assertFalse(RoleResource::canEdit($peran),
            'Pemegang roles.view dapat menyunting peran, jadi dapat memberi dirinya seluruh izin.');
        $this->assertFalse(RoleResource::canCreate());
        $this->assertFalse(RoleResource::canDelete($peran));
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

        $this->assertTrue(UserResource::canViewAny());
        $this->assertFalse(UserResource::canCreate());
        $this->assertFalse(UserResource::canEdit($korban));
        $this->assertFalse(UserResource::canDelete($korban));
    }

    /**
     * `department` hanya boleh melihat pengumuman, tetapi pengumuman tampil di
     * halaman publik.
     */
    public function test_a_department_account_cannot_rewrite_public_announcements(): void
    {
        $this->actingAs($this->user('department'));

        $pengumuman = Announcement::create([
            'title' => 'Jadwal Peminjaman Studio',
            'summary' => 'Studio tutup selama pekan ujian.',
            'content' => 'Studio MSC tutup selama pekan ujian akhir semester.',
            'category' => 'announcement',
        ]);

        $this->assertTrue(AnnouncementResource::canViewAny());
        $this->assertFalse(AnnouncementResource::canCreate());
        $this->assertFalse(AnnouncementResource::canEdit($pengumuman));
        $this->assertFalse(AnnouncementResource::canDelete($pengumuman));
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

        $pengumuman = Announcement::create([
            'title' => 'Uji',
            'summary' => 'Uji',
            'content' => 'Uji',
            'category' => 'announcement',
        ]);

        $this->assertTrue(AnnouncementResource::canCreate());
        $this->assertTrue(AnnouncementResource::canEdit($pengumuman));
        $this->assertFalse(AnnouncementResource::canDelete($pengumuman));
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
            if ($resource === CertificateResource::class) {
                continue;
            }

            // Riwayat Aktivitas juga baca-saja, bahkan bagi admin: catatan
            // audit yang bisa diubah dari panel tidak bisa dipercaya.
            if ($resource === ActivityLogResource::class) {
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

    // ------------------------------------------- tombolnya, bukan methodnya

    /**
     * Peran bawaan RoleSeeder yang tidak memegang izin hapus, beserta tombol
     * Hapus yang dulu tetap bisa ia tekan.
     *
     * @return array<string, array{string, string, class-string, Closure(): Model, bool}>
     */
    public static function deleteButtons(): array
    {
        return [
            'peran, oleh head_msc' => ['head_msc', 'roles.delete', ListRoles::class, fn () => Role::create(['name' => 'uji', 'guard_name' => 'web']), true],
            'item inventaris, oleh staff_msc' => ['staff_msc', 'inventory.delete', ListInventoryItems::class, fn () => InventoryItem::factory()->create(), true],
            'karya unggulan, oleh staff_msc' => ['staff_msc', 'featured_works.delete', ListFeaturedWorks::class, fn () => FeaturedWork::factory()->create(), true],
            'prestasi, oleh staff_msc' => ['staff_msc', 'achievements.delete', ListAchievements::class, fn () => Achievement::factory()->create(), true],
            'pengumuman, oleh staff_msc' => ['staff_msc', 'announcements.delete', EditAnnouncement::class, fn () => Announcement::factory()->create(), false],
            'twibbon, oleh staff_msc' => ['staff_msc', 'twibbons.delete', EditTwibbon::class, fn () => Twibbon::factory()->create(), false],
        ];
    }

    /**
     * canDelete() hanya dipanggil halaman. Tombol Hapus di tabel, hapus
     * massal, dan tombol Hapus di halaman Ubah meminta
     * getDeleteAuthorizationResponse(), yang tanpa policy selalu mengizinkan.
     * Dulu head_msc, yang hanya boleh melihat peran, karena itu bisa menghapus
     * peran dari tabelnya, dan staf MSC bisa menghapus pengumuman publik.
     *
     * Yang diuji karena itu tombolnya, lewat permintaan yang dikirim langsung,
     * bukan sekadar methodnya.
     *
     * @param  class-string  $halaman
     * @param  Closure(): Model  $buat
     */
    #[DataProvider('deleteButtons')]
    public function test_the_delete_buttons_follow_the_delete_permission(string $peran, string $izin, string $halaman, Closure $buat, bool $diTabel): void
    {
        $tanpaIzin = $this->user($peran);
        $this->assertFalse($tanpaIzin->can($izin), "Prasyarat: {$peran} tidak diberi {$izin}.");

        $record = $buat();

        $layar = $this->open($tanpaIzin, $halaman, $record, $diTabel);
        $diTabel ? $layar->assertTableActionHidden('delete', $record) : $layar->assertActionHidden('delete');

        // Tombol yang disembunyikan belum tentu tertutup. Permintaannya
        // dikirim langsung, seolah tombolnya tetap ditekan.
        $this->pressDelete($layar, $record, $diTabel);
        $this->assertTrue($this->stillThere($record), "{$peran} tetap bisa menghapus tanpa {$izin}.");

        // Permintaan yang sama persis dari admin memang menghapus, jadi
        // penolakan di atas bukan karena permintaannya keliru.
        $this->pressDelete($this->open($this->user('admin'), $halaman, $record, $diTabel), $record, $diTabel);
        $this->assertFalse($this->stillThere($record));
    }

    private function open(User $user, string $halaman, Model $record, bool $diTabel): Testable
    {
        return $diTabel
            ? Livewire::actingAs($user)->test($halaman)
            : Livewire::actingAs($user)->test($halaman, ['record' => $record->getRouteKey()]);
    }

    /**
     * Tekan Hapus lewat permintaan Livewire mentah, tanpa pemeriksaan tampilan
     * yang dilakukan pembantu test Filament.
     */
    private function pressDelete(Testable $layar, Model $record, bool $diTabel): void
    {
        $diTabel
            ? $layar->call('mountAction', 'delete', [], ['table' => true, 'recordKey' => (string) $record->getKey()])
            : $layar->call('mountAction', 'delete');

        $layar->call('callMountedAction');
    }

    /**
     * Yang terhapus lunak ikut dihitung terhapus: ia hilang dari panel.
     */
    private function stillThere(Model $record): bool
    {
        return $record::query()->whereKey($record->getKey())->exists();
    }

    // --------------------------------------------------- penjaga menyeluruh

    /**
     * Resource berikutnya yang ditambahkan akan mewarisi lubang yang sama bila
     * tidak ada yang mengingatkan: menjaga `canAccess()` saja terasa cukup,
     * padahal Filament mengizinkan sisanya.
     *
     * Yang diperiksa jalur yang dipakai tombol aksi bawaan, bukan canCreate()
     * dan kawan-kawannya: dulu keduanya bisa menjawab berbeda, dan hanya yang
     * kedua yang diperiksa.
     */
    public function test_no_resource_leaves_its_write_actions_wide_open(): void
    {
        // Akun yang boleh melihat segalanya, tetapi tidak boleh menulis apa pun.
        $this->seed(RoleSeeder::class);
        $user = User::factory()->create();
        $user->givePermissionTo(Permission::where('name', 'like', '%.view')->pluck('name')->push('panel.access')->all());
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->actingAs($user->fresh());

        $terbuka = [];

        foreach ($this->resources() as $resource) {
            $model = $resource::getModel();
            $contoh = new $model;

            $jawaban = [
                'create' => $resource::getCreateAuthorizationResponse(),
                'update' => $resource::getEditAuthorizationResponse($contoh),
                'delete' => $resource::getDeleteAuthorizationResponse($contoh),
                'deleteAny' => $resource::getDeleteAnyAuthorizationResponse(),
            ];

            if (in_array(SoftDeletes::class, class_uses_recursive($model), true)) {
                $jawaban += [
                    'restore' => $resource::getRestoreAuthorizationResponse($contoh),
                    'restoreAny' => $resource::getRestoreAnyAuthorizationResponse(),
                    'forceDelete' => $resource::getForceDeleteAuthorizationResponse($contoh),
                    'forceDeleteAny' => $resource::getForceDeleteAnyAuthorizationResponse(),
                ];
            }

            foreach ($jawaban as $aksi => $response) {
                /** @var Response $response */
                if ($response->allowed()) {
                    $terbuka[] = class_basename($resource).'::'.$aksi;
                }
            }
        }

        $this->assertSame([], $terbuka,
            "Akun yang hanya boleh melihat masih boleh menulis di:\n".implode("\n", $terbuka));
    }
}

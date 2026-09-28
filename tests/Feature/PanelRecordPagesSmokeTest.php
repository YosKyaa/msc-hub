<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RoleSeeder;
use Filament\Resources\Resource;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;
use Throwable;

/**
 * Halaman yang membutuhkan satu catatan: ubah dan lihat.
 *
 * PanelPagesSmokeTest hanya menyusuri rute tanpa parameter — daftar, formulir
 * baru, dan halaman kustom. Halaman ubah dan lihat karena itu tidak pernah
 * benar-benar dibuka oleh test mana pun, padahal justru di situlah relasi
 * dimuat, kolom dirender, dan aksi per baris disusun. Satu relasi yang dihapus
 * atau kolom yang diganti nama baru ketahuan saat admin membuka catatannya.
 */
class PanelRecordPagesSmokeTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Resource yang memang tidak bisa dibuatkan catatan contoh.
     *
     * Peran dan izin adalah model milik paket Spatie, yang tidak menyediakan
     * factory. Keduanya sudah terjaga PanelAuthorizationTest.
     *
     * @var list<string>
     */
    private const TANPA_FACTORY = ['PermissionResource', 'RoleResource'];

    private function admin(): User
    {
        $this->seed(RoleSeeder::class);

        $user = User::factory()->create();
        $user->assignRole('admin');

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

        return $kelas;
    }

    /**
     * Satu catatan untuk dibuka. Dibuat lewat factory bila ada; bila tidak,
     * resource itu dilewati dan disebut di hasilnya supaya kelewatannya
     * terlihat, bukan tersembunyi.
     */
    private function sampleRecord(string $resource): ?Model
    {
        $model = $resource::getModel();

        try {
            return $model::factory()->create();
        } catch (Throwable) {
            return null;
        }
    }

    public function test_every_record_page_opens_for_an_admin(): void
    {
        $admin = $this->admin();
        $gagal = [];
        $dilewati = [];
        $dibuka = 0;

        foreach ($this->resources() as $resource) {
            $record = $this->sampleRecord($resource);

            if ($record === null) {
                $dilewati[] = class_basename($resource);

                continue;
            }

            foreach (['edit', 'view'] as $halaman) {
                if (! array_key_exists($halaman, $resource::getPages())) {
                    continue;
                }

                try {
                    $url = $resource::getUrl($halaman, ['record' => $record]);
                } catch (Throwable $e) {
                    $gagal[] = class_basename($resource)." ({$halaman}) tidak punya URL: ".$e->getMessage();

                    continue;
                }

                $status = $this->actingAs($admin)->get($url)->getStatusCode();
                $dibuka++;

                if ($status !== 200) {
                    $gagal[] = class_basename($resource)." ({$halaman}) menghasilkan {$status} di {$url}";
                }
            }
        }

        // Penjaga bagi penjaga. Tanpa ini, resource yang modelnya kehilangan
        // factory akan dilewati diam-diam, dan test tetap hijau sambil
        // memeriksa makin sedikit — persis cara cakupan menyusut tanpa ada
        // yang menyadarinya.
        $this->assertSame(self::TANPA_FACTORY, $dilewati,
            'Daftar resource yang dilewati berubah. Tambahkan factory untuk model barunya, '
            .'atau sebutkan alasannya di TANPA_FACTORY.');

        $this->assertGreaterThanOrEqual(20, $dibuka,
            'Terlalu sedikit halaman yang benar-benar dibuka.');

        $this->assertSame([], $gagal, "Halaman catatan gagal terbuka:\n".implode("\n", $gagal));
    }

    /**
     * Halaman ubah menampilkan formulirnya, jadi isian yang merujuk kolom atau
     * relasi yang sudah tidak ada akan tumbang di sini — bukan di tangan admin.
     */
    public function test_every_edit_form_loads_the_record_it_is_editing(): void
    {
        $admin = $this->admin();
        $gagal = [];

        foreach ($this->resources() as $resource) {
            if (! array_key_exists('edit', $resource::getPages())) {
                continue;
            }

            $record = $this->sampleRecord($resource);

            if ($record === null) {
                continue;
            }

            $response = $this->actingAs($admin)->get($resource::getUrl('edit', ['record' => $record]));

            if ($response->getStatusCode() !== 200) {
                $gagal[] = class_basename($resource);

                continue;
            }

            // Judul catatannya harus muncul di halamannya. Bila tidak,
            // formulirnya tidak benar-benar terisi.
            $judul = $resource::getRecordTitleAttribute();

            if ($judul === null || blank($record->{$judul})) {
                continue;
            }

            $response->assertSee(e((string) $record->{$judul}), false);
        }

        $this->assertSame([], $gagal, 'Formulir ubah gagal termuat: '.implode(', ', $gagal));
    }
}

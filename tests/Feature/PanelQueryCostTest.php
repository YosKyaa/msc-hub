<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RoleSeeder;
use Filament\Resources\Resource;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;
use Throwable;

/**
 * Daftar di panel tidak boleh bertambah berat seiring isinya.
 *
 * Kolom yang menyebut relasi — nama ruangan, nama penerbit, jumlah alat —
 * menghasilkan satu kueri tambahan per baris bila relasinya tidak dimuat di
 * muka. Sepuluh baris masih terasa cepat, jadi masalahnya tidak pernah
 * terlihat saat dikembangkan; ia baru muncul setelah setahun data terkumpul,
 * ketika halaman yang sama memuat ratusan kueri.
 */
class PanelQueryCostTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Daftar yang memang membaca sekali per baris, beserta alasannya.
     *
     * IssuerResource menampilkan posisi register nomor tiap penerbit. Angkanya
     * hidup di tabel terpisah dengan kunci yang dihitung per baris — dari
     * aturan pengulangan penerbit dan tanggal hari ini — sehingga tidak bisa
     * ikut dimuat lewat relasi. Penerbit adalah himpunan yang sengaja kecil
     * dan terbatas: Rektorat, SCD, tiap jurusan, dan MSC. Biayanya karena itu
     * tidak pernah tumbuh seperti daftar peminjaman atau peserta.
     *
     * Pengecualian ditulis satu per satu dan beralasan, bukan dengan
     * melonggarkan ambangnya untuk semua.
     *
     * @var list<string>
     */
    private const DIKECUALIKAN = ['IssuerResource'];

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

    private function countQueries(callable $aksi): int
    {
        $n = 0;

        DB::listen(function () use (&$n): void {
            $n++;
        });

        $aksi();

        DB::flushQueryLog();

        return $n;
    }

    /**
     * Cara mengukurnya: buka daftar dengan sedikit baris, tambah baris
     * sepuluh kali lipat, lalu buka lagi. Jumlah kuerinya harus tetap.
     */
    public function test_no_list_page_queries_once_per_row(): void
    {
        $admin = $this->admin();
        $boros = [];
        $terukur = [];

        foreach ($this->resources() as $resource) {
            $model = $resource::getModel();

            try {
                $model::factory()->count(2)->create();
            } catch (Throwable) {
                continue;
            }

            $url = $resource::getUrl('index');

            $sedikit = $this->countQueries(
                fn () => $this->actingAs($admin)->get($url)->assertOk()
            );

            try {
                $model::factory()->count(20)->create();
            } catch (Throwable) {
                continue;
            }

            $banyak = $this->countQueries(
                fn () => $this->actingAs($admin)->get($url)->assertOk()
            );

            $terukur[] = class_basename($resource);

            if (in_array(class_basename($resource), self::DIKECUALIKAN, true)) {
                continue;
            }

            // Sedikit kelonggaran: Filament sesekali menambah kueri untuk
            // menyusun pilihan penyaring, bukan per baris.
            if ($banyak > $sedikit + 2) {
                $boros[] = class_basename($resource).": {$sedikit} kueri untuk 2 baris, {$banyak} untuk 22";
            }
        }

        // Penjaga bagi penjaga: bila pembuatan datanya gagal menyeluruh, test
        // ini lulus tanpa mengukur apa pun.
        $this->assertGreaterThanOrEqual(8, count($terukur),
            'Terlalu sedikit daftar yang terukur: '.implode(', ', $terukur));

        $this->assertSame([], $boros,
            "Jumlah kueri ikut bertambah seiring jumlah baris:\n".implode("\n", $boros));
    }
}

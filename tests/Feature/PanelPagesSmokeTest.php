<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Setiap halaman panel harus terbuka bagi admin.
 *
 * Panel dipakai untuk menyetujui peminjaman dan menerbitkan sertifikat, jadi
 * satu halaman yang tumbang berarti pekerjaan berhenti. Test ini menyusuri
 * seluruh rute panel yang tidak memerlukan record, sehingga halaman yang
 * rusak karena perubahan bersama langsung ketahuan.
 */
class PanelPagesSmokeTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $this->seed(RoleSeeder::class);

        $user = User::factory()->create();
        $user->assignRole('admin');

        return $user;
    }

    /**
     * Semua rute panel yang bisa dibuka tanpa parameter: daftar, formulir
     * baru, dan halaman kustom.
     *
     * @return array<string, string>
     */
    private function parameterlessRoutes(): array
    {
        $routes = [];

        foreach (Route::getRoutes() as $route) {
            $name = $route->getName();

            if ($name === null || ! str_starts_with($name, 'filament.admin.')) {
                continue;
            }

            if (! in_array('GET', $route->methods(), true)) {
                continue;
            }

            // Halaman login dan rute yang butuh record ditangani terpisah.
            if (str_contains($name, '.auth.') || $route->parameterNames() !== []) {
                continue;
            }

            $routes[$name] = route($name);
        }

        return $routes;
    }

    public function test_the_panel_exposes_pages_to_smoke_test(): void
    {
        // Penjaga: kalau enumerasinya rusak, test di bawah akan lulus tanpa
        // benar-benar membuka apa pun.
        $this->assertGreaterThan(20, count($this->parameterlessRoutes()));
    }

    public function test_every_panel_page_opens_for_an_admin(): void
    {
        $admin = $this->admin();
        $gagal = [];

        foreach ($this->parameterlessRoutes() as $name => $url) {
            $status = $this->actingAs($admin)->get($url)->getStatusCode();

            if ($status !== 200) {
                $gagal[] = "{$name} ({$url}) menghasilkan {$status}";
            }
        }

        $this->assertSame([], $gagal, "Halaman panel gagal terbuka:\n".implode("\n", $gagal));
    }
}

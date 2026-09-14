<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Halaman dokumentasi panel.
 *
 * Sebelumnya hanya membahas Projects, Assets, dan Tags — sepertiga sistem —
 * sementara peminjaman, permintaan konten, dan sertifikat yang justru paling
 * sering dikerjakan tidak disebut sama sekali. Test ini menjaga agar tiap
 * modul tetap punya penjelasan, dan langkahnya tetap bernomor.
 */
class PanelDocumentationTest extends TestCase
{
    use RefreshDatabase;

    private function page(): TestResponse
    {
        $this->seed(RoleSeeder::class);

        $user = User::factory()->create();
        $user->assignRole('admin');

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return $this->actingAs($user->fresh())
            ->get(route('filament.admin.pages.dokumentasi'))
            ->assertOk();
    }

    public function test_every_module_is_explained(): void
    {
        $response = $this->page();

        foreach ([
            'Permintaan Konten',
            'Peminjaman Ruangan &amp; Alat',
            'Sertifikat',
            'Arsip Media',
        ] as $modul) {
            $response->assertSee($modul, false);
        }
    }

    /**
     * Tiap alur ditulis sebagai langkah bernomor dari pengajuan sampai
     * selesai, bukan sekadar daftar menu.
     */
    public function test_each_flow_is_written_as_numbered_steps(): void
    {
        $response = $this->page();

        $response->assertSee('Pemohon mengajukan');
        $response->assertSee('Staf meninjau');
        $response->assertSee('Kepala MSC menyetujui');
        $response->assertSee('Terbitkan digital');
        $response->assertSee('Kirim email');
    }

    /**
     * Pemisahan dua tahap sertifikat adalah hal yang paling sering
     * disalahpahami, jadi harus dijelaskan di sini.
     */
    public function test_the_two_stage_certificate_flow_is_spelled_out(): void
    {
        $response = $this->page();

        $response->assertSee('Nomor diberikan dan halaman verifikasi langsung aktif. Email belum dikirim.');
        $response->assertSee('Sudah diterbitkan, kenapa emailnya belum sampai?');
    }

    public function test_it_says_who_may_do_what(): void
    {
        $response = $this->page();

        foreach (['Admin', 'Kepala MSC', 'Staf MSC', 'Dosen / Mahasiswa'] as $peran) {
            $response->assertSee($peran, false);
        }
    }

    /**
     * Kendala yang paling sering muncul dijawab di tempat, ketimbang membuat
     * staf menebak atau bertanya.
     */
    public function test_the_common_snags_are_answered(): void
    {
        $response = $this->page();

        $response->assertSee('Kenapa sertifikat tidak bisa diterbitkan?');
        $response->assertSee('queue:work', false);
        $response->assertSee('Peserta salah ketik namanya di sertifikat.');
    }

    /**
     * Tiap alur berujung pada tautan ke halamannya, supaya dokumentasi ini
     * juga berfungsi sebagai jalan pintas.
     */
    public function test_each_flow_links_to_the_page_it_describes(): void
    {
        $response = $this->page();

        foreach ([
            'filament.admin.resources.content-requests.index',
            'filament.admin.resources.room-bookings.index',
            'filament.admin.resources.inventory-bookings.index',
            'filament.admin.resources.certificate-events.index',
            'filament.admin.resources.projects.index',
        ] as $route) {
            $response->assertSee(route($route), false);
        }
    }
}

<?php

namespace Tests\Feature;

use App\Filament\Pages\DokumentasiTopik;
use App\Models\User;
use App\Support\PanduanPanel;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Panduan panel.
 *
 * Dulu satu halaman panjang berisi semua modul sekaligus, sehingga yang
 * dicari harus digulir dulu — dan isinya pun hanya membahas Projects, Assets,
 * dan Tags, sepertiga sistem. Sekarang halaman depannya berupa kartu, dan
 * tiap kartu membuka halaman rinciannya sendiri.
 */
class PanelDocumentationTest extends TestCase
{
    use RefreshDatabase;

    private function signIn(): void
    {
        $this->seed(RoleSeeder::class);

        $user = User::factory()->create();
        $user->assignRole('admin');

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->actingAs($user->fresh());
    }

    private function index(): TestResponse
    {
        $this->signIn();

        return $this->get(route('filament.admin.pages.dokumentasi'))->assertOk();
    }

    private function topic(string $slug): TestResponse
    {
        $this->signIn();

        return $this->get(DokumentasiTopik::getUrl(['topik' => $slug]))->assertOk();
    }

    // -------------------------------------------------------------- kartu

    /**
     * Yang diminta: kartu dulu, rinciannya di halaman masing-masing.
     */
    public function test_the_front_page_is_a_card_for_every_topic(): void
    {
        $response = $this->index();

        foreach (PanduanPanel::semua() as $slug => $item) {
            $response->assertSee($item['judul']);
            $response->assertSee($item['ringkas']);
            $response->assertSee(DokumentasiTopik::getUrl(['topik' => $slug]), false);
        }

        $response->assertSee('Baca selengkapnya');
    }

    /**
     * Rinciannya tidak boleh ikut tumpah ke halaman depan; itu justru yang
     * membuat versi lamanya harus digulir.
     */
    public function test_the_front_page_stays_a_summary(): void
    {
        $this->index()->assertDontSee('Sudah diterbitkan, kenapa emailnya belum sampai?');
    }

    public function test_every_module_has_its_own_page(): void
    {
        foreach (['permintaan-konten', 'peminjaman', 'sertifikat', 'arsip-media'] as $slug) {
            $this->topic($slug)->assertSee(PanduanPanel::topik($slug)['judul']);
        }
    }

    public function test_an_unknown_topic_is_not_found(): void
    {
        $this->signIn();

        $this->get(DokumentasiTopik::getUrl(['topik' => 'tidak-ada']))->assertNotFound();
    }

    // ------------------------------------------------------------ isinya

    /**
     * Tiap alur ditulis sebagai langkah bernomor dari pengajuan sampai
     * selesai, bukan sekadar daftar menu.
     */
    public function test_each_flow_is_written_as_numbered_steps(): void
    {
        $konten = $this->topic('permintaan-konten');
        $konten->assertSee('Pemohon mengajukan');
        $konten->assertSee('Staf meninjau');
        $konten->assertSee('Kepala MSC menyetujui');

        $sertifikat = $this->topic('sertifikat');
        $sertifikat->assertSee('Terbitkan digital');
        $sertifikat->assertSee('Kirim email');
    }

    /**
     * Pemisahan dua tahap sertifikat adalah hal yang paling sering
     * disalahpahami, jadi harus dijelaskan di halamannya.
     */
    public function test_the_two_stage_certificate_flow_is_spelled_out(): void
    {
        $response = $this->topic('sertifikat');

        $response->assertSee('Nomor diberikan dan halaman verifikasi langsung aktif. Email BELUM dikirim.');
        $response->assertSee('penerbitan yang keliru tidak terlanjur mendarat di kotak masuk peserta');
    }

    /**
     * Penomoran lintas unit penerbit ikut dijelaskan, karena itu yang baru
     * saja berubah dan paling mudah disalahpahami.
     */
    public function test_the_per_authority_numbering_is_explained(): void
    {
        $this->topic('sertifikat')
            ->assertSee('Rektorat, SCD, jurusan, MSC', false);
    }

    public function test_it_says_who_may_do_what(): void
    {
        $response = $this->topic('hak-akses');

        foreach (['Admin', 'Kepala MSC', 'Staf MSC', 'Dosen / Mahasiswa'] as $peran) {
            $response->assertSee($peran, false);
        }
    }

    public function test_the_common_snags_are_answered(): void
    {
        $response = $this->topic('kendala-umum');

        $response->assertSee('Kenapa sertifikat tidak bisa diterbitkan?');
        $response->assertSee('queue:work', false);
        $response->assertSee('Nomor sertifikat tidak sesuai register unit.');
    }

    /**
     * Tiap topik berujung pada tautan ke halamannya, supaya panduan ini juga
     * berfungsi sebagai jalan pintas — dan punya jalan kembali.
     */
    public function test_each_topic_links_to_the_pages_it_describes(): void
    {
        $this->topic('peminjaman')
            ->assertSee(route('filament.admin.resources.room-bookings.index'), false)
            ->assertSee(route('filament.admin.resources.inventory-bookings.index'), false);

        $this->topic('sertifikat')
            ->assertSee(route('filament.admin.resources.certificate-events.index'), false)
            ->assertSee(route('filament.admin.resources.issuers.index'), false);
    }

    public function test_every_topic_offers_a_way_back(): void
    {
        foreach (array_keys(PanduanPanel::semua()) as $slug) {
            $this->topic($slug)
                ->assertSee('Kembali ke daftar panduan')
                ->assertSee(route('filament.admin.pages.dokumentasi'), false);
        }
    }

    /**
     * Halaman rinciannya tidak muncul di navigasi kiri: pintu masuknya satu,
     * lewat kartu.
     */
    public function test_the_detail_pages_stay_out_of_the_sidebar(): void
    {
        $this->assertFalse(DokumentasiTopik::shouldRegisterNavigation());
    }
}

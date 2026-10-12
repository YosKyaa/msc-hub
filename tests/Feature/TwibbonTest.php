<?php

namespace Tests\Feature;

use App\Filament\Resources\TwibbonResource;
use App\Filament\Resources\TwibbonResource\Pages\CreateTwibbon;
use App\Models\Twibbon;
use App\Models\User;
use App\Support\TwibbonFrame;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Twibbon: admin mengunggah bingkai, publik memasangnya di atas fotonya.
 *
 * Foto peserta diolah di peramban dan tidak pernah dikirim ke server, jadi
 * yang diuji di sisi server adalah bingkainya, halaman publiknya, dan
 * penghitung pemakainya.
 */
class TwibbonTest extends TestCase
{
    use RefreshDatabase;

    /** @var list<string> */
    private array $berkas = [];

    protected function tearDown(): void
    {
        foreach ($this->berkas as $path) {
            @unlink($path);
        }

        parent::tearDown();
    }

    /**
     * Bingkai PNG pekat dengan lubang transparan di tengah.
     *
     * @param  float  $lubang  bagian sisi yang dilubangi, 0 sampai 1
     */
    private function frame(int $lebar = 1080, int $tinggi = 1080, float $lubang = 0.6, string $jenis = 'png'): string
    {
        $gambar = imagecreatetruecolor($lebar, $tinggi);
        imagesavealpha($gambar, true);
        imagealphablending($gambar, false);
        imagefill($gambar, 0, 0, imagecolorallocatealpha($gambar, 255, 216, 77, 0));

        if ($lubang > 0) {
            $tembus = imagecolorallocatealpha($gambar, 0, 0, 0, 127);
            $mx = (int) ($lebar * (1 - $lubang) / 2);
            $my = (int) ($tinggi * (1 - $lubang) / 2);
            imagefilledrectangle($gambar, $mx, $my, $lebar - $mx, $tinggi - $my, $tembus);
        }

        $path = tempnam(sys_get_temp_dir(), 'tw').'.'.$jenis;
        $jenis === 'png' ? imagepng($gambar, $path) : imagejpeg($gambar, $path);
        imagedestroy($gambar);

        return $this->berkas[] = $path;
    }

    private function admin(): User
    {
        $this->seed(RoleSeeder::class);
        $user = User::factory()->create();
        $user->assignRole('admin');
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return $user->fresh();
    }

    private function storedTwibbon(array $state = []): Twibbon
    {
        Storage::fake('public');
        Storage::disk('public')->put('twibbons/bingkai.png', file_get_contents($this->frame()));

        return Twibbon::factory()->create(['frame_path' => 'twibbons/bingkai.png', ...$state]);
    }

    // ------------------------------------------------------------ bingkai

    public function test_a_transparent_png_frame_is_accepted(): void
    {
        $bingkai = TwibbonFrame::inspect($this->frame(1080, 1350));

        $this->assertNull(TwibbonFrame::problem($this->frame(1080, 1350)));
        $this->assertSame('Potret (4:5)', $bingkai->ratioLabel);
        $this->assertEqualsWithDelta(36.0, $bingkai->transparentPercent, 3.0);
    }

    /**
     * Inti pemeriksanya: bingkai tanpa lubang menutup foto peserta seluruhnya,
     * dan baru ketahuan ketika ratusan peserta mencobanya.
     */
    public function test_a_frame_without_a_transparent_area_is_refused(): void
    {
        $this->assertStringContainsString('Bagian transparan', TwibbonFrame::problem($this->frame(lubang: 0)));
    }

    public function test_a_jpeg_frame_is_refused(): void
    {
        $this->assertStringContainsString('harus berkas PNG', TwibbonFrame::problem($this->frame(jenis: 'jpg')));
    }

    public function test_a_small_frame_is_refused(): void
    {
        $this->assertStringContainsString('terlalu kecil', TwibbonFrame::problem($this->frame(600, 600)));
    }

    public function test_an_unusual_ratio_is_refused(): void
    {
        $this->assertStringContainsString('tidak lazim', TwibbonFrame::problem($this->frame(1600, 1080)));
    }

    // -------------------------------------------------------------- panel

    public function test_an_admin_can_publish_a_twibbon(): void
    {
        Storage::fake('public');
        $this->actingAs($this->admin());

        Livewire::test(CreateTwibbon::class)
            ->fillForm([
                'frame_path' => UploadedFile::fake()->createWithContent('bingkai.png', file_get_contents($this->frame())),
                'title' => 'PKKMB 2026',
                'slug' => 'pkkmb-2026',
                'caption' => 'Siap jadi mahasiswa JGU! #PKKMBJGU2026',
                'is_published' => true,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $twibbon = Twibbon::sole();

        $this->assertSame(1080, $twibbon->frame_width);
        $this->assertSame(1080, $twibbon->frame_height);
        $this->assertGreaterThan(30, $twibbon->transparent_ratio);
        $this->assertTrue($twibbon->isOpen());
    }

    public function test_the_panel_refuses_a_frame_that_would_hide_the_photo(): void
    {
        Storage::fake('public');
        $this->actingAs($this->admin());

        Livewire::test(CreateTwibbon::class)
            ->fillForm([
                'frame_path' => UploadedFile::fake()->createWithContent('bingkai.png', file_get_contents($this->frame(lubang: 0))),
                'title' => 'PKKMB 2026',
                'slug' => 'pkkmb-2026',
            ])
            ->call('create')
            ->assertHasFormErrors(['frame_path']);

        $this->assertSame(0, Twibbon::count());
    }

    public function test_staff_may_create_but_not_delete(): void
    {
        $this->seed(RoleSeeder::class);
        $staf = User::factory()->create();
        $staf->assignRole('staff_msc');
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->actingAs($staf->fresh());

        $twibbon = $this->storedTwibbon();

        $this->assertTrue(TwibbonResource::canCreate());
        $this->assertFalse(TwibbonResource::canDelete($twibbon));
    }

    public function test_a_department_account_cannot_open_it(): void
    {
        $this->seed(RoleSeeder::class);
        $akun = User::factory()->create();
        $akun->assignRole('department');
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->actingAs($akun->fresh())->get(TwibbonResource::getUrl())->assertForbidden();
    }

    public function test_publishing_is_recorded_in_the_audit_trail(): void
    {
        $this->actingAs($this->admin());
        $twibbon = $this->storedTwibbon(['is_published' => false]);

        $twibbon->update(['is_published' => true]);

        $this->assertTrue(Activity::where('subject_type', Twibbon::class)->where('event', 'updated')->exists());
    }

    // ------------------------------------------------------------- publik

    public function test_the_public_page_offers_the_editor(): void
    {
        $twibbon = $this->storedTwibbon(['caption' => 'Siap jadi mahasiswa JGU! #PKKMBJGU2026']);

        $this->get($twibbon->publicUrl())
            ->assertOk()
            ->assertSee('id="tw-kanvas"', false)
            ->assertSee('#PKKMBJGU2026')
            ->assertSee('tidak diunggah ke server');
    }

    /**
     * Kanvas menolak mengekspor gambar dari asal lain, dan APP_URL bisa
     * berbeda dari alamat yang dibuka peserta. Bingkainya karena itu dimuat
     * dari alamat tanpa domain.
     */
    public function test_the_frame_is_loaded_from_the_same_origin(): void
    {
        $twibbon = $this->storedTwibbon();

        $this->assertStringStartsWith('/', $twibbon->frameRelativeUrl());
        $this->get($twibbon->publicUrl())->assertSee('"bingkai":"\/storage\/twibbons\/bingkai.png"', false);
    }

    public function test_a_draft_or_scheduled_twibbon_is_not_public(): void
    {
        $draf = $this->storedTwibbon(['is_published' => false]);
        $this->get($draf->publicUrl())->assertNotFound();

        $terjadwal = Twibbon::factory()->scheduled()->create(['frame_path' => 'twibbons/bingkai.png']);
        $this->get($terjadwal->publicUrl())->assertNotFound();
    }

    /**
     * Tautannya mungkin masih beredar di grup, jadi dijawab dengan
     * penjelasan, tanpa editor.
     */
    public function test_an_ended_twibbon_explains_itself(): void
    {
        $twibbon = $this->storedTwibbon(['starts_at' => now()->subWeek(), 'ends_at' => now()->subDay()]);

        $this->get($twibbon->publicUrl())
            ->assertOk()
            ->assertSee('sudah ditutup')
            ->assertDontSee('id="tw-kanvas"', false);
    }

    public function test_the_list_shows_only_open_twibbons(): void
    {
        $buka = $this->storedTwibbon(['title' => 'Twibbon Dibuka']);
        Twibbon::factory()->draft()->create(['title' => 'Twibbon Draf', 'frame_path' => 'twibbons/bingkai.png']);
        Twibbon::factory()->ended()->create(['title' => 'Twibbon Lama', 'frame_path' => 'twibbons/bingkai.png']);

        $this->get(route('twibbon.index'))
            ->assertOk()
            ->assertSee($buka->title)
            ->assertDontSee('Twibbon Draf')
            ->assertDontSee('Twibbon Lama');
    }

    public function test_using_a_twibbon_is_counted(): void
    {
        $twibbon = $this->storedTwibbon();

        $this->post(route('twibbon.used', $twibbon->slug))->assertNoContent();
        $this->post(route('twibbon.used', $twibbon->slug))->assertNoContent();

        $this->assertSame(2, $twibbon->fresh()->uses_count);
    }

    public function test_a_closed_twibbon_is_not_counted(): void
    {
        $twibbon = $this->storedTwibbon(['starts_at' => now()->subWeek(), 'ends_at' => now()->subDay()]);

        $this->post(route('twibbon.used', $twibbon->slug))->assertNotFound();
        $this->assertSame(0, $twibbon->fresh()->uses_count);
    }

    public function test_open_twibbons_reach_the_sitemap_and_the_bio(): void
    {
        $twibbon = $this->storedTwibbon();
        $draf = Twibbon::factory()->draft()->create(['frame_path' => 'twibbons/bingkai.png']);

        $this->get(route('sitemap'))
            ->assertSee($twibbon->publicUrl(), false)
            ->assertDontSee($draf->publicUrl(), false);

        $this->get(route('bio'))->assertSee('Twibbon kegiatan');
    }

    public function test_the_bio_stays_quiet_without_an_open_twibbon(): void
    {
        $this->get(route('bio'))->assertDontSee('Twibbon kegiatan');
    }
}

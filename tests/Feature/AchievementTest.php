<?php

namespace Tests\Feature;

use App\Enums\AchievementCategory;
use App\Filament\Resources\AchievementResource;
use App\Models\Achievement;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Prestasi MSC: penghargaan, sertifikat, dan piala.
 *
 * Dipajang di beranda sebagai jawaban atas pertanyaan yang muncul tepat
 * setelah orang membaca cara kerjanya: seberapa bisa dipercaya yang akan
 * memproses pengajuan saya.
 */
class AchievementTest extends TestCase
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

    // ───────────────────────────────────────────────────────── di beranda

    public function test_an_active_achievement_appears_on_the_landing_page(): void
    {
        $prestasi = Achievement::factory()->create([
            'title' => 'Juara 1 Lomba Film Pendek Nasional',
            'awarded_by' => 'LLDIKTI Wilayah III',
            'level' => 'Nasional',
        ]);

        $this->get(route('landing'))
            ->assertOk()
            ->assertSee('Penghargaan yang pernah kami bawa pulang')
            ->assertSee($prestasi->title)
            ->assertSee('LLDIKTI Wilayah III')
            ->assertSee('Nasional');
    }

    /**
     * Menyembunyikan harus benar-benar menyembunyikan. Admin memakainya saat
     * satu entri keliru terunggah, dan saat itu ia harus hilang seketika.
     */
    public function test_a_hidden_achievement_stays_off_the_landing_page(): void
    {
        $prestasi = Achievement::factory()->inactive()->create([
            'title' => 'Prestasi Yang Disembunyikan',
        ]);

        $this->get(route('landing'))
            ->assertOk()
            ->assertDontSee($prestasi->title);
    }

    /**
     * Seluruh bagiannya menghilang saat belum ada isinya, bukan menyisakan
     * judul kosong yang membuat MSC terlihat belum pernah meraih apa pun.
     */
    public function test_the_whole_section_disappears_when_there_is_nothing_to_show(): void
    {
        $this->get(route('landing'))
            ->assertOk()
            ->assertDontSee('Penghargaan yang pernah kami bawa pulang');
    }

    /**
     * Urutan dipegang admin lewat sort_order; yang dibiarkan sama diurutkan
     * dari yang terbaru, bukan menurut id.
     */
    public function test_the_order_follows_what_the_admin_set(): void
    {
        $kedua = Achievement::factory()->create(['title' => 'Kedua', 'sort_order' => 2]);
        $pertama = Achievement::factory()->create(['title' => 'Pertama', 'sort_order' => 1]);

        $urutan = Achievement::active()->ordered()->pluck('title')->all();

        $this->assertSame(['Pertama', 'Kedua'], $urutan);
        $this->assertTrue($pertama->isNot($kedua));
    }

    public function test_achievements_with_the_same_order_show_the_newest_first(): void
    {
        Achievement::factory()->create(['title' => 'Lama', 'sort_order' => 0, 'achieved_at' => now()->subYears(3)]);
        Achievement::factory()->create(['title' => 'Baru', 'sort_order' => 0, 'achieved_at' => now()->subMonth()]);

        $this->assertSame(['Baru', 'Lama'], Achievement::active()->ordered()->pluck('title')->all());
    }

    /**
     * Foto boleh kosong. Prestasi yang belum sempat difoto tetap layak
     * disebut, dan lambang jenisnya berdiri di tempat fotonya.
     */
    public function test_an_achievement_without_a_photo_still_shows_up(): void
    {
        $prestasi = Achievement::factory()->create([
            'title' => 'Piala Tanpa Foto',
            'category' => AchievementCategory::TROPHY->value,
            'image' => null,
        ]);

        $this->get(route('landing'))
            ->assertOk()
            ->assertSee($prestasi->title)
            // Lambang piala, diambil dari enum-nya.
            ->assertSee(AchievementCategory::TROPHY->icon(), false);
    }

    // ───────────────────────────────────────────────────────────── di panel

    public function test_the_cms_lists_creates_and_edits(): void
    {
        $this->actingAs($this->user('admin'));

        $prestasi = Achievement::factory()->create();

        $this->get(AchievementResource::getUrl('index'))->assertOk()->assertSee($prestasi->title);
        $this->get(AchievementResource::getUrl('create'))->assertOk();
        $this->get(AchievementResource::getUrl('edit', ['record' => $prestasi]))->assertOk();
    }

    public function test_creating_one_records_who_added_it(): void
    {
        $admin = $this->user('admin');

        Livewire::actingAs($admin)
            ->test(\App\Filament\Resources\AchievementResource\Pages\CreateAchievement::class)
            ->fillForm([
                'title' => 'Juara 2 Fotografi Kampus',
                'awarded_by' => 'Jakarta Global University',
                'category' => AchievementCategory::AWARD->value,
                'is_active' => true,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $prestasi = Achievement::sole();

        $this->assertSame('Juara 2 Fotografi Kampus', $prestasi->title);
        $this->assertSame($admin->id, $prestasi->created_by);
    }

    /**
     * Nama prestasinya wajib. Entri tanpa nama hanya menghasilkan kartu
     * kosong di beranda.
     */
    public function test_a_nameless_achievement_is_refused(): void
    {
        Livewire::actingAs($this->user('admin'))
            ->test(\App\Filament\Resources\AchievementResource\Pages\CreateAchievement::class)
            ->fillForm(['title' => '', 'category' => AchievementCategory::AWARD->value])
            ->call('create')
            ->assertHasFormErrors(['title']);

        $this->assertSame(0, Achievement::count());
    }

    // ────────────────────────────────────────────────────────────── haknya

    /**
     * Izin yang tidak diberikan harus benar-benar menutup aksinya, sama
     * seperti resource lain di panel ini.
     */
    public function test_a_staff_account_may_add_but_not_delete(): void
    {
        $this->actingAs($staf = $this->user('staff_msc'));

        $prestasi = Achievement::factory()->create();

        $this->assertTrue($staf->can('achievements.create'));
        $this->assertFalse($staf->can('achievements.delete'));

        $this->assertTrue(AchievementResource::canCreate());
        $this->assertTrue(AchievementResource::canEdit($prestasi));
        $this->assertFalse(AchievementResource::canDelete($prestasi));
        $this->assertFalse(AchievementResource::canDeleteAny());
    }

    public function test_an_account_without_the_permission_cannot_reach_it(): void
    {
        $this->seed(RoleSeeder::class);

        $orang = User::factory()->create();
        $orang->givePermissionTo('panel.access');
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->actingAs($orang->fresh());

        $this->assertFalse(AchievementResource::canViewAny());
        $this->assertFalse(AchievementResource::canCreate());
    }
}

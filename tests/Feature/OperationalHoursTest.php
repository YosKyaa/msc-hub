<?php

namespace Tests\Feature;

use App\Filament\Pages\OperationalHoursSettings;
use App\Models\Room;
use App\Models\User;
use App\Support\AppSetting;
use App\Support\JamOperasional;
use App\Support\RequesterSession;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Jam buka layanan peminjaman.
 *
 * Angkanya dulu ditulis langsung di dalam formulir, sehingga mengubah jam buka
 * menuntut menyunting berkas tampilan dan merilis ulang.
 */
class OperationalHoursTest extends TestCase
{
    use RefreshDatabase;

    private function asRequester(): static
    {
        $this->withSession([RequesterSession::KEY => [
            'google_id' => '1234567890',
            'name' => 'Budi Santoso',
            'email' => 'budi@student.jgu.ac.id',
            'type' => 'student',
        ]]);

        return $this;
    }

    private function staff(): User
    {
        $this->seed(RoleSeeder::class);

        $user = User::factory()->create();
        $user->assignRole('head_msc');

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return $user->fresh();
    }

    // ───────────────────────────────────────────────── jam di formulirnya

    public function test_the_borrowing_form_shows_the_configured_hours(): void
    {
        AppSetting::set(JamOperasional::KUNCI_BUKA, '07:30');
        AppSetting::set(JamOperasional::KUNCI_TUTUP, '15:30');

        $this->asRequester()
            ->get(route('booking.inventory'))
            ->assertOk()
            ->assertSee('07:30 - 15:30');
    }

    public function test_without_a_setting_it_falls_back_to_the_usual_hours(): void
    {
        $this->assertSame('08:00 - 16:00', JamOperasional::rentang());
    }

    /**
     * Nilai setengah jadi lebih membingungkan daripada jam bawaan, jadi yang
     * tidak bisa dibaca dikembalikan ke bawaannya.
     */
    public function test_an_unreadable_setting_falls_back_instead_of_showing_rubbish(): void
    {
        AppSetting::set(JamOperasional::KUNCI_BUKA, 'pagi sekali');

        $this->assertSame(JamOperasional::BUKA_BAWAAN, JamOperasional::buka());
    }

    public function test_the_note_can_be_rewritten(): void
    {
        AppSetting::set(JamOperasional::KUNCI_CATATAN, 'Alat dikembalikan paling lambat pukul 15.00.');

        $this->asRequester()
            ->get(route('booking.inventory'))
            ->assertOk()
            ->assertSee('Alat dikembalikan paling lambat pukul 15.00.');
    }

    // ────────────────────────────────────────────────── jam ruangan sendiri

    /**
     * Jam ruangan dulu dipotong dengan substr() dari kolom yang sebenarnya
     * objek Carbon, sehingga yang tampil adalah "2026- - 2026-" alih-alih
     * jamnya.
     */
    public function test_a_room_shows_its_own_hours_not_a_sliced_date(): void
    {
        $room = Room::factory()->create([
            'name' => 'Ruang Multimedia MSC',
            'open_time' => '09:00',
            'close_time' => '17:00',
        ]);

        $response = $this->asRequester()->get(route('booking.room'))->assertOk();

        $response->assertSee('09:00 - 17:00');
        $response->assertDontSee('2026-');
        $this->assertNotNull($room->fresh());
    }

    // ───────────────────────────────────────────────────── pengaturannya

    public function test_the_settings_page_opens_and_saves(): void
    {
        Livewire::actingAs($this->staff())
            ->test(OperationalHoursSettings::class)
            ->assertSuccessful()
            ->set('data.jam_buka', '08:30')
            ->set('data.jam_tutup', '16:30')
            ->set('data.catatan', 'Mohon datang sepuluh menit lebih awal.')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame('08:30 - 16:30', JamOperasional::rentang());
        $this->assertSame('Mohon datang sepuluh menit lebih awal.', JamOperasional::catatan());
    }

    /**
     * Jam tutup sebelum jam buka menghasilkan rentang yang tidak berarti
     * apa-apa, dan peminjam yang membacanya tidak tahu harus datang kapan.
     */
    public function test_closing_before_opening_is_refused(): void
    {
        Livewire::actingAs($this->staff())
            ->test(OperationalHoursSettings::class)
            ->set('data.jam_buka', '16:00')
            ->set('data.jam_tutup', '08:00')
            ->call('save')
            ->assertHasErrors('data.jam_tutup');
    }

    public function test_a_malformed_time_is_refused(): void
    {
        Livewire::actingAs($this->staff())
            ->test(OperationalHoursSettings::class)
            ->set('data.jam_buka', '8 pagi')
            ->set('data.jam_tutup', '16:00')
            ->call('save')
            ->assertHasErrors('data.jam_buka');
    }

    public function test_an_account_without_the_permission_cannot_reach_it(): void
    {
        $this->seed(RoleSeeder::class);

        $orang = User::factory()->create();
        $orang->givePermissionTo('panel.access');
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->actingAs($orang->fresh());

        $this->assertFalse(OperationalHoursSettings::canAccess());
    }
}

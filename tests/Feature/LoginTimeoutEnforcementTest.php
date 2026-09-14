<?php

namespace Tests\Feature;

use App\Http\Middleware\ExpirePanelSession;
use App\Models\Room;
use App\Models\User;
use App\Support\LoginTimeout;
use App\Support\RequesterSession;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

/**
 * Penegakan batas waktu login di kedua sisi.
 *
 * Umur cookie Laravel diperpanjang setiap permintaan, jadi tanpa penjagaan
 * ini sebuah tab yang terus dipakai tidak pernah keluar dengan sendirinya.
 */
class LoginTimeoutEnforcementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'msc.session.requester.idle_timeout' => 480,
            'msc.session.requester.absolute_timeout' => 720,
            'msc.session.panel.idle_timeout' => 120,
            'msc.session.panel.absolute_timeout' => 480,
        ]);

        Room::create([
            'name' => 'Ruang Multimedia MSC',
            'location' => 'Gedung A Lantai 3',
            'capacity' => 20,
            'is_active' => true,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function requester(string $authenticatedAt, string $lastActiveAt): array
    {
        return [
            'google_id' => '1234567890',
            'name' => 'Budi Santoso',
            'email' => 'budi@student.jgu.ac.id',
            'type' => 'student',
            'authenticated_at' => $authenticatedAt,
            'last_active_at' => $lastActiveAt,
        ];
    }

    // -------------------------------------------------------- sisi peminjam

    public function test_an_active_requester_keeps_browsing(): void
    {
        $this->withSession([RequesterSession::KEY => $this->requester(
            Carbon::now()->subHour()->toIso8601String(),
            Carbon::now()->subMinutes(5)->toIso8601String(),
        )])->get(route('booking.room'))->assertOk();
    }

    public function test_an_idle_requester_is_signed_out(): void
    {
        $this->withSession([RequesterSession::KEY => $this->requester(
            Carbon::now()->subHours(9)->toIso8601String(),
            Carbon::now()->subHours(9)->toIso8601String(),
        )])->get(route('booking.room'))
            ->assertRedirect()
            ->assertSessionMissing(RequesterSession::KEY);
    }

    /**
     * Inti perlindungannya: peminjam yang terus aktif pun tetap dikeluarkan
     * setelah batas keras, bukan hanya saat menganggur.
     */
    public function test_a_continuously_active_requester_still_hits_the_hard_limit(): void
    {
        $this->withSession([RequesterSession::KEY => $this->requester(
            Carbon::now()->subHours(13)->toIso8601String(),
            Carbon::now()->subMinute()->toIso8601String(),
        )])->get(route('booking.room'))
            ->assertRedirect()
            ->assertSessionMissing(RequesterSession::KEY);
    }

    public function test_the_reason_waits_for_the_requester_to_come_back(): void
    {
        $this->withSession([RequesterSession::KEY => $this->requester(
            Carbon::now()->subHours(9)->toIso8601String(),
            Carbon::now()->subHours(9)->toIso8601String(),
        )])->get(route('booking.room'));

        // Peminjam dilempar ke Google, jadi alasannya dititipkan sampai ia
        // kembali — bukan hilang di tengah pengalihan.
        $this->assertStringContainsString(
            'tidak ada aktivitas',
            session(RequesterSession::NOTICE_KEY),
        );
    }

    public function test_browsing_pushes_the_inactivity_deadline_back(): void
    {
        $this->withSession([RequesterSession::KEY => $this->requester(
            Carbon::now()->subHour()->toIso8601String(),
            Carbon::now()->subHours(8)->addMinutes(5)->toIso8601String(),
        )])->get(route('booking.room'))->assertOk();

        $this->assertTrue(
            Carbon::parse(session(RequesterSession::KEY)['last_active_at'])->isAfter(Carbon::now()->subMinute()),
        );
    }

    /**
     * Sesi yang dibuat versi sebelumnya belum menyimpan `last_active_at`;
     * peminjam yang sedang aktif tidak boleh terlempar saat rilis.
     */
    public function test_a_session_from_an_older_release_is_stamped_instead_of_dropped(): void
    {
        $this->withSession([RequesterSession::KEY => [
            'google_id' => '1234567890',
            'name' => 'Budi Santoso',
            'email' => 'budi@student.jgu.ac.id',
            'type' => 'student',
        ]])->get(route('booking.room'))->assertOk();

        $this->assertNotNull(session(RequesterSession::KEY)['last_active_at']);
    }

    /**
     * Peserta check-in di pagi hari lalu check-out sore hari tanpa membuka
     * apa pun di antaranya. Batas peminjam harus memuat rentang itu, kalau
     * tidak absensi keluar jadi gagal justru pada acara sehari penuh.
     */
    public function test_a_full_event_day_fits_inside_the_requester_limit(): void
    {
        $this->withSession([RequesterSession::KEY => $this->requester(
            Carbon::now()->subHours(6)->toIso8601String(),
            Carbon::now()->subHours(6)->toIso8601String(),
        )])->get(route('booking.room'))->assertOk();
    }

    /**
     * Panel jauh lebih ketat daripada peminjam: perangkat yang ditinggal di
     * ruang kerja tidak boleh tetap bisa menyetujui peminjaman.
     */
    public function test_the_panel_expires_sooner_than_the_public_portal(): void
    {
        $panel = LoginTimeout::forPanel();
        $requester = LoginTimeout::forRequester();

        $this->assertLessThan($requester->idleMinutes, $panel->idleMinutes);
        $this->assertLessThan($requester->absoluteMinutes, $panel->absoluteMinutes);
    }

    // ------------------------------------------------------------ sisi panel

    private function panelUser(): User
    {
        $this->seed(RoleSeeder::class);

        $user = User::factory()->create();
        $user->assignRole('admin');

        return $user;
    }

    public function test_an_expired_panel_session_is_signed_out(): void
    {
        $this->actingAs($this->panelUser())
            ->withSession([
                ExpirePanelSession::LOGGED_IN_AT => Carbon::now()->subHours(9)->toIso8601String(),
                ExpirePanelSession::LAST_SEEN_AT => Carbon::now()->subMinute()->toIso8601String(),
            ])
            ->get(route('filament.admin.pages.dashboard'))
            ->assertRedirect(route('filament.admin.auth.login'));

        $this->assertFalse(Auth::check());
    }

    public function test_an_active_panel_session_is_left_alone(): void
    {
        $this->actingAs($this->panelUser())
            ->withSession([
                ExpirePanelSession::LOGGED_IN_AT => Carbon::now()->subHour()->toIso8601String(),
                ExpirePanelSession::LAST_SEEN_AT => Carbon::now()->subMinute()->toIso8601String(),
            ])
            ->get(route('filament.admin.pages.dashboard'))
            ->assertSuccessful();
    }
}

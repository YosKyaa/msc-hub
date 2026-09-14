<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\GoogleLogin;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Laravel\Socialite\Contracts\User as SocialiteUser;
use Laravel\Socialite\Facades\Socialite;
use Mockery;
use Tests\TestCase;

/**
 * Login lewat Google, untuk panel maupun peminjam.
 *
 * Google hanya boleh memanggil balik satu alamat, dan alamat itu adalah
 * callback milik peminjam. Login panel karena itu dibedakan lewat penanda di
 * sesi — bagian yang paling mudah putus dan paling sulit dilihat gejalanya:
 * admin mendarat di halaman peminjam dengan pesan galat yang bukan miliknya,
 * sementara sebab aslinya tidak tercatat di mana pun.
 */
class GoogleLoginTest extends TestCase
{
    use RefreshDatabase;

    private function googleReturns(string $email, string $name = 'Admin MSC'): void
    {
        $googleUser = Mockery::mock(SocialiteUser::class);
        $googleUser->shouldReceive('getId')->andReturn('1234567890');
        $googleUser->shouldReceive('getEmail')->andReturn($email);
        $googleUser->shouldReceive('getName')->andReturn($name);
        $googleUser->shouldReceive('getAvatar')->andReturn(null);

        $driver = Mockery::mock();
        $driver->shouldReceive('scopes')->andReturnSelf();
        $driver->shouldReceive('with')->andReturnSelf();
        $driver->shouldReceive('stateless')->andReturnSelf();
        $driver->shouldReceive('redirect')->andReturn(redirect('https://accounts.google.com/o/oauth2/auth'));
        $driver->shouldReceive('user')->andReturn($googleUser);

        Socialite::shouldReceive('driver')->with('google')->andReturn($driver);
    }

    private function panelUser(string $email): User
    {
        $this->seed(RoleSeeder::class);

        $user = User::factory()->create(['email' => $email]);
        $user->assignRole('admin');

        return $user;
    }

    /**
     * Perjalanan utuh: klik tombol di halaman login panel, lalu kembali dari
     * Google ke alamat callback yang benar-benar terdaftar.
     */
    public function test_an_admin_signs_in_through_google_and_lands_on_the_panel(): void
    {
        $this->panelUser('admin@jgu.ac.id');
        $this->googleReturns('admin@jgu.ac.id');

        $this->get(route('admin.google.redirect'))->assertRedirect();

        $this->get(route('auth.google.callback', ['code' => 'kode-uji', 'state' => 'state-uji']))
            ->assertRedirect('/panel')
            ->assertSessionHasNoErrors();

        $this->assertTrue(Auth::check());
    }

    /**
     * Penanda sesi menentukan callback mana yang dipakai. Kalau hilang, admin
     * jatuh ke jalur peminjam dan melihat pesan yang bukan untuknya.
     */
    public function test_the_admin_marker_survives_the_trip_to_google(): void
    {
        $this->get(route('admin.google.redirect'));

        $this->assertSame('admin', session('google_auth_type'));
    }

    public function test_an_email_outside_the_allowed_domain_is_refused_at_the_panel(): void
    {
        $this->googleReturns('budi@student.jgu.ac.id');

        $this->get(route('admin.google.redirect'));

        $this->get(route('auth.google.callback', ['code' => 'kode-uji']))
            ->assertRedirect(route('filament.admin.auth.login'));

        $this->assertFalse(Auth::check());
    }

    public function test_an_unregistered_account_is_told_so_on_the_panel_login(): void
    {
        $this->seed(RoleSeeder::class);
        $this->googleReturns('belum-terdaftar@jgu.ac.id');

        $this->get(route('admin.google.redirect'));

        $this->get(route('auth.google.callback', ['code' => 'kode-uji']))
            ->assertRedirect(route('filament.admin.auth.login'))
            ->assertSessionHas('error');

        $this->assertFalse(Auth::check());
    }

    // --------------------------------------------------------- saat gagal

    private function googleThrows(\Throwable $exception): void
    {
        $driver = Mockery::mock();
        $driver->shouldReceive('scopes')->andReturnSelf();
        $driver->shouldReceive('with')->andReturnSelf();
        $driver->shouldReceive('redirect')->andReturn(redirect('https://accounts.google.com/o/oauth2/auth'));
        $driver->shouldReceive('user')->andThrow($exception);

        Socialite::shouldReceive('driver')->with('google')->andReturn($driver);
    }

    /**
     * Bug yang dilaporkan: admin yang gagal masuk mendarat di portal peminjam
     * dengan pesan galat yang bukan miliknya, bukan di halaman login panel.
     */
    public function test_a_failed_panel_login_never_lands_on_the_public_portal(): void
    {
        $this->googleThrows(new \RuntimeException('Google menolak koneksi'));

        $this->get(route('admin.google.redirect'));

        $this->get(route('auth.google.callback', ['code' => 'kode-uji']))
            ->assertRedirect(route('filament.admin.auth.login'))
            ->assertSessionHas('error');
    }

    /**
     * Dulu seluruh kegagalan ditelan tanpa satu baris log pun, sehingga
     * penyebabnya mustahil dilacak.
     */
    public function test_the_real_reason_for_a_failure_is_written_to_the_log(): void
    {
        Log::spy();

        $this->googleThrows(new \RuntimeException('Google menolak koneksi'));

        $this->get(route('admin.google.redirect'));
        $this->get(route('auth.google.callback', ['code' => 'kode-uji']));

        Log::shouldHaveReceived('warning')
            ->withArgs(fn (string $message, array $context) => $message === 'Login Google gagal.'
                && ($context['alur'] ?? null) === 'panel'
                && $context['exception'] instanceof \RuntimeException);
    }

    /**
     * Sesi yang terputus di tengah jalan terbaca sebagai state yang tidak
     * dikenali; pesannya harus menyebut sebabnya, bukan sekadar "coba lagi".
     */
    public function test_a_broken_session_is_explained_rather_than_shrugged_off(): void
    {
        $this->googleThrows(new \Laravel\Socialite\Two\InvalidStateException);

        $this->get(route('admin.google.redirect'));

        $this->get(route('auth.google.callback', ['code' => 'kode-uji']))
            ->assertRedirect(route('filament.admin.auth.login'));

        $this->assertStringContainsString('cookie', session('error'));
    }

    // ------------------------------------------- batas waktu satu percobaan

    public function test_a_login_left_hanging_for_too_long_is_refused(): void
    {
        config(['msc.session.login_attempt_timeout' => 15]);

        $this->googleReturns('admin@jgu.ac.id');
        $this->get(route('admin.google.redirect'));

        $this->travel(16)->minutes();

        $this->get(route('auth.google.callback', ['code' => 'kode-uji']))
            ->assertRedirect(route('filament.admin.auth.login'));

        $this->assertStringContainsString('15 menit', session('error'));
        $this->assertFalse(Auth::check());
    }

    public function test_a_login_finished_within_the_window_still_works(): void
    {
        config(['msc.session.login_attempt_timeout' => 15]);

        $this->panelUser('admin@jgu.ac.id');
        $this->googleReturns('admin@jgu.ac.id');
        $this->get(route('admin.google.redirect'));

        $this->travel(14)->minutes();

        $this->get(route('auth.google.callback', ['code' => 'kode-uji']))->assertRedirect('/panel');

        $this->assertTrue(Auth::check());
    }

    /**
     * Callback tanpa jejak awal berarti sesinya hilang; diperlakukan sebagai
     * percobaan kedaluwarsa ketimbang diteruskan ke Google.
     */
    public function test_a_callback_without_a_starting_point_is_refused(): void
    {
        $this->withSession([GoogleLogin::TYPE_KEY => GoogleLogin::ADMIN]);

        $this->get(route('auth.google.callback', ['code' => 'kode-uji']))
            ->assertRedirect(route('filament.admin.auth.login'))
            ->assertSessionHas('error');
    }

    public function test_the_markers_are_cleared_once_the_trip_is_over(): void
    {
        $this->panelUser('admin@jgu.ac.id');
        $this->googleReturns('admin@jgu.ac.id');

        $this->get(route('admin.google.redirect'));
        $this->get(route('auth.google.callback', ['code' => 'kode-uji']));

        $this->assertNull(session(GoogleLogin::TYPE_KEY));
        $this->assertNull(session(GoogleLogin::STARTED_AT_KEY));
    }

    // --------------------------------------------------------- sisi peminjam

    public function test_a_requester_signs_in_and_returns_to_the_page_they_came_from(): void
    {
        $this->googleReturns('budi@student.jgu.ac.id', 'Budi Santoso');

        $this->get(route('google.redirect', ['redirect' => '/borrow/inventory']));

        $this->get(route('auth.google.callback', ['code' => 'kode-uji']))
            ->assertRedirect(url('/borrow/inventory'))
            ->assertSessionHas('success');

        $this->assertSame('budi@student.jgu.ac.id', session('requester')['email']);
    }

    /**
     * Kegagalan di sisi peminjam juga harus tercatat dan dijelaskan, bukan
     * ditelan menjadi satu kalimat yang sama untuk semua sebab.
     */
    public function test_a_failed_requester_login_is_logged_and_explained(): void
    {
        Log::spy();

        $this->googleThrows(new \Laravel\Socialite\Two\InvalidStateException);

        $this->get(route('google.redirect'));
        $this->get(route('auth.google.callback', ['code' => 'kode-uji']))->assertRedirect();

        Log::shouldHaveReceived('warning')
            ->withArgs(fn (string $message, array $context) => $message === 'Login Google gagal.'
                && ($context['alur'] ?? null) === 'peminjam');

        $this->assertStringContainsString('cookie', session('error'));
        $this->assertNull(session('requester'));
    }

    public function test_a_requester_login_left_hanging_for_too_long_is_refused(): void
    {
        config(['msc.session.login_attempt_timeout' => 15]);

        $this->googleReturns('budi@student.jgu.ac.id');
        $this->get(route('google.redirect'));

        $this->travel(16)->minutes();

        $this->get(route('auth.google.callback', ['code' => 'kode-uji']))->assertRedirect();

        $this->assertStringContainsString('15 menit', session('error'));
        $this->assertNull(session('requester'));
    }

    /**
     * Parameter `state` memang tersimpan di sesi yang sama, jadi memeriksanya
     * menutup jalan bagi permintaan callback yang dipalsukan dari luar.
     */
    public function test_the_panel_callback_verifies_the_state_parameter(): void
    {
        $this->panelUser('admin@jgu.ac.id');

        $googleUser = Mockery::mock(SocialiteUser::class);
        $googleUser->shouldReceive('getId')->andReturn('1');
        $googleUser->shouldReceive('getEmail')->andReturn('admin@jgu.ac.id');
        $googleUser->shouldReceive('getName')->andReturn('Admin MSC');
        $googleUser->shouldReceive('getAvatar')->andReturn(null);

        $driver = Mockery::mock();
        $driver->shouldReceive('scopes')->andReturnSelf();
        $driver->shouldReceive('with')->andReturnSelf();
        $driver->shouldReceive('redirect')->andReturn(redirect('https://accounts.google.com/o/oauth2/auth'));
        $driver->shouldNotReceive('stateless');
        $driver->shouldReceive('user')->andReturn($googleUser);

        Socialite::shouldReceive('driver')->with('google')->andReturn($driver);

        $this->get(route('admin.google.redirect'));
        $this->get(route('auth.google.callback', ['code' => 'kode-uji']))->assertRedirect('/panel');
    }
}

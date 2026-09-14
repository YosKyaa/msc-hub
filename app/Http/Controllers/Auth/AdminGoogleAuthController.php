<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\GoogleLogin;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\Facades\Socialite;
use Throwable;

class AdminGoogleAuthController extends Controller
{
    protected array $allowedDomains = [
        'jgu.ac.id',
    ];

    public function redirect()
    {
        GoogleLogin::begin(GoogleLogin::ADMIN);

        return Socialite::driver('google')
            ->scopes(['openid', 'profile', 'email'])
            ->with(['prompt' => 'select_account'])
            ->redirect();
    }

    public function callback(Request $request)
    {
        if (GoogleLogin::attemptExpired()) {
            return $this->refuse(GoogleLogin::expiredMessage());
        }

        try {
            // Bukan stateless: parameter `state` yang ditaruh saat redirect
            // memang tersimpan di sesi yang sama, dan memeriksanya menutup
            // jalan bagi permintaan callback yang dipalsukan dari luar.
            $googleUser = Socialite::driver('google')->user();
        } catch (Throwable $exception) {
            return $this->refuse(GoogleLogin::report($exception, 'panel'));
        }

        $email = $googleUser->getEmail();

        if (! $email) {
            return $this->refuse('Email tidak ditemukan dari akun Google.');
        }

        $domain = strtolower(substr(strrchr($email, '@'), 1));

        if (! in_array($domain, $this->allowedDomains, true)) {
            return $this->refuse('Panel hanya dapat diakses dengan email @jgu.ac.id.');
        }

        $user = User::where('email', $email)->first();

        if ($user === null) {
            return $this->refuse('Akun '.$email.' belum terdaftar. Hubungi administrator MSC.');
        }

        if (! $user->hasPermissionTo('panel.access')) {
            return $this->refuse('Akun Anda terdaftar tetapi belum diberi akses panel.');
        }

        Auth::login($user, true);

        // Sesi diperbarui setelah login supaya sesi lama tidak bisa dipakai
        // ulang, dan penanda perjalanan tidak tertinggal untuk login berikutnya.
        $request->session()->regenerate();
        GoogleLogin::finish();

        return redirect()->intended('/panel');
    }

    /**
     * Setiap penolakan berakhir di halaman login panel, bukan di halaman
     * peminjam — dulu admin yang gagal masuk mendarat di portal publik dengan
     * pesan galat yang bukan miliknya.
     */
    private function refuse(string $message): RedirectResponse
    {
        GoogleLogin::finish();

        return redirect()->route('filament.admin.auth.login')->with('error', $message);
    }
}

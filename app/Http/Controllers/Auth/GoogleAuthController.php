<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Support\SafeRedirect;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Session;
use Laravel\Socialite\Facades\Socialite;

class GoogleAuthController extends Controller
{
    protected array $allowedDomains = [
        'jgu.ac.id',
        'student.jgu.ac.id',
    ];

    public function redirect(Request $request)
    {
        Session::put('google_auth_redirect', $this->intendedUrl($request->input('redirect')));

        return Socialite::driver('google')
            ->scopes(['openid', 'profile', 'email'])
            ->redirect();
    }

    public function callback(Request $request)
    {
        // Check if this is an admin login
        if (session('google_auth_type') === 'admin') {
            Session::forget('google_auth_type');

            return app(AdminGoogleAuthController::class)->callback($request);
        }

        // Tetap di halaman yang memicu login supaya pesan error terlihat di konteksnya.
        $intended = $this->intendedUrl(Session::get('google_auth_redirect'));

        try {
            $googleUser = Socialite::driver('google')->user();
        } catch (\Exception $e) {
            return redirect($intended)->with('error', 'Gagal login dengan Google. Silakan coba lagi.');
        }

        $email = $googleUser->getEmail();

        if (! $email) {
            return redirect($intended)->with('error', 'Email tidak ditemukan dari akun Google.');
        }

        $domain = strtolower(substr(strrchr($email, '@'), 1));

        if (! in_array($domain, $this->allowedDomains, true)) {
            return redirect($intended)->with('error', 'Hanya email @jgu.ac.id atau @student.jgu.ac.id yang diperbolehkan.');
        }

        Session::put('requester', [
            'google_id' => $googleUser->getId(),
            'name' => $googleUser->getName(),
            'email' => strtolower($email),
            'avatar' => $googleUser->getAvatar(),
            'type' => str_contains($domain, 'student') ? 'student' : 'lecturer',
            'authenticated_at' => now()->toIso8601String(),
        ]);

        Session::forget('google_auth_redirect');

        return redirect($intended)
            ->with('success', 'Login berhasil! Selamat datang, '.$googleUser->getName());
    }

    public function logout(Request $request)
    {
        Session::forget('requester');

        return redirect($this->intendedUrl($request->input('redirect')))
            ->with('success', 'Anda telah logout.');
    }

    /**
     * Hanya izinkan tujuan internal — mencegah open redirect lewat ?redirect=.
     */
    protected function intendedUrl(?string $target): string
    {
        return SafeRedirect::sanitize($target, route('request.content'));
    }
}

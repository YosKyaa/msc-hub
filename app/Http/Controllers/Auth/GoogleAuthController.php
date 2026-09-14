<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Support\GoogleLogin;
use App\Support\RequesterSession;
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
        GoogleLogin::begin();

        return Socialite::driver('google')
            ->scopes(['openid', 'profile', 'email'])
            ->redirect();
    }

    public function callback(Request $request)
    {
        // Google hanya memanggil balik satu alamat, jadi perjalanan menuju
        // panel dibedakan lewat penanda di sesi dan diserahkan seluruhnya —
        // termasuk penanganan galatnya — ke controller panel.
        if (GoogleLogin::isForPanel()) {
            return app(AdminGoogleAuthController::class)->callback($request);
        }

        // Tetap di halaman yang memicu login supaya pesan error terlihat di konteksnya.
        $intended = $this->intendedUrl(Session::get('google_auth_redirect'));

        if (GoogleLogin::attemptExpired()) {
            GoogleLogin::finish();

            return redirect($intended)->with('error', GoogleLogin::expiredMessage());
        }

        try {
            $googleUser = Socialite::driver('google')->user();
        } catch (\Throwable $exception) {
            GoogleLogin::finish();

            return redirect($intended)->with('error', GoogleLogin::report($exception, 'peminjam'));
        }

        $email = $googleUser->getEmail();

        if (! $email) {
            return redirect($intended)->with('error', 'Email tidak ditemukan dari akun Google.');
        }

        $domain = strtolower(substr(strrchr($email, '@'), 1));

        if (! in_array($domain, $this->allowedDomains, true)) {
            return redirect($intended)->with('error', 'Hanya email @jgu.ac.id atau @student.jgu.ac.id yang diperbolehkan.');
        }

        RequesterSession::start([
            'google_id' => $googleUser->getId(),
            'name' => $googleUser->getName(),
            'email' => strtolower($email),
            'avatar' => $googleUser->getAvatar(),
            'type' => str_contains($domain, 'student') ? 'student' : 'lecturer',
        ]);

        Session::forget('google_auth_redirect');
        GoogleLogin::finish();

        // Sesi yang berakhir melempar peminjam ke Google tanpa sempat
        // menampilkan alasannya; pesan itu disampaikan sekarang.
        $notice = Session::pull(RequesterSession::NOTICE_KEY);

        return redirect($intended)->with('success', $notice
            ? $notice.' Anda masuk kembali sebagai '.$googleUser->getName().'.'
            : 'Login berhasil! Selamat datang, '.$googleUser->getName());
    }

    public function logout(Request $request)
    {
        RequesterSession::forget();
        Session::forget(RequesterSession::NOTICE_KEY);

        return redirect($this->intendedUrl($request->input('redirect')))
            ->with('success', 'Anda telah logout.');
    }

    /**
     * Hanya izinkan tujuan internal — mencegah open redirect lewat ?redirect=.
     */
    protected function intendedUrl(?string $target): string
    {
        return SafeRedirect::sanitize($target, route('requester.dashboard'));
    }
}

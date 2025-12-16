<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
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
        // Store intended URL if provided
        if ($request->has('redirect')) {
            Session::put('google_auth_redirect', $request->input('redirect'));
        }

        return Socialite::driver('google')
            ->scopes(['openid', 'profile', 'email'])
            ->redirect();
    }

    public function callback(Request $request)
    {
        try {
            $googleUser = Socialite::driver('google')->user();
        } catch (\Exception $e) {
            return redirect()->route('request.content')
                ->with('error', 'Gagal login dengan Google. Silakan coba lagi.');
        }

        // Verify email is present
        if (!$googleUser->getEmail()) {
            return redirect()->route('request.content')
                ->with('error', 'Email tidak ditemukan dari akun Google.');
        }

        // Verify email domain
        $email = $googleUser->getEmail();
        $domain = substr(strrchr($email, "@"), 1);

        if (!in_array($domain, $this->allowedDomains)) {
            return redirect()->route('request.content')
                ->with('error', 'Hanya email @jgu.ac.id atau @student.jgu.ac.id yang diperbolehkan.');
        }

        // Determine requester type from domain
        $requesterType = str_contains($domain, 'student') ? 'student' : 'lecturer';

        // Store requester info in session
        Session::put('requester', [
            'google_id' => $googleUser->getId(),
            'name' => $googleUser->getName(),
            'email' => $email,
            'avatar' => $googleUser->getAvatar(),
            'type' => $requesterType,
            'authenticated_at' => now()->toIso8601String(),
        ]);

        // Redirect to intended URL or default
        $redirect = Session::pull('google_auth_redirect', route('request.content'));

        return redirect($redirect)
            ->with('success', 'Login berhasil! Selamat datang, ' . $googleUser->getName());
    }

    public function logout(Request $request)
    {
        Session::forget('requester');

        return redirect()->route('request.content')
            ->with('success', 'Anda telah logout.');
    }
}

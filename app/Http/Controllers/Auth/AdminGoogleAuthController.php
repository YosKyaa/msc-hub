<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\Facades\Socialite;

class AdminGoogleAuthController extends Controller
{
    protected array $allowedDomains = [
        'jgu.ac.id',
    ];

    public function redirect()
    {
        // Set session flag for admin login
        session(['google_auth_type' => 'admin']);
        
        return Socialite::driver('google')
            ->scopes(['openid', 'profile', 'email'])
            ->with(['prompt' => 'select_account'])
            ->redirect();
    }

    public function callback(Request $request)
    {
        try {
            $googleUser = Socialite::driver('google')->user();
        } catch (\Exception $e) {
            return redirect()->route('filament.admin.auth.login')
                ->with('error', 'Gagal login dengan Google. Silakan coba lagi.');
        }

        if (!$googleUser->getEmail()) {
            return redirect()->route('filament.admin.auth.login')
                ->with('error', 'Email tidak ditemukan dari akun Google.');
        }

        $email = $googleUser->getEmail();
        $domain = substr(strrchr($email, "@"), 1);

        if (!in_array($domain, $this->allowedDomains)) {
            return redirect()->route('filament.admin.auth.login')
                ->with('error', 'Hanya email @jgu.ac.id yang diperbolehkan untuk akses admin.');
        }

        // Find existing user by email
        $user = User::where('email', $email)->first();

        if (!$user) {
            return redirect()->route('filament.admin.auth.login')
                ->with('error', 'Akun Anda belum terdaftar. Hubungi administrator.');
        }

        // Check if user has admin access
        if (!$user->hasAnyRole(['admin', 'staff_msc', 'head_msc'])) {
            return redirect()->route('filament.admin.auth.login')
                ->with('error', 'Anda tidak memiliki akses ke panel admin.');
        }

        // Login the user
        Auth::login($user, true);

        return redirect()->to('/admin');
    }
}

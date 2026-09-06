<?php

namespace App\Http\Middleware;

use App\Support\LoginTimeout;
use Closure;
use Filament\Notifications\Notification;
use Illuminate\Contracts\Session\Session;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Mengakhiri sesi panel yang sudah lewat batas waktu.
 *
 * Panel dipakai untuk menyetujui peminjaman dan menerbitkan sertifikat, jadi
 * perangkat yang ditinggal tidak boleh tetap masuk sampai kapan pun.
 */
class ExpirePanelSession
{
    public const LOGGED_IN_AT = 'msc_logged_in_at';

    public const LAST_SEEN_AT = 'msc_last_seen_at';

    public function handle(Request $request, Closure $next): Response
    {
        if (! Auth::check()) {
            return $next($request);
        }

        $session = $request->session();
        $timeout = LoginTimeout::forPanel();

        $reason = $timeout->expiryReason(
            $this->stamp($session, self::LOGGED_IN_AT),
            $this->stamp($session, self::LAST_SEEN_AT),
        );

        if ($reason !== null) {
            return $this->signOut($session, $timeout->message($reason));
        }

        $session->put(self::LAST_SEEN_AT, Carbon::now()->toIso8601String());

        return $next($request);
    }

    private function signOut(Session $session, string $message): Response
    {
        Auth::logout();

        $session->invalidate();
        $session->regenerateToken();

        // Dikirim setelah sesi dibarui supaya tersimpan di sesi yang baru,
        // sehingga pesannya masih terlihat di halaman login.
        Notification::make()
            ->title($message)
            ->danger()
            ->persistent()
            ->send();

        return redirect()->guest(route('filament.admin.auth.login'));
    }

    /**
     * Jejak waktu sesi, dicatat sekarang bila belum ada, agar sesi yang
     * sudah berjalan sebelum rilis ini tidak langsung terputus.
     */
    private function stamp(Session $session, string $key): Carbon
    {
        $value = $session->get($key);

        if (is_string($value) && $value !== '') {
            return Carbon::parse($value);
        }

        $now = Carbon::now();
        $session->put($key, $now->toIso8601String());

        return $now;
    }
}

<?php

namespace App\Http\Middleware;

use App\Support\LoginTimeout;
use App\Support\RequesterSession;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Session;
use Symfony\Component\HttpFoundation\Response;

/**
 * Mengakhiri sesi peminjam yang sudah lewat batas waktu.
 *
 * Tanpa ini, sesi peminjam hanya ikut umur cookie Laravel yang diperpanjang
 * setiap permintaan — artinya tab yang terus dipakai tidak pernah keluar.
 */
class ExpireStaleLogins
{
    public function handle(Request $request, Closure $next): Response
    {
        $this->expireRequester();

        RequesterSession::touch();

        return $next($request);
    }

    private function expireRequester(): void
    {
        $requester = RequesterSession::get();

        if ($requester === null) {
            return;
        }

        $timeout = LoginTimeout::forRequester();

        $reason = $timeout->expiryReason(
            RequesterSession::stamp($requester, 'authenticated_at'),
            RequesterSession::stamp($requester, 'last_active_at'),
        );

        if ($reason === null) {
            return;
        }

        RequesterSession::forget();

        // Peminjam yang membuka halaman berlogin langsung dilempar ke Google,
        // jadi pesannya dititipkan sampai ia kembali dari sana.
        Session::put(RequesterSession::NOTICE_KEY, $timeout->message($reason));
    }
}

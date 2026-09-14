<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Support\RequesterSession;
use App\Support\SafeRedirect;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Satu pintu masuk untuk seluruh sistem.
 *
 * Beranda dulu menyodorkan dua tombol berdampingan — "Masuk" untuk peminjam
 * dan "Panel Admin" — sehingga pengunjung harus tahu lebih dulu dirinya
 * termasuk yang mana. Sekarang keduanya berangkat dari halaman ini.
 */
class LoginPortalController extends Controller
{
    public function show(Request $request)
    {
        // Tanpa tujuan khusus, peminjam mendarat di dasbornya — di situlah
        // pilihan layanan berada.
        $redirect = SafeRedirect::sanitize($request->input('redirect'), route('requester.dashboard'));

        // Yang sudah masuk tidak perlu disuruh memilih lagi.
        if (Auth::check()) {
            return redirect()->to('/panel');
        }

        if (RequesterSession::get() !== null) {
            return redirect()->to($redirect);
        }

        return view('auth.portal', ['redirect' => $redirect]);
    }
}

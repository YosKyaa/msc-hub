<?php

namespace App\Http\Controllers;

use App\Models\ContentRequest;
use App\Models\InventoryBooking;
use App\Models\RoomBooking;
use App\Support\RequesterSession;
use Illuminate\Http\Request;

/**
 * Halaman pertama peminjam setelah masuk.
 *
 * Dari sini ia memilih layanan dan melihat pengajuannya sendiri, tanpa perlu
 * menebak-nebak harus membuka menu yang mana.
 */
class RequesterDashboardController extends Controller
{
    /** Berapa baris terakhir yang ditampilkan per jenis pengajuan. */
    private const RECENT = 3;

    public function index(Request $request)
    {
        $requester = RequesterSession::get();

        if ($requester === null) {
            return redirect()->route('login.portal', ['redirect' => $request->fullUrl()]);
        }

        $email = $requester['email'];

        // Relasi dimuat di muka: daftar ini menyebut nama ruangan dan jumlah
        // alat tiap baris, yang tanpa ini menambah satu kueri per pengajuan.
        $roomBookings = RoomBooking::with('room')
            ->where('requester_email', $email)
            ->latest()
            ->take(self::RECENT)
            ->get();

        $inventoryBookings = InventoryBooking::with('items')
            ->where('requester_email', $email)
            ->latest()
            ->take(self::RECENT)
            ->get();

        $contentRequests = ContentRequest::where('requester_email', $email)
            ->latest()
            ->take(self::RECENT)
            ->get();

        return view('dashboard.index', [
            'requester' => $requester,
            'contentRequests' => $contentRequests,
            'roomBookings' => $roomBookings,
            'inventoryBookings' => $inventoryBookings,
            'totals' => [
                'konten' => ContentRequest::where('requester_email', $email)->count(),
                'ruangan' => RoomBooking::where('requester_email', $email)->count(),
                'alat' => InventoryBooking::where('requester_email', $email)->count(),
            ],
        ]);
    }
}

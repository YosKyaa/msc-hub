<?php

namespace App\Http\Controllers;

use App\Models\Announcement;
use App\Models\FeaturedWork;
use App\Support\LayananStatistik;

class LandingController extends Controller
{
    public function index()
    {
        $announcementsPinned = Announcement::visible()
            ->pinned()
            ->latest('published_at')
            ->limit(2)
            ->get();

        $announcementsLatest = Announcement::visible()
            ->where('is_pinned', false)
            ->latest('published_at')
            ->limit(3)
            ->get();

        $featuredWorks = FeaturedWork::active()
            ->ordered()
            ->limit(6)
            ->get();

        $requester = session('requester');

        // Dihitung dari basis data, bukan dikarang: pembacanya mahasiswa dan
        // dosen yang tahu persis seberapa besar MSC.
        $statistik = LayananStatistik::untukBeranda();

        return view('landing.msc-hub', compact(
            'announcementsPinned',
            'announcementsLatest',
            'featuredWorks',
            'requester',
            'statistik'
        ));
    }
}

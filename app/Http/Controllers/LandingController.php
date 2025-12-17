<?php

namespace App\Http\Controllers;

use App\Models\Announcement;
use App\Models\Asset;
use App\Models\FeaturedWork;

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

        return view('landing.msc-hub', compact(
            'announcementsPinned',
            'announcementsLatest',
            'featuredWorks',
            'requester'
        ));
    }
}

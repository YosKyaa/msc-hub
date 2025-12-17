<?php

namespace App\Http\Controllers;

use App\Models\Announcement;
use Illuminate\Http\Request;

class AnnouncementPublicController extends Controller
{
    public function index()
    {
        $pinned = Announcement::visible()
            ->pinned()
            ->latest('published_at')
            ->get();

        $announcements = Announcement::visible()
            ->where('is_pinned', false)
            ->latest('published_at')
            ->paginate(10);

        $requester = session('requester');

        return view('announcements.index', compact('pinned', 'announcements', 'requester'));
    }

    public function show(string $slug)
    {
        $announcement = Announcement::where('slug', $slug)
            ->visible()
            ->firstOrFail();

        $relatedAnnouncements = Announcement::visible()
            ->where('id', '!=', $announcement->id)
            ->where('category', $announcement->category)
            ->latest('published_at')
            ->limit(3)
            ->get();

        $requester = session('requester');

        return view('announcements.show', compact('announcement', 'relatedAnnouncements', 'requester'));
    }
}

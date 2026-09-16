<?php

namespace App\Http\Controllers;

use App\Models\Announcement;
use App\Models\CertificateEvent;
use Illuminate\Support\Carbon;

/**
 * Peta situs untuk mesin pencari.
 *
 * Dibentuk dari data yang sebenarnya, bukan daftar yang ditulis tangan:
 * pengumuman baru dan daftar penerima yang baru dibuka ikut masuk dengan
 * sendirinya, dan yang ditutup hilang tanpa perlu diingat siapa pun.
 * Halaman yang menuntut login tidak pernah dicantumkan — mesin pencari
 * hanya akan menemui pengalihan ke Google di sana.
 */
class SitemapController extends Controller
{
    /**
     * Jalur yang menuntut login atau memuat data milik seseorang. Perayap
     * hanya akan menemui pengalihan di sana, dan isinya bukan untuk umum.
     *
     * @var array<int, string>
     */
    private const TERTUTUP = [
        '/panel', '/admin', '/masuk', '/dashboard', '/my-bookings',
        '/booking', '/book', '/borrow', '/request/status', '/request/success',
        '/attend', '/verify', '/certificates', '/auth', '/google',
    ];

    public function __invoke()
    {
        $halaman = [
            ['loc' => route('landing'), 'priority' => '1.0', 'changefreq' => 'weekly'],
            ['loc' => route('announcements.index'), 'priority' => '0.8', 'changefreq' => 'daily'],
            ['loc' => route('request.content'), 'priority' => '0.7', 'changefreq' => 'monthly'],
        ];

        foreach (Announcement::visible()->latest('published_at')->get() as $pengumuman) {
            $halaman[] = [
                'loc' => route('announcements.show', $pengumuman->slug),
                'lastmod' => ($pengumuman->updated_at ?? $pengumuman->published_at)?->toAtomString(),
                'priority' => '0.6',
                'changefreq' => 'monthly',
            ];
        }

        // Hanya kegiatan yang daftarnya memang dibuka admin.
        foreach (CertificateEvent::where('recipients_public', true)->where('status', 'published')->get() as $kegiatan) {
            $halaman[] = [
                'loc' => $kegiatan->publicRecipientsUrl(),
                'lastmod' => $kegiatan->updated_at?->toAtomString(),
                'priority' => '0.5',
                'changefreq' => 'monthly',
            ];
        }

        return response()
            ->view('sitemap', ['halaman' => $halaman, 'sekarang' => Carbon::now()->toAtomString()])
            ->header('Content-Type', 'application/xml');
    }

    /**
     * robots.txt disajikan lewat rute, bukan berkas statis, supaya baris
     * Sitemap selalu menunjuk domain yang sedang dipakai.
     */
    public function robots()
    {
        return response()
            ->view('robots', ['tertutup' => self::TERTUTUP])
            ->header('Content-Type', 'text/plain');
    }
}

<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Route as RoutingRoute;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Rute publik harus dibatasi lajunya.
 *
 * Halaman verifikasi dan unduhan sertifikat sebelumnya tidak dibatasi sama
 * sekali, padahal rute publik lain di proyek ini sudah. Unduhan yang paling
 * berisiko: tiap panggilan merender ulang PDF beserta desain latar yang
 * ditanam ke dalamnya, sehingga satu tautan yang dipukul berulang-ulang cukup
 * untuk menghabiskan memori server.
 */
class PublicRouteThrottlingTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Rute yang memang tidak perlu dibatasi, beserta alasannya.
     *
     * Beranda, pengumuman, dan peta situs murah dan memang dimaksudkan untuk
     * dirayapi mesin pencari; membatasinya justru merugikan. Rute yang menuntut
     * sesi peminjam sudah terjaga oleh login itu sendiri.
     *
     * @var list<string>
     */
    private const TANPA_BATAS = [
        'landing',
        'landing.alias',
        'bio',
        'announcements.index',
        'announcements.show',
        'sitemap',
        'robots',
        'login.portal',
        'google.redirect',
        'requester.dashboard',
        'booking.success',
        'booking.inventory',
        'booking.room',
        'booking.logout',
        'my.bookings',
        'my.bookings.detail',
        'request.content',
        'request.success',
        'request.status',
        'request.status.detail',

        // Rute bawaan Laravel untuk melayani berkas storage saat
        // dikembangkan. Di server, public/storage adalah tautan simbolik yang
        // dilayani langsung oleh Nginx dan tidak pernah menyentuh PHP.
        'storage.local',

        // Milik Livewire, untuk melihat berkas yang baru diunggah sebelum
        // formulirnya disimpan. URL-nya bertanda tangan dan hanya berlaku bagi
        // sesi yang mengunggahnya.
        'livewire.preview-file',
    ];

    /**
     * @return list<RoutingRoute>
     */
    private function publicRoutes(): array
    {
        $rute = [];

        foreach (Route::getRoutes() as $route) {
            $nama = $route->getName();

            if ($nama === null || ! in_array('GET', $route->methods(), true)) {
                continue;
            }

            $middleware = $route->gatherMiddleware();

            // Yang menuntut login sudah terjaga; yang di panel pun begitu.
            if (in_array('auth', $middleware, true) || str_starts_with($nama, 'filament.')) {
                continue;
            }

            $rute[] = $route;
        }

        $this->assertGreaterThan(10, count($rute), 'Enumerasi rute publik tidak menghasilkan apa-apa.');

        return $rute;
    }

    public function test_every_public_route_is_either_throttled_or_listed_as_safe(): void
    {
        $telanjang = [];

        foreach ($this->publicRoutes() as $route) {
            $nama = $route->getName();

            if (in_array($nama, self::TANPA_BATAS, true)) {
                continue;
            }

            $adaBatas = collect($route->gatherMiddleware())
                ->contains(fn ($m) => is_string($m) && str_starts_with($m, 'throttle:'));

            if (! $adaBatas) {
                $telanjang[] = $nama.' ('.$route->uri().')';
            }
        }

        $this->assertSame([], $telanjang,
            "Rute publik tanpa pembatasan laju:\n".implode("\n", $telanjang)
            ."\nTambahkan throttle, atau sebutkan alasannya di TANPA_BATAS.");
    }

    /**
     * Unduhan dibatasi lebih ketat daripada sekadar melihat, karena ia jauh
     * lebih mahal: satu PDF dirender ulang tiap kali.
     */
    public function test_downloading_is_held_tighter_than_looking(): void
    {
        $batas = function (string $nama): int {
            $route = Route::getRoutes()->getByName($nama);

            foreach ($route->gatherMiddleware() as $m) {
                if (is_string($m) && str_starts_with($m, 'throttle:')) {
                    return (int) explode(',', substr($m, strlen('throttle:')))[0];
                }
            }

            return PHP_INT_MAX;
        };

        $this->assertLessThan(
            $batas('certificates.verify'),
            $batas('certificates.download'),
            'Unduhan tidak lebih ketat daripada halaman verifikasinya, padahal jauh lebih mahal.',
        );
    }
}

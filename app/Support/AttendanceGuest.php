<?php

namespace App\Support;

use App\Enums\AttendanceAction;
use App\Models\CertificateEvent;
use Illuminate\Support\Facades\Session;

/**
 * Sesi tamu absensi: akun Google di luar JGU yang login hanya untuk mencatat
 * kehadiran di kegiatan yang terbuka untuk umum.
 *
 * Sengaja terpisah dari sesi pemohon (RequesterSession). Portal pengajuan
 * konten dan peminjaman ruangan tetap khusus sivitas JGU; seandainya tamu
 * memakai sesi pemohon, ia ikut bisa mengajukan peminjaman. Sesi ini hanya
 * dibaca halaman absensi, dan hanya untuk kegiatan yang mengizinkan tamu.
 */
final class AttendanceGuest
{
    public const KEY = 'attendance_guest';

    /**
     * Kegiatan yang menerima tamu, bila alamat tujuan login adalah halaman
     * absensinya; null bila bukan.
     */
    public static function eventForUrl(?string $url): ?CertificateEvent
    {
        $path = trim((string) parse_url((string) $url, PHP_URL_PATH), '/');

        if (preg_match('#^attend/([a-z]+)/([^/]+)$#', $path, $cocok) !== 1) {
            return null;
        }

        $aksi = AttendanceAction::tryFrom($cocok[1]);

        if ($aksi === null) {
            return null;
        }

        return CertificateEvent::query()
            ->where($aksi->tokenColumn(), $cocok[2])
            ->where('attendance_enabled', true)
            ->where('attendance_guests_allowed', true)
            ->first();
    }

    /**
     * @param  array{google_id?: string|null, name?: string|null, email: string, avatar?: string|null}  $akun
     */
    public static function start(array $akun): void
    {
        Session::regenerate();
        Session::put(self::KEY, [...$akun, 'email' => strtolower($akun['email']), 'type' => 'guest']);
    }

    /**
     * @return array{email: string, name?: string|null, google_id?: string|null}|null
     */
    public static function current(): ?array
    {
        $tamu = Session::get(self::KEY);

        return is_array($tamu) && filled($tamu['email'] ?? null) ? $tamu : null;
    }

    public static function forget(): void
    {
        Session::forget(self::KEY);
    }
}

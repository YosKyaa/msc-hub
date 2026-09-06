<?php

namespace App\Support;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Session;

/**
 * Sesi peminjam publik.
 *
 * Login Google untuk peminjam tidak memakai guard Laravel, sehingga jejak
 * waktunya harus disimpan sendiri. Semua penulisan dikumpulkan di sini agar
 * ExpireStaleLogins punya satu bentuk data yang bisa diandalkan.
 */
class RequesterSession
{
    public const KEY = 'requester';

    /** Pesan yang menunggu ditampilkan setelah peminjam masuk kembali. */
    public const NOTICE_KEY = 'msc_login_notice';

    /**
     * @param  array<string, mixed>  $profile
     */
    public static function start(array $profile): void
    {
        $now = Carbon::now()->toIso8601String();

        Session::put(self::KEY, [
            ...$profile,
            'authenticated_at' => $now,
            'last_active_at' => $now,
        ]);
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function get(): ?array
    {
        $requester = Session::get(self::KEY);

        return is_array($requester) ? $requester : null;
    }

    public static function touch(): void
    {
        $requester = self::get();

        if ($requester === null) {
            return;
        }

        $requester['last_active_at'] = Carbon::now()->toIso8601String();

        Session::put(self::KEY, $requester);
    }

    public static function forget(): void
    {
        Session::forget(self::KEY);
    }

    /**
     * Jejak waktu sesi, dicatat sekarang bila belum ada.
     *
     * Sesi yang dibuat versi sebelumnya belum menyimpan `last_active_at`;
     * menganggapnya dimulai sekarang mencegah peminjam yang sedang aktif
     * terlempar keluar begitu versi ini dirilis.
     *
     * @param  array<string, mixed>  $requester
     */
    public static function stamp(array $requester, string $key): Carbon
    {
        $value = $requester[$key] ?? null;

        if (is_string($value) && $value !== '') {
            return Carbon::parse($value);
        }

        // Ditulis di atas keadaan sesi terkini supaya dua pemanggilan
        // berturut-turut tidak saling menimpa jejak waktu.
        $now = Carbon::now();
        $current = self::get() ?? $requester;
        $current[$key] = $now->toIso8601String();
        Session::put(self::KEY, $current);

        return $now;
    }
}

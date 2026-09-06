<?php

namespace App\Support;

use Illuminate\Support\Carbon;

/**
 * Kebijakan kedaluwarsa login.
 *
 * Peminjam publik masuk lewat Google tanpa guard Laravel, sedangkan panel
 * memakai guard biasa. Keduanya memakai aturan yang sama dari sini supaya
 * tidak ada satu pun sesi yang berlaku tanpa batas.
 */
class LoginTimeout
{
    /** Tidak ada permintaan selama sekian menit. */
    public const IDLE = 'idle';

    /** Batas keras sejak login, seaktif apa pun penggunanya. */
    public const ABSOLUTE = 'absolute';

    public function __construct(
        public readonly int $idleMinutes,
        public readonly int $absoluteMinutes,
    ) {}

    /**
     * Peminjam menganggur berjam-jam di antara check-in dan check-out acara,
     * sedangkan panel memegang persetujuan; keduanya memang beda angka.
     */
    public static function forRequester(): self
    {
        return self::fromConfig('requester', idleMinutes: 480, absoluteMinutes: 720);
    }

    public static function forPanel(): self
    {
        return self::fromConfig('panel', idleMinutes: 120, absoluteMinutes: 480);
    }

    private static function fromConfig(string $side, int $idleMinutes, int $absoluteMinutes): self
    {
        return new self(
            (int) config("msc.session.{$side}.idle_timeout", $idleMinutes),
            (int) config("msc.session.{$side}.absolute_timeout", $absoluteMinutes),
        );
    }

    /**
     * Alasan sesi harus diakhiri, atau null bila masih berlaku.
     */
    public function expiryReason(Carbon $loggedInAt, Carbon $lastSeenAt, ?Carbon $now = null): ?string
    {
        $now ??= Carbon::now();

        // Batas keras diperiksa lebih dulu: sesi yang sudah terlalu tua
        // tetap berakhir walaupun penggunanya baru saja beraktivitas.
        if ($this->absoluteMinutes > 0 && $loggedInAt->copy()->addMinutes($this->absoluteMinutes)->lte($now)) {
            return self::ABSOLUTE;
        }

        if ($this->idleMinutes > 0 && $lastSeenAt->copy()->addMinutes($this->idleMinutes)->lte($now)) {
            return self::IDLE;
        }

        return null;
    }

    public function message(string $reason): string
    {
        return $reason === self::IDLE
            ? 'Sesi Anda berakhir karena tidak ada aktivitas. Silakan masuk kembali.'
            : 'Sesi Anda telah mencapai batas waktu. Silakan masuk kembali.';
    }
}

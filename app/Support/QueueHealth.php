<?php

namespace App\Support;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Apakah antrean benar-benar dikerjakan.
 *
 * Pekerjaan yang dititipkan ke antrean tanpa ada pekerja yang menjalankannya
 * akan menumpuk diam-diam: panel menjawab "diantrekan", lalu tidak terjadi
 * apa-apa. Pemeriksaan ini membuat keadaan itu terlihat sebelum admin
 * menunggu sia-sia.
 */
class QueueHealth
{
    /** Pekerjaan yang belum tersentuh selama ini dianggap menumpuk. */
    private const STALE_MINUTES = 5;

    public static function pendingCount(): int
    {
        if (! self::isInspectable()) {
            return 0;
        }

        try {
            return DB::table('jobs')->count();
        } catch (Throwable) {
            return 0;
        }
    }

    public static function isStalled(): bool
    {
        if (! self::isInspectable()) {
            return false;
        }

        try {
            $oldest = DB::table('jobs')->min('created_at');
        } catch (Throwable) {
            return false;
        }

        return $oldest !== null
            && Carbon::createFromTimestamp($oldest)->addMinutes(self::STALE_MINUTES)->isPast();
    }

    /**
     * Peringatan siap tampil, atau null bila antreannya sehat.
     */
    public static function warning(): ?string
    {
        if (! self::isStalled()) {
            return null;
        }

        $tertunda = self::pendingCount();

        return "Perhatian: {$tertunda} pekerjaan menumpuk di antrean dan belum ada yang mengerjakannya. "
            .'Jalankan `php artisan queue:work` di server, kalau tidak email ini hanya akan ikut menumpuk.';
    }

    /**
     * Hanya antrean database yang isinya bisa diperiksa dari sini. Driver
     * `sync` mengerjakan semuanya seketika, jadi tidak pernah menumpuk.
     */
    private static function isInspectable(): bool
    {
        return config('queue.default') === 'database';
    }
}

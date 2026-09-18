<?php

namespace App\Support;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
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

    /** Denyut pekerja antrean yang terakhir terdengar. */
    public const HEARTBEAT_KEY = 'antrean:pekerja-terakhir';

    /**
     * Pekerja memanggil denyutnya tiap kali menengok antrean — beberapa kali
     * semenit. Sunyi lebih lama daripada ini berarti tidak ada yang bekerja.
     */
    private const SILENT_SECONDS = 120;

    /** Denyutnya cukup ditulis sesekali; tiap tengokan terlalu boros. */
    private const HEARTBEAT_EVERY_SECONDS = 20;

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
     * Tandai bahwa masih ada pekerja yang hidup.
     *
     * Dipanggil dari peristiwa Looping, yaitu tiap kali pekerja menengok
     * antrean. Ini satu-satunya cara mengetahui keberadaannya: Laravel tidak
     * mencatat pekerjanya di mana pun.
     */
    public static function recordWorkerHeartbeat(): void
    {
        try {
            $terakhir = Cache::get(self::HEARTBEAT_KEY);

            if ($terakhir !== null
                && Carbon::parse($terakhir)->addSeconds(self::HEARTBEAT_EVERY_SECONDS)->isFuture()) {
                return;
            }

            Cache::put(self::HEARTBEAT_KEY, Carbon::now()->toIso8601String(), now()->addDay());
        } catch (Throwable) {
            // Denyut yang gagal ditulis tidak boleh menghentikan pekerjanya.
        }
    }

    public static function workerLastSeen(): ?Carbon
    {
        try {
            $terakhir = Cache::get(self::HEARTBEAT_KEY);
        } catch (Throwable) {
            return null;
        }

        return $terakhir === null ? null : Carbon::parse($terakhir);
    }

    /**
     * Apakah ada pekerja yang sedang menjalankan antrean saat ini.
     */
    public static function hasWorker(): bool
    {
        if (! self::isInspectable()) {
            // Driver sync mengerjakan semuanya seketika di proses yang sama.
            return true;
        }

        $terakhir = self::workerLastSeen();

        return $terakhir !== null
            && $terakhir->addSeconds(self::SILENT_SECONDS)->isFuture();
    }

    /**
     * Peringatan siap tampil, atau null bila antreannya sehat.
     *
     * Dua keadaan yang berbeda, dan yang pertama jauh lebih sering terjadi:
     * tidak ada pekerja sama sekali. Menumpuknya pekerjaan baru terlihat lima
     * menit kemudian, padahal admin sudah keburu menutup halamannya dan
     * mengira emailnya terkirim.
     */
    public static function warning(): ?string
    {
        if (! self::isInspectable()) {
            return null;
        }

        $tertunda = self::pendingCount();

        // Antrean kosong tidak perlu pekerja. Memperingatkannya justru
        // membuat peringatan ini kehilangan arti saat benar-benar dibutuhkan.
        if ($tertunda === 0) {
            return null;
        }

        if (! self::hasWorker()) {
            return "Tidak ada pekerja antrean yang berjalan di server, jadi {$tertunda} pekerjaan ini "
                .'hanya akan menumpuk dan emailnya tidak akan terkirim. Jalankan `php artisan queue:work` '
                .'di server (atau hidupkan kembali layanan Supervisor-nya), lalu emailnya terkirim sendiri.';
        }

        if (! self::isStalled()) {
            return null;
        }

        return "Perhatian: {$tertunda} pekerjaan menumpuk di antrean dan belum ada yang mengerjakannya. "
            .'Periksa apakah pekerja antreannya masih hidup di server.';
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

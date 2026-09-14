<?php

namespace App\Support;

use GuzzleHttp\Exception\RequestException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Str;
use Laravel\Socialite\Two\InvalidStateException;
use Throwable;

/**
 * Perjalanan login Google, untuk peminjam maupun panel.
 *
 * Sebelumnya setiap kegagalan ditelan menjadi satu kalimat yang sama tanpa
 * satu baris log pun, sehingga penyebabnya mustahil dilacak dan penggunanya
 * tidak tahu harus berbuat apa. Semua penanda dan penerjemahan kegagalan
 * dikumpulkan di sini supaya kedua controller berperilaku sama.
 */
class GoogleLogin
{
    /** Penanda bahwa perjalanan ini menuju panel, bukan portal peminjam. */
    public const TYPE_KEY = 'google_auth_type';

    /** Kapan perjalanan dimulai, untuk membatasi umur satu percobaan login. */
    public const STARTED_AT_KEY = 'google_auth_started_at';

    public const ADMIN = 'admin';

    /**
     * Tandai awal perjalanan. $type null berarti login peminjam biasa.
     */
    public static function begin(?string $type = null): void
    {
        Session::put(self::STARTED_AT_KEY, Carbon::now()->toIso8601String());

        if ($type === null) {
            Session::forget(self::TYPE_KEY);

            return;
        }

        Session::put(self::TYPE_KEY, $type);
    }

    public static function isForPanel(): bool
    {
        return Session::get(self::TYPE_KEY) === self::ADMIN;
    }

    /**
     * Bersihkan penanda begitu perjalanan selesai — berhasil maupun gagal —
     * agar percobaan berikutnya tidak mewarisi keadaan yang lama.
     */
    public static function finish(): void
    {
        Session::forget([self::TYPE_KEY, self::STARTED_AT_KEY]);
    }

    /**
     * Halaman login yang dibiarkan terbuka berjam-jam menghasilkan kode
     * otorisasi basi. Batas ini menolaknya dengan alasan yang jelas, alih-alih
     * memunculkan galat Socialite yang tidak berarti apa-apa bagi pengguna.
     */
    public static function attemptExpired(): bool
    {
        $minutes = (int) config('msc.session.login_attempt_timeout', 15);

        if ($minutes <= 0) {
            return false;
        }

        $startedAt = Session::get(self::STARTED_AT_KEY);

        // Callback tanpa jejak awal berarti sesinya hilang di tengah jalan.
        if (! is_string($startedAt) || $startedAt === '') {
            return true;
        }

        return Carbon::parse($startedAt)->addMinutes($minutes)->lte(Carbon::now());
    }

    public static function expiredMessage(): string
    {
        $minutes = (int) config('msc.session.login_attempt_timeout', 15);

        return "Proses login melebihi batas {$minutes} menit atau sesinya terputus. Silakan mulai lagi dari awal.";
    }

    /**
     * Catat penyebab sebenarnya, lalu kembalikan kalimat yang bisa ditindak
     * oleh penggunanya.
     */
    public static function report(Throwable $exception, string $flow): string
    {
        try {
            Log::warning('Login Google gagal.', array_filter([
                'alur' => $flow,
                'redirect_uri' => config('services.google.redirect'),
                // Guzzle memotong isi balasan di pesan galatnya, padahal di
                // situlah Google menyebut sebabnya: invalid_client,
                // redirect_uri_mismatch, invalid_grant, dan seterusnya.
                'jawaban_google' => self::responseBody($exception),
                'exception' => $exception,
            ]));
        } catch (Throwable) {
            // Menulis log pun bisa gagal; penggunanya tidak boleh menanggungnya.
        }

        if ($exception instanceof InvalidStateException) {
            return 'Sesi login tidak dikenali lagi — biasanya karena cookie browser diblokir atau halaman login dibiarkan terlalu lama. Silakan mulai lagi dari awal.';
        }

        return 'Gagal menghubungi Google. Silakan coba lagi beberapa saat lagi.';
    }

    /**
     * Isi balasan Google, bila kegagalannya berasal dari panggilan HTTP.
     * Balasan galat OAuth hanya berisi kode dan penjelasan, tanpa kredensial.
     */
    private static function responseBody(Throwable $exception): ?string
    {
        if (! $exception instanceof RequestException || ! $exception->hasResponse()) {
            return null;
        }

        return Str::limit((string) $exception->getResponse()->getBody(), 500);
    }
}

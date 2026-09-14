<?php

namespace App\Support;

/**
 * Menjaga parameter ?redirect= agar hanya bisa mengarah ke halaman internal.
 *
 * Tanpa pembatasan ini, URL login Google dapat dipakai untuk melempar pengguna
 * ke domain pihak ketiga setelah otentikasi (open redirect).
 */
class SafeRedirect
{
    /**
     * Kembalikan URL internal yang aman, atau $fallback jika target tidak sah.
     */
    public static function sanitize(?string $target, string $fallback): string
    {
        $target = trim((string) $target);

        if ($target === '') {
            return $fallback;
        }

        // URL absolut hanya diterima bila host-nya sama dengan host aplikasi.
        if (preg_match('#^[a-z][a-z0-9+.-]*://#i', $target) || str_starts_with($target, '//')) {
            return static::isSameHost($target) ? $target : $fallback;
        }

        // Path relatif wajib diawali satu garis miring dan bebas backslash,
        // karena "/\evil.com" ditafsirkan sebagian browser sebagai URL protocol-relative.
        if (! str_starts_with($target, '/') || str_contains($target, '\\')) {
            return $fallback;
        }

        return url($target);
    }

    private static function isSameHost(string $target): bool
    {
        $targetHost = parse_url($target, PHP_URL_HOST);
        $appHost = parse_url((string) config('app.url'), PHP_URL_HOST);

        return $targetHost !== null
            && $appHost !== null
            && strcasecmp($targetHost, $appHost) === 0;
    }
}

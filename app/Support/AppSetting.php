<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Pengaturan aplikasi yang boleh diubah admin dari panel tanpa deploy.
 *
 * Nilainya disimpan sebagai JSON dan di-cache, karena dibaca pada setiap
 * penerbitan sertifikat tetapi hampir tidak pernah berubah.
 */
class AppSetting
{
    private const CACHE_PREFIX = 'app-setting:';

    public static function get(string $key, mixed $default = null): mixed
    {
        $value = Cache::rememberForever(self::CACHE_PREFIX.$key, function () use ($key) {
            $stored = DB::table('app_settings')->where('key', $key)->value('value');

            // Dibungkus array agar nilai null yang memang tersimpan tidak
            // membuat cache dianggap kosong dan dibaca ulang terus-menerus.
            return ['value' => $stored === null ? null : json_decode($stored, true)];
        });

        return $value['value'] ?? $default;
    }

    public static function set(string $key, mixed $value): void
    {
        DB::table('app_settings')->updateOrInsert(
            ['key' => $key],
            ['value' => json_encode($value), 'updated_at' => now(), 'created_at' => now()],
        );

        Cache::forget(self::CACHE_PREFIX.$key);
    }

    public static function forget(string $key): void
    {
        DB::table('app_settings')->where('key', $key)->delete();
        Cache::forget(self::CACHE_PREFIX.$key);
    }
}

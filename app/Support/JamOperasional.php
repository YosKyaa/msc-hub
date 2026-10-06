<?php

namespace App\Support;

use Illuminate\Support\Carbon;

/**
 * Jam buka layanan peminjaman.
 *
 * Angkanya dulu ditulis langsung di dalam formulir peminjaman alat, sehingga
 * mengubah jam buka menuntut menyunting berkas tampilan dan merilis ulang.
 * Sekarang ia tersimpan sebagai pengaturan, bisa diubah admin dari panel,
 * dan dibaca dari satu tempat ini.
 */
class JamOperasional
{
    public const KUNCI_BUKA = 'operasional.jam_buka';

    public const KUNCI_TUTUP = 'operasional.jam_tutup';

    public const KUNCI_CATATAN = 'operasional.catatan';

    public const BUKA_BAWAAN = '08:00';

    public const TUTUP_BAWAAN = '16:00';

    public const CATATAN_BAWAAN = 'Peminjaman hanya dapat dilakukan dalam jam operasional. '
        .'Proses persetujuan memerlukan tinjauan Staf dan Kepala MSC.';

    public static function buka(): string
    {
        return self::jam(AppSetting::get(self::KUNCI_BUKA), self::BUKA_BAWAAN);
    }

    public static function tutup(): string
    {
        return self::jam(AppSetting::get(self::KUNCI_TUTUP), self::TUTUP_BAWAAN);
    }

    public static function catatan(): string
    {
        $catatan = AppSetting::get(self::KUNCI_CATATAN);

        return filled($catatan) ? (string) $catatan : self::CATATAN_BAWAAN;
    }

    /**
     * Rentangnya siap tampil, misalnya "08:00 - 16:00".
     */
    public static function rentang(): string
    {
        return self::buka().' - '.self::tutup();
    }

    /**
     * Rapikan nilai yang tersimpan menjadi "HH:MM".
     *
     * Pengaturan lama bisa saja menyimpan "8:0" atau menyertakan detik, dan
     * jam yang tampil setengah jadi lebih membingungkan daripada jam bawaan.
     */
    private static function jam(mixed $nilai, string $bawaan): string
    {
        if (blank($nilai)) {
            return $bawaan;
        }

        try {
            return Carbon::createFromFormat('H:i', substr((string) $nilai, 0, 5))->format('H:i');
        } catch (\Throwable) {
            return $bawaan;
        }
    }
}

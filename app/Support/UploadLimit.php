<?php

namespace App\Support;

/**
 * Batas ukuran unggahan yang benar-benar berlaku di server ini.
 *
 * Batas sesungguhnya ditentukan PHP lewat upload_max_filesize dan
 * post_max_size, dan bawaannya sering hanya 2 MB — jauh di bawah yang
 * dibayangkan orang. Berkas yang melewatinya ditolak sebelum Laravel sempat
 * melihatnya, sehingga yang muncul hanyalah pesan Livewire "failed to upload"
 * tanpa menyebut sebab maupun angkanya.
 *
 * Dengan membaca batas itu dari PHP, pesan di formulir selalu menyebut angka
 * yang sungguh berlaku di mesin tempat aplikasinya berjalan.
 */
class UploadLimit
{
    /**
     * Batas efektif dalam kilobita: yang terkecil di antara kedua pengaturan.
     */
    public static function maxKilobytes(): int
    {
        $unggah = self::toKilobytes((string) ini_get('upload_max_filesize'));
        $kiriman = self::toKilobytes((string) ini_get('post_max_size'));

        $berlaku = min(
            $unggah > 0 ? $unggah : PHP_INT_MAX,
            $kiriman > 0 ? $kiriman : PHP_INT_MAX,
        );

        return $berlaku === PHP_INT_MAX ? 2048 : $berlaku;
    }

    /**
     * Batas untuk sebuah isian: yang diinginkan, tetapi tidak pernah melebihi
     * yang sanggup diterima server.
     */
    public static function forField(int $diinginkanKb): int
    {
        return max(1, min($diinginkanKb, self::maxKilobytes()));
    }

    /**
     * Angkanya dalam satuan yang enak dibaca, misalnya "4 MB" atau "512 KB".
     */
    public static function describe(?int $kilobita = null): string
    {
        $kb = $kilobita ?? self::maxKilobytes();

        return $kb >= 1024
            ? rtrim(rtrim(number_format($kb / 1024, 1, ',', ''), '0'), ',').' MB'
            : $kb.' KB';
    }

    /**
     * Terjemahkan notasi singkat PHP — 2M, 512K, 1G — menjadi kilobita.
     */
    private static function toKilobytes(string $nilai): int
    {
        $nilai = trim($nilai);

        if ($nilai === '' || $nilai === '-1' || $nilai === '0') {
            return 0;
        }

        $angka = (float) $nilai;
        $satuan = strtolower(substr($nilai, -1));

        return (int) match ($satuan) {
            'g' => $angka * 1024 * 1024,
            'm' => $angka * 1024,
            'k' => $angka,
            default => $angka / 1024,
        };
    }
}
